<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\DisableOATHAuthForUser;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\DisableOATHAuthForUser
 * @group Database
 */
class DisableOATHAuthForUserTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return DisableOATHAuthForUser::class;
	}

	public function testDisableOATHAuthForUser() {
		[ $repository, $user, $totpKey, $recoveryKey, ] = $this->setupUserWith2FA();

		$this->assertArrayEquals( [ $totpKey, $recoveryKey ], $repository->findByUser( $user )->getKeys() );

		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );

		$this->expectOutputString( "Two-factor authentication disabled for $username.\n." );
		$this->maintenance->execute();

		$this->assertArrayEquals( [], $repository->findByUser( $user )->getKeys() );
	}

	public function testNonExistentUser() {
		$this->maintenance->setArg( 'user', 'foobar' );
		$this->expectCallToFatalError();
		$this->expectOutputString( "User foobar doesn't exist!" );
		$this->maintenance->execute();
	}

	public function test2FANotEnabled() {
		$user = $this->getTestSysop()->getUser();
		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		$this->expectCallToFatalError();
		$this->expectOutputString( "User $username does not have two-factor authentication enabled!" );
		$this->maintenance->execute();
	}
}
