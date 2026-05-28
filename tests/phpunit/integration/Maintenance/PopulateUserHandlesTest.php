<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\Tests\Integration\Key\WebAuthnKeyTest as KeyIntegrationTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles
 * @group Database
 */
class PopulateUserHandlesTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return PopulateUserHandles::class;
	}

	public function testPopulateNoUsers(): void {
		$this->expectOutputString(
			"Done. Processed 0 users and inserted 0 rows"
		);

		$this->maintenance->execute();
	}

	public function testPopulateUserWith2FA(): void {
		[ $repository, $moduleRegistry, $oathUser, ] = $this->setupConfig();

		$key = WebAuthnKey::newFromData(
			KeyIntegrationTest::KEY_DATA
		);

		$repository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( WebAuthn::MODULE_NAME ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$this->expectOutputString( "Done. Processed 1 users and inserted 1 rows" );
		$this->maintenance->execute();
	}
}
