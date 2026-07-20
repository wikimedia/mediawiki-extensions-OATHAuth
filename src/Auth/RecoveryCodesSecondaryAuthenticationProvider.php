<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCode;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;

/**
 * AuthManager secondary authentication provider for Recovery Codes second-factor authentication.
 *
 * After a successful primary authentication, requests a recovery code from the user.
 *
 * @see AuthManager
 */
class RecoveryCodesSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	public function __construct(
		private readonly RecoveryCodes $module,
		private readonly OATHUserRepository $userRepository,
		private readonly OATHAuthLogger $oathLogger,
	) {
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ): array {
		// don't ask for anything initially, so the second factor is on a separate screen
		return [];
	}

	/**
	 * If the user has a recovery code module enabled, request a second factor.
	 *
	 * @inheritDoc
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ): AuthenticationResponse {
		if ( ReauthPrimaryAuthenticationProvider::isRestrictedReauth( $this->manager ) ) {
			return AuthenticationResponse::newAbstain();
		}

		$oathUser = $this->userRepository->findByUser( $user );

		if ( !$this->module->isEnabled( $oathUser ) ) {
			return AuthenticationResponse::newAbstain();
		}

		// If the user only has recovery codes, and is reauthenticating, allow them to skip 2FA.
		// This prevents users with initial recovery codes from needing two codes to set up 2FA.
		// If we got here, the reauth has to be for 'OATHManage', otherwise the isRestrictedReauth
		// check would have prevented us from getting here.
		if (
			!$oathUser->userHasNonSpecialEnabledKeys() &&
			$this->manager->getAuthenticationSessionData( 'oathauth-reauth-securitylevel' )
		) {
			return AuthenticationResponse::newAbstain();
		}

		$initialCodesOnly = false;
		if ( $this->config->get( 'OATHAuthEnforce2FAForAll' ) ) {
			// Figure out whether the user has initial recovery codes
			/** @var RecoveryCodeKeys $recoveryCodes */
			$recoveryCodes = $oathUser->getKeysForModule( $this->module->getName() )[ 0 ];
			'@phan-var RecoveryCodeKeys $recoveryCodes';
			$initialCodes = array_filter( $recoveryCodes->getRecoveryCodes(),
				static fn ( RecoveryCode $code ) => $code->isInitial() );
			$temporaryCodes = array_filter( $recoveryCodes->getRecoveryCodes(),
				static fn ( RecoveryCode $code ) => !$code->isPermanent() );

			if ( $initialCodes === [] && $temporaryCodes === [] && !$oathUser->userHasNonSpecialEnabledKeys() ) {
				// The user has no initial or temporary recovery codes, no other keys, and 2FA is required.
				// This means their initial recovery codes have expired and they cannot log in.
				// We do have to allow non-initial temporary recovery codes here, so that users in this
				// situation can use assisted account recovery.
				return AuthenticationResponse::newFail( wfMessage( 'oathauth-recovery-codes-expired-error' ) );
			}
			$initialCodesOnly = $initialCodes !== [];

		}
		return AuthenticationResponse::newUI( [ new RecoveryCodesAuthenticationRequest( $initialCodesOnly ) ] );
	}

	/** @inheritDoc */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		if ( ReauthPrimaryAuthenticationProvider::isRestrictedReauth( $this->manager ) ) {
			return AuthenticationResponse::newFail( wfMessage( 'oathauth-recovery-code-login-failed' ) );
		}

		/** @var RecoveryCodesAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, RecoveryCodesAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newUI( [ new RecoveryCodesAuthenticationRequest() ],
				wfMessage( 'oathauth-recovery-code-login-failed' ), 'error' );
		}

		// Check for (and increment) rate limiter before doing the auth
		if ( $user->pingLimiter( 'badoath' ) || $user->pingLimiter( 'badoath-long' ) ) {
			return AuthenticationResponse::newUI(
				[ new RecoveryCodesAuthenticationRequest() ],
				new Message( 'oathauth-throttled' ),
				'error'
			);
		}

		$authUser = $this->userRepository->findByUser( $user );
		$recoveryCode = $request->RecoveryCode;

		if ( $this->module->verify( $authUser, [ 'recoverycode' => $recoveryCode ] ) ) {
			return AuthenticationResponse::newPass();
		}

		$this->logger->info( 'OATHAuth user {user} failed recovery code from {clientip}', [
			'user'     => $user->getName(),
			'clientip' => $user->getRequest()->getIP(),
		] );

		$this->oathLogger->logFailedVerification( $user );

		return AuthenticationResponse::newUI(
			[ new RecoveryCodesAuthenticationRequest() ],
			wfMessage( 'oathauth-recovery-code-login-failed' ),
			'error'
		);
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
