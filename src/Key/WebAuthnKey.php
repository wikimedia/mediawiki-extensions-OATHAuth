<?php

namespace MediaWiki\Extension\WebAuthn\Key;

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

use CBOR\Decoder;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\Tag\TagObjectManager;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\RS1;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS512;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\WebAuthn\Request;
use MediaWiki\Extension\WebAuthn\WebAuthnCredentialRepository;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RequestContext;
use MWException;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestedCredentialData;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Throwable;
use Webauthn\TrustPath\EmptyTrustPath;
use PHP_CodeSniffer\Exceptions\RuntimeException;

/**
 * This holds the information on user's private key
 * and does the actual authentication of data passed
 * by the client with data saved on server
 */
class WebAuthnKey implements IAuthKey {
	const MODE_CREATE = 'webauthn.create';
	const MODE_AUTHENTICATE = 'webauthn.authenticate';

	/**
	 * User handle represents unique ID of the user.
	 * It is a randomly generated 64-bit string.
	 * It can change if user disables and then re-enables
	 * webauthn module, but MUST be same for each key
	 * if user has multiple keys set at once
	 *
	 * @var string
	 */
	protected $userHandle;

	/**
	 * @var AttestedCredentialData
	 */
	protected $attestedCredentialData;

	/**
	 * @var string
	 */
	protected $friendlyName;

	/**
	 * @var int
	 */
	protected $signCounter = 0;

	/**
	 * @var string
	 */
	protected $mode;

	/**
	 * @var string
	 */
	protected $credentialType = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

	/**
	 * @var array
	 */
	protected $credentialTransports = [
		PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_USB,
		PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_NFC,
		PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_BLE,
		PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_INTERNAL
	];

	/**
	 * @var string
	 */
	protected $credentialAttestationType = '';

	/**
	 * @var string
	 */
	protected $credentialTrustPath = [];

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var RequestContext
	 */
	protected $context;

	/**
	 * Create new empty key instance
	 * Used for new keys
	 *
	 * @return WebAuthnKey
	 */
	public static function newKey() {
		return new static(
			static::MODE_CREATE,
			RequestContext::getMain()
		);
	}

	/**
	 * Create new key instance from given data
	 * Used for existing keys
	 *
	 * @param array $data
	 * @return WebAuthnKey
	 */
	public static function newFromData( $data ) {
		$key = new static(
			static::MODE_AUTHENTICATE,
			RequestContext::getMain()
		);
		$key->setDataFromEncodedDBData( $data );
		return $key;
	}

