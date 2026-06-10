<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\Enable2FAForUsersWithout;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\Enable2FAForUsersWithout
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\Base\AllUsers
 * @group Database
 */
class Enable2FAForUsersWithoutTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return Enable2FAForUsersWithout::class;
	}

	public function testEnableWithNoUsers(): void {
		$this->expectOutputString(
			"Total: 0; Blocked: 0; Without email: 0; Other skipped: 0\n" .
			"2FA already enabled: 0; 2FA setup: 0; 2FA not required: 0\n" .
			"Done.\n"
		);

		$this->maintenance->execute();
	}
}
