<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Maintenance\DisableOATHAuthForUser;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\DisableOATHAuthForUser
 * @group Database
 */
class DisableOATHAuthForUserTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return DisableOATHAuthForUser::class;
	}

	public function testDisableOATHAuthForUser() {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$repository = $services->getUserRepository();
		$moduleRegistry = $services->getModuleRegistry();
		$module = $moduleRegistry->getModuleByKey( TOTP::MODULE_NAME );

		$oathUser = $repository->findByUser( $user );

		$key = $repository->createKey(
			$oathUser,
			$module,
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$this->assertArrayEquals( [ $key ], $repository->findByUser( $user )->getKeys() );

		$this->maintenance->setArg( 'user', $user->getName() );
		$this->maintenance->execute();

		$this->expectOutputString( "Two-factor authentication disabled for {$user->getName()}.\n." );

		$this->assertArrayEquals( [], $repository->findByUser( $user )->getKeys() );
	}
}
