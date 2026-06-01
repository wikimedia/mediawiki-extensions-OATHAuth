<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP
 * @group Database
 */
class MoveRecoveryCodesFromTOTPTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return MoveRecoveryCodesFromTOTP::class;
	}

	public function testNotifyNoUsers(): void {
		$this->expectOutputString(
			"Done. Updated 0 of 0 rows"
		);

		$this->maintenance->execute();
	}

	public function testMoveRecoveryCodes(): void {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();

		$totp = TOTPKey::newFromRandom()->jsonSerialize();
		$totp['scratch_tokens'] = [ '123456', '654321' ];

		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$totpModuleId = $services->getModuleRegistry()->getModuleId( TOTP::MODULE_NAME );
		$recoveryModuleId = $services->getModuleRegistry()->getModuleId( RecoveryCodes::MODULE_NAME );

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

		$this->expectOutputString(
			"Done. Updated 1 of 1 rows"
		);

		$this->maintenance->execute();

		$res = $db->newSelectQueryBuilder()
			->from( 'oathauth_devices' )
			->select( [ 'oad_type', 'oad_data' ] )
			->where( [ 'oad_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Should be a TOTP and a RecoveryCodes row
		$this->assertSame( 2, $res->numRows() );

		foreach ( $res as $row ) {
			if ( $row->oad_type === $totpModuleId ) {
				$this->assertArrayNotHasKey( 'scratch_tokens', FormatJson::decode( $row->oad_data ) );
			} elseif ( $row->oad_type === $recoveryModuleId ) {
				$this->assertArrayEquals(
					$totp['scratch_tokens'],
					FormatJson::decode( $row->oad_data['recoverycodekeys'] )
				);
			}
		}
	}
}
