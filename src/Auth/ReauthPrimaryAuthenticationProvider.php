<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Auth;

use BadMethodCallException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ElevatedSecurityAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Status\Status;

/**
 * Dummy authentication provider that only exists to bypass primary authentication when the user is
 * reauthenticating and has 2FA enabled. For these users, reauthentication only requires their second
 * factor, and is handled in SecondaryAuthenticationProvider.
 */
class ReauthPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry
	) {
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action !== AuthManager::ACTION_LOGIN || !isset( $options['securityLevel'] ) ) {
			// Not a reauthentication, skip
			return [];
		}

		$user = $this->manager->getRequest()->getSession()->getUser();
		$oathUser = $this->userRepo->findByUser( $user );
		$availableModules = [];
		$hasPasswordless = false;
		$hasNonpasswordlessWebAuthn = false;
		// TODO the logic for building $availableModules is duplicated from SecondaryAuthenticationProvider
		foreach ( $oathUser->getKeys() as $key ) {
			$module = $this->moduleRegistry->getModuleByKey( $key->getModule() );
			$availableModules[$module->getName()] = $module->getDisplayName();

			if ( $key->supportsPasswordlessLogin() ) {
				$hasPasswordless = true;
			} elseif ( $module->getName() === WebAuthn::MODULE_NAME ) {
				$hasNonpasswordlessWebAuthn = true;
			}
		}
		if ( !$availableModules ) {
			return [];
		}

		if ( $hasPasswordless && $this->config->get( 'OATHPasswordlessLogin' ) ) {
			// The user has a passkey. PasskeyPrimaryAuthentiationProvider will already generate a
			// "Log in with passkey" button, we just have to generate switch buttons for the other
			// methods
			return [ new TwoFactorModuleSelectAuthenticationRequest(
				// If the user has non-passkey WebAuthn keys, also include a switch button for WebAuthn,
				// by lying about the current module name
				$hasNonpasswordlessWebAuthn ? 'passkey' : WebAuthn::MODULE_NAME,
				$availableModules
			) ];
		}

		// The user doesn't have a passkey. Let SecondaryAuthenticationProvider generate both a
		// request for the primary method and a switch request
		/** @var SecondaryAuthenticationProvider|null $secondaryAuthProvider */
		$secondaryAuthProvider = $this->manager->getAuthenticationProvider( SecondaryAuthenticationProvider::class );
		'@phan-var SecondaryAuthenticationProvider|null $secondaryAuthProvider';
		if ( !$secondaryAuthProvider ) {
			// Not supposed to be possible
			return [];
		}
		$reqs = $secondaryAuthProvider->beginSecondaryAuthentication( $user, [] )->neededRequests;

		// Display a "Continue with security key" button if the primary method is a security key
		$webauthnReq = AuthenticationRequest::getRequestByClass( $reqs, WebAuthnAuthenticationRequest::class );
		if ( $webauthnReq ) {
			$webauthnReq->options['showButton'] = 'interstitial';
			$webauthnReq->options['showPrompt'] = false;
		}

		return $reqs;
	}

	/** @inheritDoc */
	public function beginPrimaryAuthentication( array $reqs ) {
		// Skip non-reauth logins, and reauths for users without 2FA
		$elevReq = AuthenticationRequest::getRequestByClass( $reqs, ElevatedSecurityAuthenticationRequest::class );
		$oathUser = $this->userRepo->findByUser( $this->manager->getRequest()->getSession()->getUser() );
		if ( !$elevReq || !$oathUser->isTwoFactorAuthEnabled() ) {
			return AuthenticationResponse::newAbstain();
		}

		// If the user is logging in with a passkey through a request that looks like it was added
		// by PasskeyPrimaryAuthenticationProvider, abstain so that PasskeyPrimaryAuthenticationProvider
		// can handle it instead
		$webauthnReq = AuthenticationRequest::getRequestByClass( $reqs, WebAuthnAuthenticationRequest::class );
		if (
			$webauthnReq
			&& $webauthnReq->credential !== ''
			&& ( $webauthnReq->options['showButton'] ?? false ) === 'passwordless'
		) {
			return AuthenticationResponse::newAbstain();
		}

		return $this->continuePrimaryAuthentication( $reqs );
	}

	/** @inheritDoc */
	public function continuePrimaryAuthentication( array $reqs ) {
		$user = $this->manager->getRequest()->getSession()->getUser();
		/** @var SecondaryAuthenticationProvider|null $secondaryAuthProvider */
		$secondaryAuthProvider = $this->manager->getAuthenticationProvider( SecondaryAuthenticationProvider::class );
		'@phan-var SecondaryAuthenticationProvider|null $secondaryAuthProvider';
		if ( !$secondaryAuthProvider ) {
			// Not supposed to be possible
			return AuthenticationResponse::newAbstain();
		}

		$selectReq = AuthenticationRequest::getRequestByClass( $reqs,
			TwoFactorModuleSelectAuthenticationRequest::class );
		if ( $selectReq && $selectReq->newModule ) {
			// User wants to switch, regenerate the requests
			return $secondaryAuthProvider->beginSecondaryAuthentication( $user, $reqs );
		}

		$response = $secondaryAuthProvider->continueSecondaryAuthentication( $user, $reqs );
		if ( $response->status === AuthenticationResponse::PASS ) {
			$response->username = $user->getName();
			$this->manager->setAuthenticationSessionData( SecondaryAuthenticationProvider::SUCCESS_KEY, true );
		}
		return $response;
	}

	/** @inheritDoc */
	public function accountCreationType() {
		return self::TYPE_NONE;
	}

	/** @inheritDoc */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new BadMethodCallException( 'Shouldn\'t call this when accountCreationType() is NONE' );
	}

	/** @inheritDoc */
	public function testUserExists( $username, $flags = \Wikimedia\Rdbms\IDBAccessObject::READ_NORMAL ) {
		// We rely on other primary authentication providers to manage user existence
		return false;
	}

	/** @inheritDoc */
	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
		// Not supported
		return Status::newGood( 'ignored' );
	}

	/** @inheritDoc */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		// Do nothing; this is not applicable for this provider
	}
}
