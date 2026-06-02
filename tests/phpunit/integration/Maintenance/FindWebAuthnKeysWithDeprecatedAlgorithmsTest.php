<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Maintenance\FindWebAuthnKeysWithDeprecatedAlgorithms;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\Tests\Integration\Key\WebAuthnKeyTest as KeyIntegrationTest;
use MediaWiki\Extension\OATHAuth\Tests\Unit\Key\WebAuthnKeyTest as KeyUnitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\FindWebAuthnKeysWithDeprecatedAlgorithms
 * @group Database
 */
class FindWebAuthnKeysWithDeprecatedAlgorithmsTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return FindWebAuthnKeysWithDeprecatedAlgorithms::class;
	}

	public function testFindDeprecated(): void {
		[ $repository, $moduleRegistry, $oathUser, ] = $this->setupConfig();

		$key = WebAuthnKey::newFromData(
			[
				'credentialPublicKey' => KeyUnitTest::KEY_RS1_2048,
			] + KeyIntegrationTest::KEY_DATA
		);

		$repository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( WebAuthn::MODULE_NAME ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$this->expectOutputRegex( "/1 keys found. 1 keys are using deprecated algorithms./" );
		$this->maintenance->execute();
	}
}
