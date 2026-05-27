<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\NotifyTwoFactorRequired;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\NotifyTwoFactorRequired
 * @group Database
 */
class NotifyTwoFactorRequiredTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return NotifyTwoFactorRequired::class;
	}

	public function testNotifyNoUsers() {
		$this->maintenance->setOption( 'date', '20260630000000' );

		$this->expectOutputString(
			"Total: 0; Blocked: 0; Other skipped: 0\n" .
			"2FA already enabled: 0; 2FA needed: 0\n"
		);

		$this->maintenance->execute();
	}
}
