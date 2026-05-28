<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles
 * @group Database
 */
class PopulateUserHandlesTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return PopulateUserHandles::class;
	}

	public function testPopulateNoUsers() {
		$this->expectOutputString(
			"Done. Processed 0 users and inserted 0 rows"
		);

		$this->maintenance->execute();
	}
}
