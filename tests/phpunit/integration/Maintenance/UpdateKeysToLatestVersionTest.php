<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateKeysToLatestVersion;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\UpdateKeysToLatestVersion
 * @group Database
 */
class UpdateKeysToLatestVersionTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return UpdateKeysToLatestVersion::class;
	}

	public function testUpdateNoKeys(): void {
		$this->expectOutputRegex( "/Done. Processed 0 rows and updated 0 keys/" );
		$this->maintenance->execute();
	}

	public function testUpdateKeyWithNoVersion(): void {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();

		$totp = TOTPKey::newFromRandom()->jsonSerialize();
		// Lazily just remove the version, maintenance script should re-add it
		unset( $totp['version'] );

		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$totpModuleId = $services->getModuleRegistry()->getModuleId( TOTP::MODULE_NAME );

		$db = $this->getDb();
		$db->newInsertQueryBuilder()
			->insert( 'oathauth_devices' )
			->row( [
				'oad_user' => $user->getId(),
				'oad_type' => $totpModuleId,
				'oad_created' => $db->timestamp(),
				'oad_data' => FormatJson::encode( $totp ),
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( 1, $db->affectedRows() );
		$id = $db->insertId();

		$this->expectOutputRegex(
			"/Done. Processed 1 rows and updated 1 keys/"
		);

		$this->maintenance->execute();

		$row = $db->newSelectQueryBuilder()
			->from( 'oathauth_devices' )
			->select( 'oad_data' )
			->where( [ 'oad_id' => $id ] )
			->caller( __METHOD__ )
			->fetchRow();

		$data = FormatJson::decode( $row->oad_data, true );

		$this->assertTrue( isset( $data['version'] ) );
	}
}
