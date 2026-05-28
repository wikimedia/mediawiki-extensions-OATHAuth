<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\ReEncryptSecrets;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Tests\Integration\EncryptionTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\ReEncryptSecrets
 * @group Database
 */
class ReEncryptSecretsTest extends MaintenanceBaseTestCase {

	use EncryptionTestTrait;
	use UserWith2FATrait;

	private const string NEW_SECRET_KEY = '22e715e0c785dde0d0f39f8ed94e9f2419ad8d01e91e9343902afba8aaa4ec91';

	protected function setUp(): void {
		parent::setUp();

		$this->encryptionIntegrationTestSetup();
	}

	protected function getMaintenanceClass() {
		return ReEncryptSecrets::class;
	}

	public function testMismatchInputVersusConfig(): void {
		$this->maintenance->setArg( 0, 'foo' );
		$this->maintenance->setArg( 1, 'bar' );

		$this->expectCallToFatalError();
		$this->expectOutputString(
			"The old key is not the same as \$wgOATHSecretKey. Unable to decrypt existing secrets."
		);

		$this->maintenance->execute();
	}

	public function testIdenticalInput(): void {
		$this->maintenance->setArg( 0, 'foo' );
		$this->maintenance->setArg( 1, 'foo' );

		$this->expectCallToFatalError();
		$this->expectOutputString(
			"The old key is the same as the new key. No reason to re-encrypt existing secrets."
		);

		$this->maintenance->execute();
	}

	public function testInvalidFirstSecret(): void {
		$this->maintenance->setArg( 0, 'foo' );
		$this->maintenance->setArg( 1, self::NEW_SECRET_KEY );

		$this->expectCallToFatalError();
		$this->expectOutputString( "The 'old' parameter is not set correctly!" );

		$this->maintenance->execute();
	}

	public function testInvalidSecondSecret(): void {
		$this->maintenance->setArg( 0, self::SECRET_KEY );
		$this->maintenance->setArg( 1, 'foo' );

		$this->expectCallToFatalError();
		$this->expectOutputString( "The 'new' parameter is not set correctly!" );

		$this->maintenance->execute();
	}

	public function testReEncyptSecrets(): void {
		[ $repository, $user, , , $recoveryKeys ] = $this->setupUserWith2FA();

		$this->maintenance->setArg( 0, self::SECRET_KEY );
		$this->maintenance->setArg( 1, self::NEW_SECRET_KEY );
		$this->maintenance->execute();

		$this->expectOutputString(
			"Please set \$wgOATHSecretKey to the new value in LocalSettings.php.\n"
		);

		$this->overrideConfigValue( 'OATHSecretKey', self::NEW_SECRET_KEY );
		$this->getServiceContainer()->resetServiceForTesting( 'OATHAuth.EncryptionHelper' );

		// Test we can still verify the recovery codes
		$oathUser = $repository->findByUser( $user );
		$modules = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $modules );

		$this->assertTrue( $modules[0]->verify( $oathUser, [ 'recoverycode' => $recoveryKeys[0] ] ) );
	}
}
