<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Maintenance\FindWebAuthnKeysWithDeprecatedAlgorithms;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Tests\Integration\Key\WebAuthnKeyTest as KeyIntegrationTest;
use MediaWiki\Extension\OATHAuth\Tests\Unit\Key\WebAuthnKeyTest as KeyUnitTest;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\FindWebAuthnKeysWithDeprecatedAlgorithms
 * @group Database
 */
class FindWebAuthnKeysWithDeprecatedAlgorithmsTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return FindWebAuthnKeysWithDeprecatedAlgorithms::class;
	}

	public function testFindDeprecated() {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$repository = $services->getUserRepository();
		$moduleRegistry = $services->getModuleRegistry();
		$module = $moduleRegistry->getModuleByKey( WebAuthn::MODULE_NAME );

		$oathUser = $repository->findByUser( $user );

		$key = WebAuthnKey::newFromData(
			[
				'credentialPublicKey' => KeyUnitTest::KEY_RS1_2048,
			] + KeyIntegrationTest::KEY_DATA
		);

		$repository->createKey(
			$oathUser,
			$module,
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$this->expectOutputString( "1 keys found. 1 keys are using deprecated algorithms.\n" );
		$this->maintenance->execute();
	}
}
