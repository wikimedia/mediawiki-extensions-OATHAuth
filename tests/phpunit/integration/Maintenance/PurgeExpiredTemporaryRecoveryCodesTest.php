<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\PurgeExpiredTemporaryRecoveryCodes;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\PurgeExpiredTemporaryRecoveryCodes
 * @group Database
 */
class PurgeExpiredTemporaryRecoveryCodesTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return PurgeExpiredTemporaryRecoveryCodes::class;
	}

	public function testPurgeForNoRowsToPurge(): void {
		$this->expectOutputString( "Done. Updated 0 of 0 rows" );
		$this->maintenance->execute();
	}

	public function testPurgeUserWith2FA(): void {
		$this->setupUserWith2FA();

		$this->expectOutputString( "Done. Updated 0 of 1 rows" );
		$this->maintenance->execute();
	}
}
