<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP
 * @group Database
 */
class MoveRecoveryCodesFromTOTPTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return MoveRecoveryCodesFromTOTP::class;
	}

	public function testNotifyNoUsers() {
		$this->expectOutputString(
			"Done. Updated 0 of 0 rows"
		);

		$this->maintenance->execute();
	}
}
