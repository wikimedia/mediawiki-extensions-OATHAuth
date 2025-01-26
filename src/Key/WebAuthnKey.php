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

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\RS1;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS512;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use MediaWiki\Extension\WebAuthn\Request;
use MediaWiki\Extension\WebAuthn\WebAuthnCredentialRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;
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
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\TrustPath\TrustPath;

/**
 * This holds the information on user's private key
 * and does the actual authentication of data passed
 * by the client with data saved on server
 */
class WebAuthnKey implements IAuthKey {
	private const MODE_CREATE = 'webauthn.create';
	private const MODE_AUTHENTICATE = 'webauthn.authenticate';

	/** @var int|null */
	private ?int $id;

	/**
	 * User handle represents unique ID of the user.
	 *
	 * It is a randomly generated 64-bit string.
	 *
	 * It can change if the user disables and then re-enables
	 * webauthn module, but MUST be the same for each key
	 * if the user has multiple keys set at once.
	 *
	 * @var string
	 */
	protected $userHandle;

	protected AttestedCredentialData $attestedCredentialData;

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
	 * @var TrustPath
	 */
	protected $credentialTrustPath;

	protected LoggerInterface $logger;

	protected RequestContext $context;

	/**
	 * Create a new empty key instance.
	 *
	 * Used for new keys.
	 *
	 * @return WebAuthnKey
	 */
	public static function newKey() {
		return new static(
			null,
			static::MODE_CREATE,
			RequestContext::getMain()
		);
	}

	/**
	 * Create a new key instance from given data.
	 *
	 * Used for existing keys.
	 *
	 * @param array $data
	 * @return WebAuthnKey
	 */
	public static function newFromData( $data ) {
		$key = new static(
			$data['id'] ?? null,
			static::MODE_AUTHENTICATE,
			RequestContext::getMain()
		);
		$key->setDataFromEncodedDBData( $data );
		return $key;
	}

	/**
	 * @param int|null $id
	 * @param string $mode
	 * @param RequestContext $context
	 */
	protected function __construct( ?int $id, $mode, $context ) {
		$this->id = $id;
		$this->mode = $mode;
		$this->context = $context;

		// There is no documentation on what this trust path is
		// and how it should be used
		$this->credentialTrustPath = new EmptyTrustPath();

		$this->logger = LoggerFactory::getInstance( 'authentication' );
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			"userHandle" => base64_encode( $this->userHandle ),
			"publicKeyCredentialId" => base64_encode( $this->attestedCredentialData->getCredentialId() ),
			"credentialPublicKey" => base64_encode(
				(string)$this->attestedCredentialData->getCredentialPublicKey()
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
	 * @return int|null
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getFriendlyName() {
		return $this->friendlyName;
	}

	/**
	 * Sets friendly name
	 * If value exists, it will be appended with a unique suffix
	 *
	 * @param string $name
	 */
	private function setFriendlyName( $name ) {
		$this->friendlyName = trim( $name );
		$this->checkFriendlyName();
	}

	public function getAttestedCredentialData(): AttestedCredentialData {
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
	 */
	public function setSignCounter( $newCount ) {
		$this->signCounter = $newCount;
	}

	/**
	 * @return string
	 */
	public function getAttestationType() {
		return $this->credentialAttestationType;
	}

	public function getTrustPath(): TrustPath {
		return $this->credentialTrustPath;
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
			$data['authInfo'],
			$user
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
	public function verifyRegistration(
		$friendlyName,
		$data,
		$registrationObject,
		OATHUser $user
	) {
		if ( $this->mode !== static::MODE_CREATE ) {
			$this->logger->error( sprintf(
				"Registration attempt by user %s while not in register mode",
				$user->getUser()->getName()
			) );
			throw new MWException( 'webauthn-mode-invalid' );
		}
		$this->setFriendlyName( $friendlyName );
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
	 * @throws MWException
	 */
	private function checkFriendlyName() {
		/** @var OATHUserRepository $repo */
		$repo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$oauthUser = $repo->findByUser( $this->context->getUser() );
		$credRepo = new WebAuthnCredentialRepository( $oauthUser );
		$names = $credRepo->getFriendlyNames( true );
		$this->checkFriendlyNameInternal( $names );
	}

	/**
	 * This will not actually work very well, as third same key will
	 * be named "Key #2 #3".
	 *
	 * It should be refactored once we have defined what this
	 * behaviour should be, for now it's just a safety feature.
	 *
	 * @param array $names Existing keys friendly names
	 * @param int $inc
	 * @return void
	 */
	private function checkFriendlyNameInternal( $names, $inc = 2 ) {
		if ( in_array( strtolower( $this->friendlyName ), $names ) ) {
			$this->friendlyName .= " #$inc";
			$inc++;
			$this->checkFriendlyNameInternal( $names, $inc );
		}
	}

	/**
	 * @param string $data
	 * @param PublicKeyCredentialCreationOptions $registrationObject
	 * @param OATHUser $user
	 * @return bool
	 */
	private function registrationCeremony( $data, $registrationObject, $user ) {
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$attestationStatementSupportManager->add( new FidoU2FAttestationStatementSupport() );
		$attestationStatementSupportManager->add( new PackedAttestationStatementSupport(
			new Manager()
		) );
		$attestationObjectLoader = new AttestationObjectLoader(
			$attestationStatementSupportManager
		);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			$attestationObjectLoader
		);
		$credentialRepository = new WebAuthnCredentialRepository( $user );
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
				$registrationObject,
				$request
			);
		} catch ( Throwable $ex ) {
			$this->logger->warning(
				"WebAuthn key registration failed due to: {$ex->getMessage()}"
			);
			return false;
		}

		if ( $response->getAttestationObject()->getAuthData()->hasAttestedCredentialData() ) {
			$this->userHandle = $registrationObject->getUser()->getId();
			// @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty
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
	 * @param string $data
	 * @param PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions
	 * @param OATHUser $user
	 * @return bool
	 */
	private function authenticationCeremony( $data, $publicKeyCredentialRequestOptions, $user ) {
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$attestationStatementSupportManager->add( new FidoU2FAttestationStatementSupport() );
		$attestationStatementSupportManager->add( new AndroidKeyAttestationStatementSupport() );

		$attestationObjectLoader = new AttestationObjectLoader(
			$attestationStatementSupportManager
		);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			$attestationObjectLoader
		);
		$coseAlgorithmManager = new Manager();
		$coseAlgorithmManager->add( new ES256() );
		$coseAlgorithmManager->add( new ES512() );
		$coseAlgorithmManager->add( new EdDSA() );
		$coseAlgorithmManager->add( new RS1() );
		$coseAlgorithmManager->add( new RS256() );
		$coseAlgorithmManager->add( new RS512() );

		$credentialRepository = new WebAuthnCredentialRepository( $user );
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();
		$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
			$credentialRepository,
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
				// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType
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

	/** @inheritDoc */
	public function getModule(): string {
		return WebAuthn::MODULE_ID;
	}
}