	/**
	 *
	 * @param string $mode
	 * @param RequestContext $context
	 */
	protected function __construct( $mode, $context ) {
		$this->mode = $mode;
		$this->context = $context;

		// There is not documentation on what this trust path is
		// and how it should be used
		$this->credentialTrustPath = new EmptyTrustPath();

		$this->logger = LoggerFactory::getInstance( 'authentication' );
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			"userHandle" => base64_encode( $this->userHandle ),
			"publicKeyCredentialId" => base64_encode( $this->attestedCredentialData->getCredentialId() ),
			"credentialPublicKey" => base64_encode(
				$this->attestedCredentialData->getCredentialPublicKey()
			),
			"aaguid" => $this->attestedCredentialData->getAaguid()->toString(),
			"friendlyName" => $this->friendlyName,
			"counter" => $this->signCounter,
			"type" => $this->credentialType,
			"transports" => $this->credentialTransports,
			"attestationType" => $this->credentialAttestationType,
			"trustPath" => $this->credentialTrustPath
		];
	}

	/**
	 * Set the key up with data coming from DB
	 *
	 * @param array $data
	 * @return void
	 */
	public function setDataFromEncodedDBData( $data ) {
		$this->userHandle = base64_decode( $data['userHandle'] );
		$this->friendlyName = $data['friendlyName'];
		$this->signCounter = $data['counter'];
		$this->attestedCredentialData = new AttestedCredentialData(
			Uuid::fromString( $data['aaguid'] ),
			base64_decode( $data['publicKeyCredentialId'] ),
			base64_decode( $data['credentialPublicKey'] )
		);
	}

	/**
	 * @return string
	 */
	public function getFriendlyName() {
		return $this->friendlyName;
	}

	/**
	 * Sets friendly name
	 * If value exists, it will be appended with unique suffix
	 *
	 * @param string $name
	 * @return void
	 */
	public function setFriendyName( $name ) {
		$this->friendlyName = trim( $name );
		$this->checkFriendlyName();
	}

	/**
	 * @return AttestedCredentialData
	 */
	public function getAttestedCredentialData() {
		return $this->attestedCredentialData;
	}

	/**
	 * @return string
	 */
	public function getUserHandle() {
		return $this->userHandle;
	}

	/**
	 * @return int
	 */
	public function getSignCounter() {
		return $this->signCounter;
	}

	/**
	 * @param int $newCount
	 * @return void
	 */
	public function setSignCounter( $newCount ) {
		$this->signCounter = $newCount;
	}

	/**
	 * @param array $data
	 * @param OATHUser $user
	 * @return bool
	 * @throws MWException
	 */
	public function verify( $data, OATHUser $user ) {
		if ( $this->mode !== static::MODE_AUTHENTICATE ) {
			$this->logger->error( sprintf(
				"Authentication attempt by user %s while not in authenticate mode",
				$user->getUser()->getName()
			) );
			throw new MWException( 'webauthn-mode-invalid' );
		}
		return $this->authenticationCeremony(
			$data['credential'],
			$data['authInfo']
		);
	}

	/**
	 * @param string $friendlyName
	 * @param string $data
	 * @param PublicKeyCredentialCreationOptions $registrationObject
	 * @param OATHUser $user
	 * @return bool
	 * @throws MWException
	 */
	public function verifyRegistration( $friendlyName, $data,
		$registrationObject, OATHUser $user ) {
		if ( $this->mode !== static::MODE_CREATE ) {
			$this->logger->error( sprintf(
				"Registration attempt by user %s while not in register mode",
				$user->getUser()->getName()
			) );
			throw new MWException( 'webauthn-mode-invalid' );
		}
		$this->setFriendyName( $friendlyName );
		return $this->registrationCeremony( $data, $registrationObject, $user );
	}

	/**
	 * Get the credential type
	 *
	 * @return string
	 */
	public function getType() {
		return $this->credentialType;
	}

	/**
	 * Get transports available for the credential assigned to this key
	 *
	 * @return array
	 */
	public function getTransports() {
		return $this->credentialTransports;
	}

	/**
	 * This will not actually work very well, as third same key will
	 * be name Key #2 #3, should be refactored once we have defined what this
	 * behaviour should be, for now its just a safety feature
	 *
	 * @param int $inc
	 * @return void
	 */
	private function checkFriendlyName( $inc = 2 ) {
		$credRepo = new WebAuthnCredentialRepository();
		$names = $credRepo->getFriendlyNamesForMWUser( $this->context->getUser(), true );
		if ( in_array( strtolower( $this->friendlyName ), $names ) ) {
			$this->friendlyName .= " #$inc";
			$inc++;
			$this->checkFriendlyName( $inc );
		}
	}

	/**
	 * @param string $data
	 * @param PublicKeyCredentialCreationOptions $registrationObject
	 * @param OATHUser $user
	 * @return bool
	 */
	private function registrationCeremony( $data, $registrationObject, $user ) {
		$publicKeyCredentialCreationOptions = $registrationObject;

		$otherObjectManager = new OtherObjectManager();
		$tagObjectManager = new TagObjectManager();
		$decoder = new Decoder( $tagObjectManager, $otherObjectManager );
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$attestationStatementSupportManager->add( new FidoU2FAttestationStatementSupport(
			$decoder
		) );
		$attestationStatementSupportManager->add( new PackedAttestationStatementSupport(
			$decoder,
			new Manager()
		) );
		$attestationObjectLoader = new AttestationObjectLoader(
			$attestationStatementSupportManager,
			$decoder
		);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			$attestationObjectLoader,
			$decoder
		);
		$credentialRepository = new WebAuthnCredentialRepository();
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();
		$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
			$attestationStatementSupportManager,
			$credentialRepository,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler
		);

		try {
			$publicKeyCredential = $publicKeyCredentialLoader->load( $data );
			$response = $publicKeyCredential->getResponse();
			if ( !$response instanceof AuthenticatorAttestationResponse ) {
				throw new MWException( 'webauthn-invalid-response' );
			}

			$request = Request::newFromWebRequest( $this->context->getRequest() );

			$authenticatorAttestationResponseValidator->check(
				$response,
				$publicKeyCredentialCreationOptions,
				$request
			);
		} catch ( Throwable $ex ) {
			$this->logger->warning(
				"WebAuthn key registration failed due to: {$ex->getMessage()}"
			);
			return false;
		}

		$attestedCredentialData = null;
		if ( $response->getAttestationObject()->getAuthData()->hasAttestedCredentialData() ) {
			$this->userHandle = $registrationObject->getUser()->getId();
			$this->attestedCredentialData = $response->getAttestationObject()
				->getAuthData()->getAttestedCredentialData();
			$this->signCounter = $response->getAttestationObject()->getAuthData()->getSignCount();

			$this->logger->info(
				"User {$user->getUser()->getName()} registered new WebAuthn key"
			);
			return true;
		}
		$this->logger->warning(
			'WebAuthn key registration failed due to: No AttestedCredentialData in the response'
		);
		return false;
	}

	/**
	 * @param array $data
	 * @param PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions
	 * @return bool
	 */
	private function authenticationCeremony( $data, $publicKeyCredentialRequestOptions ) {
		$otherObjectManager = new OtherObjectManager();
		$tagObjectManager = new TagObjectManager();
		$decoder = new Decoder( $tagObjectManager, $otherObjectManager );

		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$attestationStatementSupportManager->add(
			new FidoU2FAttestationStatementSupport( $decoder )
		);
		$attestationStatementSupportManager->add(
			new AndroidKeyAttestationStatementSupport( $decoder )
		);

		$attestationObjectLoader = new AttestationObjectLoader(
			$attestationStatementSupportManager,
			$decoder
		);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			$attestationObjectLoader,
			$decoder
		);
		$coseAlgorithmManager = new Manager();
		$coseAlgorithmManager->add( new ES256() );
		$coseAlgorithmManager->add( new ES512() );
		$coseAlgorithmManager->add( new EdDSA() );
		$coseAlgorithmManager->add( new RS1() );
		$coseAlgorithmManager->add( new RS256() );
		$coseAlgorithmManager->add( new RS512() );

		$credentialRepository = new WebAuthnCredentialRepository();
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();
		$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
			$credentialRepository,
			$decoder,
			$tokenBindingHandler,
			new ExtensionOutputCheckerHandler(),
			$coseAlgorithmManager
		);

		try {
			if ( !( $publicKeyCredentialRequestOptions
				instanceof PublicKeyCredentialRequestOptions ) ) {
				throw new RuntimeException( 'Authentication data is not set' );
			}
			$publicKeyCredential = $publicKeyCredentialLoader->load( $data );
			$response = $publicKeyCredential->getResponse();

			// Check if the response is an Authenticator Assertion Response
			if ( !$response instanceof AuthenticatorAssertionResponse ) {
				throw new RuntimeException( 'Not an authenticator assertion response' );
			}

			$request = Request::newFromWebRequest( $this->context->getRequest() );
			// Check the response against the attestation request
			$authenticatorAssertionResponseValidator->check(
				$publicKeyCredential->getRawId(),
				$publicKeyCredential->getResponse(),
				$publicKeyCredentialRequestOptions,
				$request,
				$this->userHandle
			);
			return true;
		} catch ( Throwable $ex ) {
			$this->logger->warning(
				"WebAuthn authentication failed due to: {$ex->getMessage()}"
			);
			return false;
		}
	}
}
