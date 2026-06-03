<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\UpdateKeysToLatestVersion;
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
		$this->expectOutputString( "Done. Processed 0 rows and updated 0 keys" );
		$this->maintenance->execute();
	}
}
