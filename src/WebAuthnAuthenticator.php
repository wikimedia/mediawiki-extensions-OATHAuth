<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

use Cose\Algorithms;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Json\FormatJson;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use stdClass;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * This class serves as an authentication/registration
 * proxy, connecting the users to their keys and carrying out
 * the authentication process
 */
class WebAuthnAuthenticator {

	use KeySessionStorageTrait;

	private const SESSION_KEY = 'webauthn_session_data';

	/**
	 * 60 seconds
	 */
	private const CLIENT_ACTION_TIMEOUT = 60000;

	protected ?string $serverId;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly WebAuthn $module,
		private readonly RecoveryCodes $recoveryCodesModule,
		private readonly OATHAuthLogger $oathLogger,
		private readonly IContextSource $context,
		private readonly LoggerInterface $logger,
		private readonly AuthManager $authManager,
		private readonly UrlUtils $urlUtils,
		private readonly UserFactory $userFactory,
	) {
		$this->serverId = $this->getServerId();
	}

	public function isEnabled( OATHUser $user ): bool {
		return $this->module->isEnabled( $user );
	}

	public function getRequest(): WebRequest {
		return $this->authManager->getRequest();
	}

	public function canAuthenticate( OATHUser $user ): Status {
		if ( !$this->isEnabled( $user ) ) {
			return Status::newFatal(
				'oathauth-webauthn-error-module-not-enabled',
				$this->module->getName(),
				$user->getUser()->getName()
			);
		}

		return Status::newGood();
	}

	public function canRegister( OATHUser $user ): Status {
		if ( $this->userFactory->newFromUserIdentity( $user->getUser() )->isAllowed( 'oathauth-enable' ) ) {
			return Status::newGood();
		}

		return Status::newFatal(
			'oathauth-webauthn-error-cannot-register',
			$user->getUser()->getName()
		);
	}

	public function startAuthentication( OATHUser $user ): Status {
		$canAuthenticate = $this->canAuthenticate( $user );
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$user->getUser()->getName()} cannot authenticate"
			);
			return $canAuthenticate;
		}
		$authInfo = $this->getAuthInfo( $user );
		$this->setSessionData( $authInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $authInfo, 'json' ),
			'raw' => $authInfo
		] );
	}

	public function continueAuthentication(
		array $verificationData,
		OATHUser $user,
		?PublicKeyCredentialRequestOptions $authInfo = null
	): Status {
		$canAuthenticate = $this->canAuthenticate( $user );
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$user->getUser()->getName()} lost authenticate ability mid-request"
			);
			return $canAuthenticate;
		}

		$verificationData['authInfo'] = $authInfo ?? $this->getSessionData(
			PublicKeyCredentialRequestOptions::class
		);
		$this->clearSessionData();

		if ( $this->module->verify( $user, $verificationData ) ) {
			$this->logger->info(
				"User {$user->getUser()->getName()} logged in using WebAuthn"
			);
			return Status::newGood( $user );
		}
		$this->logger->warning(
			"Webauthn login failed for user {$user->getUser()->getName()}"
		);

		$this->oathLogger->logFailedVerification( $user->getUser() );

		return Status::newFatal( 'oathauth-webauthn-error-verification-failed' );
	}

	public function startRegistration( OATHUser $user, bool $passkeyMode = false ): Status {
		$canRegister = $this->canRegister( $user );
		if ( !$canRegister->isGood() ) {
			$this->logger->error(
				"User {$user->getUser()->getName()} cannot register a credential"
			);
			return $canRegister;
		}
		$registerInfo = $this->getRegisterInfo( $user, $passkeyMode );
		$this->setSessionData( $registerInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $registerInfo, 'json' ),
			'raw' => $registerInfo
		] );
	}

	public function continueRegistration(
		stdClass $credential, OATHUser $user, bool $passkeyMode = false
	): Status {
		$canRegister = $this->canRegister( $user );
		if ( !$canRegister->isGood() ) {
			$username = $user->getUser()->getName();
			$this->logger->error(
				"User $username lost registration ability mid-request"
			);
			return $canRegister;
		}

		$registerInfo = $this->getSessionData(
			PublicKeyCredentialCreationOptions::class
		);
		if ( $registerInfo === null ) {
			return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
		}

		$key = $this->module->newKey();
		$friendlyName = $credential->friendlyName;
		$data = FormatJson::encode( $credential );
		try {
			$registered = $key->verifyRegistration(
				$friendlyName,
				$data,
				$registerInfo,
				$user
			);
			if ( $passkeyMode ) {
				$key->setPasswordlessSupport( true );
			}
			if ( $registered ) {
				$this->userRepo->createKey(
					$user,
					$this->module,
					$key->jsonSerialize(),
					$this->getRequest()->getIP()
				);

				$this->recoveryCodesModule->ensureExistence(
					$user,
					$this->getKeyDataInSession( 'RecoveryCodeKeys' )
				);

				$this->clearSessionData();
				return Status::newGood();
			}
		} catch ( ErrorPageError $error ) {
			return Status::newFatal( $error->getMessageObject() );
		}
		return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
	}

	private function setSessionData( PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $data ) {
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		$this->authManager->setAuthenticationSessionData( static::SESSION_KEY,
			$serializer->serialize( $data, 'json' )
		);
	}

	private function clearSessionData() {
		$this->authManager->setAuthenticationSessionData( static::SESSION_KEY, null );
	}

	private function getSessionData(
		string $returnClass
	): PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions|null {
		$json = $this->authManager->getAuthenticationSessionData( static::SESSION_KEY );
		if ( $json === null ) {
			return null;
		}
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		return $serializer->deserialize( $json, $returnClass, 'json' );
	}

	/**
	 * Information to be sent to the client to start the authentication process
	 */
	protected function getAuthInfo( OATHUser $user ): PublicKeyCredentialRequestOptions {
		$keys = WebAuthn::getWebAuthnKeys( $user );
		$credentialDescriptors = [];
		foreach ( $keys as $key ) {
			$credentialDescriptors[] = new PublicKeyCredentialDescriptor(
				$key->getType(),
				$key->getAttestedCredentialData()->credentialId,
				$key->getTransports()
			);
		}

		return PublicKeyCredentialRequestOptions::create(
			random_bytes( 32 ),
			$this->serverId,
			$credentialDescriptors,
			PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			static::CLIENT_ACTION_TIMEOUT
		);
	}

	/**
	 * Information to be sent to the client to start the registration process
	 */
	protected function getRegisterInfo(
		OATHUser $user,
		bool $passkeyMode = false
	): PublicKeyCredentialCreationOptions {
		$serverName = $this->getServerName();
		$rpEntity = new PublicKeyCredentialRpEntity( $serverName, $this->serverId );

		// Exclude all already registered keys for user
		/** @var WebAuthnKey[] $webauthnKeys */
		$webauthnKeys = $user->getKeysForModule( WebAuthn::MODULE_ID );
		'@phan-var WebAuthnKey[] $webauthnKeys';
		$excludedPublicKeyDescriptors = array_map( static fn ( $key ) => new PublicKeyCredentialDescriptor(
			PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
			$key->getAttestedCredentialData()->credentialId
		), $webauthnKeys );

		$mwUser = $this->userFactory->newFromUserIdentity( $user->getUser() );
		$userHandle = $user->getUserHandle() ?? random_bytes( 64 );
		$realName = $mwUser->getRealName() ?: $mwUser->getName();
		$userEntity = new PublicKeyCredentialUserEntity(
			$mwUser->getName(),
			$userHandle,
			$realName
		);

		$publicKeyCredParametersList = [
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_ES256
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_ES512
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_EDDSA
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS1
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS256
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS512
			),
		];

		if ( $passkeyMode ) {
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
				residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
			);
		} else {
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM,
			);
		}

		return PublicKeyCredentialCreationOptions::create(
			$rpEntity,
			$userEntity,
			random_bytes( 32 ),
			$publicKeyCredParametersList,
			$authSelectorCriteria,
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			$excludedPublicKeyDescriptors,
			static::CLIENT_ACTION_TIMEOUT
		);
	}

	/**
	 * Get identifier for this server
	 */
	private function getServerId(): ?string {
		$rpId = $this->context->getConfig()->get( 'WebAuthnRelyingPartyID' );
		if ( $rpId && is_string( $rpId ) ) {
			return $rpId;
		}

		$server = $this->context->getConfig()->get( 'Server' );
		$serverBits = $this->urlUtils->parse( $server );
		if ( $serverBits !== null ) {
			return $serverBits['host'];
		}

		return null;
	}

	/**
	 * Get the name for this server
	 */
	private function getServerName(): string {
		$serverName = $this->context->getConfig()->get( 'WebAuthnRelyingPartyName' );
		if ( $serverName && is_string( $serverName ) ) {
			return $serverName;
		}
		if ( $this->context->getConfig()->has( 'Sitename' ) ) {
			return $this->context->getConfig()->get( 'Sitename' );
		}

		return WikiMap::getCurrentWikiId();
	}
}
