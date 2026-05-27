<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWikiIntegrationTestCase;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\WebAuthnKey
 * @group Database
 */
class WebAuthnRegistrationRegressionTest extends MediaWikiIntegrationTestCase {

	/**
	 * Mirrors WebAuthnAuthenticator::continueRegistration(): a fresh key whose
	 * attestedCredentialData is not yet populated. With an invalid attestation,
	 * verifyRegistration() must return false rather than fatal.
	 */
	public function testVerifyRegistrationDoesNotFatalOnFreshKey(): void {
		$this->setGroupPermissions( 'user', 'oathauth-enable', true );

		/** @var WebAuthnAuthenticator $authenticator */
		$authenticator = $this->getServiceContainer()->getService( 'OATHAuth.WebAuthnAuthenticator' );
		/** @var OATHUserRepository $repo */
		$repo = $this->getServiceContainer()->getService( 'OATHAuth.UserRepository' );
		$oathUser = $repo->findByUser( $this->getTestUser()->getUser() );

		$status = $authenticator->startRegistration( $oathUser, true );
		$this->assertStatusGood( $status );
		/** @var PublicKeyCredentialCreationOptions $creationOptions */
		$creationOptions = $status->getValue()['raw'];

		$key = WebAuthnKey::newKey();
		$result = $key->verifyRegistration( 'test key', '{}', $creationOptions, $oathUser );

		$this->assertFalse( $result );
	}
}
