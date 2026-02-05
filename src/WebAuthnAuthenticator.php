<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

use Cose\Algorithms;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
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

	public static function factory( User $user, ?WebRequest $request = null, bool $passkeyMode = false ): self {
		$services = MediaWikiServices::getInstance();
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = $services->getService( 'OATHAuthModuleRegistry' );
		/** @var OATHUserRepository $userRepo */
		$userRepo = $services->getService( 'OATHUserRepository' );
		/** @var OATHAuthLogger $oathLogger */
		$oathLogger = $services->getService( 'OATHAuthLogger' );

		/** @var WebAuthn $webAuthn */
		$webAuthn = $moduleRegistry->getModuleByKey( WebAuthn::MODULE_ID );
		/** @var RecoveryCodes $recovery */
		$recovery = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		return new static(
			$userRepo,
			$webAuthn,
			$recovery,
			$userRepo->findByUser( $user ),
			$oathLogger,
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' ),
			$request ?? RequestContext::getMain()->getRequest(),
			$services->getUrlUtils(),
			$passkeyMode
		);
	}

	protected function __construct(
		protected OATHUserRepository $userRepo,
		protected WebAuthn $module,
		protected RecoveryCodes $recoveryCodesModule,
		protected OATHUser $oathUser,
		protected OATHAuthLogger $oathLogger,
		protected IContextSource $context,
		protected LoggerInterface $logger,
		protected WebRequest $request,
		private readonly UrlUtils $urlUtils,
		protected bool $passkeyMode
	) {
		$this->serverId = $this->getServerId();
	}

	public function isEnabled(): bool {
		return $this->module->isEnabled( $this->oathUser );
	}

	public function getRequest(): WebRequest {
		return $this->request;
	}

	public function canAuthenticate(): Status {
		if ( !$this->isEnabled() ) {
			return Status::newFatal(
				'oathauth-webauthn-error-module-not-enabled',
				$this->module->getName(),
				$this->oathUser->getUser()->getName()
			);
		}

		return Status::newGood();
	}

	public function canRegister(): Status {
		if ( $this->context->getUser()->isAllowed( 'oathauth-enable' ) ) {
			return Status::newGood();
		}

		return Status::newFatal(
			'oathauth-webauthn-error-cannot-register',
			$this->oathUser->getUser()->getName()
		);
	}

	public function startAuthentication(): Status {
		$canAuthenticate = $this->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$this->oathUser->getUser()->getName()} cannot authenticate"
			);
			return $canAuthenticate;
		}
		$authInfo = $this->getAuthInfo();
		$this->setSessionData( $authInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $authInfo, 'json' ),
			'raw' => $authInfo
		] );
	}

	public function continueAuthentication(
		array $verificationData,
		?PublicKeyCredentialRequestOptions $authInfo = null
	): Status {
		$canAuthenticate = $this->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$this->oathUser->getUser()->getName()} lost authenticate ability mid-request"
			);
			return $canAuthenticate;
		}

		$verificationData['authInfo'] = $authInfo ?? $this->getSessionData(
			PublicKeyCredentialRequestOptions::class
		);
		$this->clearSessionData();

		if ( $this->module->verify( $this->oathUser, $verificationData ) ) {
			$this->logger->info(
				"User {$this->oathUser->getUser()->getName()} logged in using WebAuthn"
			);
			return Status::newGood( $this->oathUser );
		}
		$this->logger->warning(
			"Webauthn login failed for user {$this->oathUser->getUser()->getName()}"
		);

		$this->oathLogger->logFailedVerification( $this->oathUser->getUser() );

		return Status::newFatal( 'oathauth-webauthn-error-verification-failed' );
	}

	public function startRegistration(): Status {
		$canRegister = $this->canRegister();
		if ( !$canRegister->isGood() ) {
			$this->logger->error(
				"User {$this->oathUser->getUser()->getName()} cannot register a credential"
			);
			return $canRegister;
		}
		$registerInfo = $this->getRegisterInfo();
		$this->setSessionData( $registerInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $registerInfo, 'json' ),
			'raw' => $registerInfo
		] );
	}

	/**
	 * @param stdClass $credential
	 * @param PublicKeyCredentialCreationOptions|null $registerInfo
	 * @return Status
	 */
	public function continueRegistration( $credential, $registerInfo = null ): Status {
		$canRegister = $this->canRegister();
		if ( !$canRegister->isGood() ) {
			$username = $this->oathUser->getUser()->getName();
			$this->logger->error(
				"User $username lost registration ability mid-request"
			);
			return $canRegister;
		}

		if ( $registerInfo === null ) {
			$registerInfo = $this->getSessionData(
				PublicKeyCredentialCreationOptions::class
			);
			if ( $registerInfo === null ) {
				return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
			}
		}

		$key = $this->module->newKey();
		$friendlyName = $credential->friendlyName;
		$data = FormatJson::encode( $credential );
		try {
			$registered = $key->verifyRegistration(
				$friendlyName,
				$data,
				$registerInfo,
				$this->oathUser
			);
			if ( $this->passkeyMode ) {
				$key->setPasswordlessSupport( true );
			}
			if ( $registered ) {
				$this->userRepo->createKey(
					$this->oathUser,
					$this->module,
					$key->jsonSerialize(),
					$this->request->getIP()
				);

				$this->recoveryCodesModule->ensureExistence(
					$this->oathUser,
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
		$session = $this->request->getSession();
		$authData = $session->getSecret( 'authData' );
		if ( !is_array( $authData ) ) {
			$authData = [];
		}

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		$authData[static::SESSION_KEY] = $serializer->serialize( $data, 'json' );
		$session->setSecret( 'authData', $authData );
	}

	private function clearSessionData() {
		$session = $this->request->getSession();
		$authData = $session->getSecret( 'authData' );
		if ( is_array( $authData ) && array_key_exists( static::SESSION_KEY, $authData ) ) {
			unset( $authData[static::SESSION_KEY] );
			$session->setSecret( 'authData', $authData );
		}
	}

	private function getSessionData(
		string $returnClass
	): PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions|null {
		$authData = $this->request->getSession()->getSecret( 'authData' );
		if ( !is_array( $authData ) || !array_key_exists( static::SESSION_KEY, $authData ) ) {
			return null;
		}

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return $serializer->deserialize(
			$authData[static::SESSION_KEY],
			$returnClass,
			'json',
		);
	}

	/**
	 * Information to be sent to the client to start the authentication process
	 */
	protected function getAuthInfo(): PublicKeyCredentialRequestOptions {
		$keys = WebAuthn::getWebAuthnKeys( $this->oathUser );
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
	protected function getRegisterInfo(): PublicKeyCredentialCreationOptions {
		$serverName = $this->getServerName();
		$rpEntity = new PublicKeyCredentialRpEntity( $serverName, $this->serverId );

		$mwUser = $this->context->getUser();

		// Exclude all already registered keys for user
		/** @var WebAuthnKey[] $webauthnKeys */
		$webauthnKeys = $this->oathUser->getKeysForModule( WebAuthn::MODULE_ID );
		'@phan-var WebAuthnKey[] $webauthnKeys';
		$excludedPublicKeyDescriptors = array_map( static fn ( $key ) => new PublicKeyCredentialDescriptor(
			PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
			$key->getAttestedCredentialData()->credentialId
		), $webauthnKeys );

		$userHandle = $this->oathUser->getUserHandle() ?? random_bytes( 64 );
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

		if ( $this->passkeyMode ) {
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
