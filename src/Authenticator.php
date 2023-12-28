<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\WebAuthn;

use Cose\Algorithms;
use FormatJson;
use IContextSource;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MWException;
use Psr\Log\LoggerInterface;
use RequestContext;
use stdClass;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
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

	// 60 sec
	private const CLIENT_ACTION_TIMEOUT = 60000;

	/**
	 * @var string
	 */
	protected $serverId = '';

	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;

	/**
	 * @var WebAuthn
	 */
	protected $module;

	/**
	 * @var OATHUser
	 */
	protected $oathUser;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var WebRequest
	 */
	protected $request;

	/**
	 * @var IContextSource
	 */
	protected $context;

	/**
	 * @param User $user
	 * @param WebRequest|null $request
	 * @return Authenticator
	 * @throws ConfigException
	 * @throws MWException
	 */
	public static function factory( $user, $request = null ) {
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = MediaWikiServices::getInstance()->getService( 'OATHAuthModuleRegistry' );
		/** @var OATHUserRepository $userRepo */
		$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		/** @var WebAuthn $module */
		$module = $moduleRegistry->getModuleByKey( 'webauthn' );
		$oathUser = $userRepo->findByUser( $user );
		$context = RequestContext::getMain();
		$logger = LoggerFactory::getInstance( 'authentication' );
		if ( $request === null ) {
			$request = RequestContext::getMain()->getRequest();
		}

		return new static(
			$userRepo,
			$module,
			$oathUser,
			$context,
			$logger,
			$request
		);
	}

	/**
	 * @param OATHUserRepository $userRepo
	 * @param IModule $module
	 * @param OATHUser|null $oathUser
	 * @param IContextSource $context
	 * @param LoggerInterface $logger
	 * @param WebRequest $request
	 * @throws ConfigException
	 */
	protected function __construct( $userRepo, $module, $oathUser, $context, $logger, $request ) {
		$this->userRepo = $userRepo;
		$this->module = $module;
		$this->oathUser = $oathUser;
		$this->context = $context;
		$this->logger = $logger;
		$this->request = $request;
		$this->serverId = $this->getServerId();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		return $this->module->isEnabled( $this->oathUser );
	}

	/**
	 * @return Status
	 */
	public function canAuthenticate() {
		if ( !$this->isEnabled() ) {
			return Status::newFatal(
				'webauthn-error-module-not-enabled',
				$this->module->getName(),
				$this->oathUser->getUser()->getName()
			);
		}

		return Status::newGood();
	}

	/**
	 * @return Status
	 */
	public function canRegister() {
		if ( $this->oathUser->getUser()->isAllowed( 'oathauth-enable' ) ) {
			return Status::newGood();
		}

		return Status::newFatal(
			'webauthn-error-cannot-register',
			$this->oathUser->getUser()->getName()
		);
	}

	/**
	 * @return Status
	 * @throws MWException
	 */
	public function startAuthentication() {
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

	/**
	 * @param array $verificationData
	 * @param PublicKeyCredentialRequestOptions|null $authInfo
	 * @return Status
	 */
	public function continueAuthentication( array $verificationData, $authInfo = null ) {
		$canAuthenticate = $this->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$this->oathUser->getUser()->getName()} lost authenticate ability mid-request"
			);
			return $canAuthenticate;
		}

		if ( $authInfo === null ) {
			$authInfo = $this->getSessionData(
				PublicKeyCredentialRequestOptions::class
			);
		}
		$verificationData['authInfo'] = $authInfo;
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
	 * @return Status
	 * @throws ConfigException
	 */
	public function startRegistration() {
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
	public function continueRegistration( $credential, $registerInfo = null ) {
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
		if ( !( $key instanceof WebAuthnKey ) ) {
			$this->logger->error(
				'New Webauthn key registration failed due to invalid key instance'
			);
			return Status::newFatal( 'webauthn-error-invalid-new-key' );
		}

		$friendlyName = $credential->friendlyName;
		$data = FormatJson::encode( $credential );
		try {
			$registered = $key->verifyRegistration(
				$friendlyName,
				$data,
				$registerInfo,
				$this->oathUser
			);
			if ( $registered ) {
				$maxKeysPerUser = $this->module->getConfig()->get( 'maxKeysPerUser' );
				if ( count( $this->oathUser->getKeys() ) >= (int)$maxKeysPerUser ) {
					return Status::newFatal(
						wfMessage( 'webauthn-error-max-keys-reached', $maxKeysPerUser )
					);
				}

				// If user has another module already activated, clear all keys for than module
				if ( !$this->oathUser->getModule() instanceof WebAuthn ) {
					$this->oathUser->clearAllKeys();
				}

				$this->userRepo->createKey(
					$this->oathUser,
					$this->module,
					$key->jsonSerialize(),
					$this->request->getIP()
				);

				$this->clearSessionData();
				return Status::newGood();
			}
		} catch ( MWException $exception ) {
			return Status::newFatal( $exception->getMessage() );
		}
		return Status::newFatal( 'webauthn-error-registration-failed' );
	}

	/**
	 * @param PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $data
	 */
	private function setSessionData( $data ) {
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

	/**
	 * @param string $returnClass
	 * @return PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions|null
	 */
	private function getSessionData( $returnClass ) {
		$authData = $this->request->getSession()->getSecret( 'authData' );
		if ( !is_array( $authData ) ) {
			return null;
		}
		if ( array_key_exists( static::SESSION_KEY, $authData ) ) {
			$json = $authData[static::SESSION_KEY];
			$factory = [ $returnClass, 'createFromString' ];
			if ( !is_callable( $factory ) ) {
				return null;
			}
			return call_user_func_array( $factory, [ $json ] );
		}
		return null;
	}

	/**
	 * Information to be sent to the client to start the authentication process
	 *
	 * @return PublicKeyCredentialRequestOptions
	 * @throws MWException
	 */
	protected function getAuthInfo() {
		$keys = $this->oathUser->getKeys();
		$credentialDescriptors = [];
		foreach ( $keys as $key ) {
			if ( !$key instanceof WebAuthnKey ) {
				throw new MWException( 'webauthn-key-type-missmatch' );
			}
			$credentialDescriptors[$key->getFriendlyName()] = new PublicKeyCredentialDescriptor(
				$key->getType(),
				$key->getAttestedCredentialData()->getCredentialId(),
				$key->getTransports()
			);
		}

		return new PublicKeyCredentialRequestOptions(
			random_bytes( 32 ),
			static::CLIENT_ACTION_TIMEOUT,
			$this->serverId,
			$credentialDescriptors,
			PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			new AuthenticationExtensionsClientInputs()
		);
	}

	/**
	 * Information to be sent to the client to start the registration process
	 *
	 * @return PublicKeyCredentialCreationOptions
	 * @throws ConfigException
	 */
	protected function getRegisterInfo() {
		$serverName = $this->getServerName();
		$rpEntity = new PublicKeyCredentialRpEntity( $serverName, $this->serverId );

		$mwUser = $this->context->getUser();

		// Exclude all already registered keys for user
		$excludedPublicKeyDescriptors = [];

		// If the user already has webauthn enabled, and is just registering another key,
		// make sure userHandle remains the same across keys
		$userHandle = null;

		foreach ( $this->oathUser->getKeys() as $key ) {
			if ( !( $key instanceof WebAuthnKey ) ) {
				continue;
			}

			$userHandle = $key->getUserHandle();

			$excludedPublicKeyDescriptors[] = new PublicKeyCredentialDescriptor(
				PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
				$key->getAttestedCredentialData()->getCredentialId()
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
			)
		];

		return new PublicKeyCredentialCreationOptions(
			$rpEntity,
			$userEntity,
			random_bytes( 32 ),
			$publicKeyCredParametersList,
			static::CLIENT_ACTION_TIMEOUT,
			$excludedPublicKeyDescriptors,
			new AuthenticatorSelectionCriteria(),
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			new AuthenticationExtensionsClientInputs()
		);
	}

	/**
	 * Get identifier for this server
	 *
	 * @return string|null
	 */
	private function getServerId() {
		$rpId = $this->context->getConfig()->get( 'WebAuthnRelyingPartyID' );
		if ( $rpId && is_string( $rpId ) ) {
			return $rpId;
		}

		$server = $this->context->getConfig()->get( 'Server' );
		$serverBits = wfParseUrl( $server );
		if ( $serverBits !== false ) {
			return $serverBits['host'];
		}

		return null;
	}

	/**
	 * Get name for this server
	 *
	 * @return string
	 */
	private function getServerName() {
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
