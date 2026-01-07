<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\WebAuthn;

use Cose\Algorithms;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
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
class Authenticator {
	private const SESSION_KEY = 'webauthn_session_data';

	/**
	 * 60 seconds
	 */
	private const CLIENT_ACTION_TIMEOUT = 60000;

	protected ?string $serverId;

	/**
	 * @throws ConfigException
	 * @throws MWException
	 */
	public static function factory( User $user, ?WebRequest $request = null, bool $passkeyMode = false ): self {
		$services = MediaWikiServices::getInstance();
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = $services->getService( 'OATHAuthModuleRegistry' );
		/** @var OATHUserRepository $userRepo */
		$userRepo = $services->getService( 'OATHUserRepository' );

		return new static(
			$userRepo,
			$moduleRegistry->getModuleByKey( 'webauthn' ),
			$userRepo->findByUser( $user ),
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
		protected OATHUser $oathUser,
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

	public function canAuthenticate(): Status {
		if ( !$this->isEnabled() ) {
			return Status::newFatal(
				'webauthn-error-module-not-enabled',
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
			'webauthn-error-cannot-register',
			$this->oathUser->getUser()->getName()
		);
	}

	/**
	 * @throws MWException
	 */
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

		return Status::newGood( [
			'json' => json_encode(
				$authInfo,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			),
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
		return Status::newFatal( 'webauthn-error-verification-failed' );
	}

	/**
	 * @throws ConfigException
	 */
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

		return Status::newGood( [
			'json' => json_encode(
				$registerInfo,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			),
			'raw' => $registerInfo
		] );
	}

	/**
	 * @param stdClass $credential
	 * @param PublicKeyCredentialCreationOptions|null $registerInfo
	 * @return Status
	 * @throws ConfigException
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
				return Status::newFatal( 'webauthn-error-registration-failed' );
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
			if ( $this->inPasskeyMode() ) {
				$key->setPasswordlessSupport( true );
			}
			if ( $registered ) {
				$this->userRepo->createKey(
					$this->oathUser,
					$this->module,
					$key->jsonSerialize(),
					$this->request->getIP()
				);

				$this->clearSessionData();
				return Status::newGood();
			}
		} catch ( ErrorPageError $error ) {
			return Status::newFatal( $error->getMessageObject() );
		}
		return Status::newFatal( 'webauthn-error-registration-failed' );
	}

	private function inPasskeyMode(): bool {
		return $this->passkeyMode && $this->context->getConfig()->get( 'OATHNewPasskeyFeatures' );
	}

	private function setSessionData( PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $data ) {
		$session = $this->request->getSession();
		$authData = $session->getSecret( 'authData' );
		if ( !is_array( $authData ) ) {
			$authData = [];
		}
		$authData[static::SESSION_KEY] = FormatJson::encode( $data );
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
		if ( !is_array( $authData ) ) {
			return null;
		}
		if ( array_key_exists( static::SESSION_KEY, $authData ) ) {
			$json = $authData[static::SESSION_KEY];
			$data = json_decode( $json, associative: true, flags: JSON_THROW_ON_ERROR );
			// FIXME webauthn-lib uses different encoding to serialize (base64url unpadded)
			//   and unserialize (base64) the challenge and user.id and JSON fields :/
			/** @var array $data */'@phan-var array{challenge:string} $data';
			$data['challenge'] = Base64::encode( Base64UrlSafe::decode( $data['challenge'] ) );
			if ( $returnClass === PublicKeyCredentialCreationOptions::class ) {
				/** @var array $data */'@phan-var array{challenge:string,user:array{id:string}} $data';
				$data['user']['id'] = Base64::encode( Base64UrlSafe::decode( $data['user']['id'] ) );
			}
			$factory = match ( $returnClass ) {
				PublicKeyCredentialRequestOptions::class => PublicKeyCredentialRequestOptions::createFromArray( ... ),
				PublicKeyCredentialCreationOptions::class => PublicKeyCredentialCreationOptions::createFromArray( ... ),
			};
			return $factory( $data );
		}
		return null;
	}

	/**
	 * Information to be sent to the client to start the authentication process
	 * @throws MWException
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
	 * @throws ConfigException
	 */
	protected function getRegisterInfo(): PublicKeyCredentialCreationOptions {
		$serverName = $this->getServerName();
		$rpEntity = new PublicKeyCredentialRpEntity( $serverName, $this->serverId );

		$mwUser = $this->context->getUser();

		// Exclude all already registered keys for user
		$excludedPublicKeyDescriptors = [];

		// If the user already has webauthn enabled, and is just registering another key,
		// make sure userHandle remains the same across keys
		$userHandle = null;

		foreach ( WebAuthn::getWebAuthnKeys( $this->oathUser ) as $key ) {
			$userHandle = $key->getUserHandle();

			$excludedPublicKeyDescriptors[] = new PublicKeyCredentialDescriptor(
				PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
				$key->getAttestedCredentialData()->credentialId
			);
		}

		if ( !$userHandle ) {
			$userHandle = random_bytes( 64 );
		}

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

		if ( $this->inPasskeyMode() ) {
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
				residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
			);
		} else {
			$authenticatorAttachment = $this->context->getConfig()->get( 'WebAuthnLimitPasskeysToRoaming' )
				? AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM
				: null;
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				$authenticatorAttachment,
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
