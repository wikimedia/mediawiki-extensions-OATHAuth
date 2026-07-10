<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Auth;

use LogicException;
use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserNameUtils;

class SecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	public const array MODULE_PRIORITY = [ 'webauthn', 'totp', 'recoverycodes' ];
	public const string SUCCESS_KEY = 'oathauth-skip-2fa';

	public function __construct(
		private readonly OATHAuthLogger $oathLogger,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHUserRepository $userRepository,
		private readonly HookContainer $hookContainer,
		private readonly UserNameUtils $usernameUtils,
	) {
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * If the user has enabled two-factor authentication, request a second factor.
	 *
	 * @inheritDoc
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		if ( $this->manager->getAuthenticationSessionData( self::SUCCESS_KEY ) ) {
			// The user logged in with 2FA through a primary auth provider, like
			// PasskeyPrimaryAuthenticationProvider or ReauthPrimaryAuthenticationProvider
			return AuthenticationResponse::newAbstain();
		}

		$authUser = $this->userRepository->findByUser( $user );

		if ( !$authUser->isTwoFactorAuthEnabled() ) {
			return AuthenticationResponse::newAbstain();
		}

		$module = $this->getModule( $authUser, $reqs );
		if ( !$module ) {
			throw new LogicException( 'Not possible' );
		}
		$provider = $this->getProviderForModule( $module );
		$this->oathLogger->logTwoFactorChallengePresented( $user, $provider->getUniqueId() );
		$response = $provider->beginSecondaryAuthentication( $user, [] );

		// Include information about used module in request so that the correct
		// provider can be used when continuing
		$this->maybeAddSelectAuthenticationRequest( $authUser, $response, $module );

		return $response;
	}

	/** @inheritDoc */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		$authUser = $this->userRepository->findByUser( $user );

		$module = $this->getModule( $authUser, $reqs );
		if ( !$module ) {
			return AuthenticationResponse::newFail( wfMessage( 'oathauth-invalidrequest' ) );
		}
		$provider = $this->getProviderForModule( $module );

		/** @var TwoFactorModuleSelectAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, TwoFactorModuleSelectAuthenticationRequest::class );
		if ( $request && $request->newModule ) {
			// The user is switching modules, restart
			$response = $provider->beginSecondaryAuthentication( $user, [] );
		} else {
			$response = $provider->continueSecondaryAuthentication( $user, $reqs );
		}

		if ( $response->status === AuthenticationResponse::PASS ) {
			$this->oathLogger->logSuccessfulVerification( $user );
		}

		$this->maybeAddSelectAuthenticationRequest( $authUser, $response, $module );
		return $response;
	}

	private function getModule( OATHUser $authUser, array $reqs ): ?string {
		return $this->getModuleFromRequest( $authUser, $reqs )
			?? $this->getDefaultModule( $authUser );
	}

	/**
	 * Return the ID of the module corresponding to the 2FA type option the user selected in the
	 * login form (or null if not selected / invalid).
	 *
	 * @param OATHUser $authUser
	 * @param AuthenticationRequest[] $reqs
	 * @return string|null
	 */
	private function getModuleFromRequest( OATHUser $authUser, array $reqs ): ?string {
		/** @var TwoFactorModuleSelectAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, TwoFactorModuleSelectAuthenticationRequest::class );
		if ( !$request ) {
			return null;
		}
		$module = $request->newModule ?: $request->currentModule;

		// Validate that the specified module ID is valid
		// and enabled for the user.
		foreach ( $authUser->getKeys() as $key ) {
			if ( $key->getModule() === $module ) {
				return $module;
			}
		}

		return null;
	}

	private function getDefaultModule( OATHUser $authUser ): ?string {
		// HACK: If the request came from the clientlogin API, and the user has both
		// TOTP and other modules enabled, only present TOTP. This is needed to avoid
		// breaking the Wikipedia mobile apps until they can handle users with multiple
		// modules enabled. (T399654)
		if ( defined( 'MW_API' ) && $authUser->getKeysForModule( 'totp' ) ) {
			return 'totp';
		}

		// Use the highest-priority module the user has
		foreach ( self::MODULE_PRIORITY as $module ) {
			if ( $authUser->getKeysForModule( $module ) ) {
				return $module;
			}
		}

		// Return the first key from the db if the user doesn't have any of the prioritized modules
		return $authUser->getKeys() ? $authUser->getKeys()[0]->getModule() : null;
	}

	private function getProviderForModule( string $moduleId ): AbstractSecondaryAuthenticationProvider {
		$module = $this->moduleRegistry->getModuleByKey( $moduleId );

		$provider = $module->getSecondaryAuthProvider();
		$provider->init(
			$this->logger,
			$this->manager,
			$this->hookContainer,
			$this->config,
			$this->usernameUtils,
		);
		return $provider;
	}

	private function maybeAddSelectAuthenticationRequest(
		OATHUser $authUser,
		AuthenticationResponse $response,
		string $currentModule
	): void {
		if ( !in_array( $response->status, [ AuthenticationResponse::UI, AuthenticationResponse::REDIRECT ] ) ) {
			return;
		}

		$allowedModules = [];
		foreach ( $authUser->getKeys() as $key ) {
			$module = $this->moduleRegistry->getModuleByKey( $key->getModule() );
			$allowedModules[$module->getName()] = $module->getDisplayName();
		}
		if ( ReauthPrimaryAuthenticationProvider::isRestrictedReauth( $this->manager ) ) {
			unset( $allowedModules[RecoveryCodes::MODULE_NAME] );
		}
		// Do not add the select request if there's nothing else to select.
		if ( count( $allowedModules ) > 1 ) {
			$selectRequest = new TwoFactorModuleSelectAuthenticationRequest( $currentModule, $allowedModules );
			$response->neededRequests[] = $selectRequest;
		}
	}
}
