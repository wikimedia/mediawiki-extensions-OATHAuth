<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\UpdateSecretsToEncryptedFormat;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Tests\Integration\EncryptionTestTrait;
use MediaWiki\Json\FormatJson;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\UpdateSecretsToEncryptedFormat
 * @group Database
 */
class UpdateSecretsToEncryptedFormatTest extends MaintenanceBaseTestCase {

	use EncryptionTestTrait;
	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return UpdateSecretsToEncryptedFormat::class;
	}

	public function testReEncyptSecrets(): void {
		[ $repository, $user, , , $recoveryKeys ] = $this->setupUserWith2FA();

		// Enable encryption *after* setting up 2FA, so they aren't created encrypted
		$this->encryptionEnableIntegrationTestSetup();

		$this->maintenance->execute();

		$this->expectOutputRegex(
			"/Done. Updated 2 of 2 rows in/"
		);

		// Test we can still verify the recovery codes
		$oathUser = $repository->findByUser( $user );
		$modules = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $modules );

		$this->assertTrue( $modules[0]->verify( $oathUser, [ 'recoverycode' => $recoveryKeys[0] ] ) );

		// Because we don't have an "isEncrypted()" or similar function...
		$res = $this->getDb()->newSelectQueryBuilder()
			->select( 'oad_data' )
			->from( 'oathauth_devices' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$json = FormatJson::decode( $row->oad_data, true );
			// Currently the easiest way to check if encrypted
			$this->assertTrue( isset( $json['nonce'] ) );
		}
	}

}
