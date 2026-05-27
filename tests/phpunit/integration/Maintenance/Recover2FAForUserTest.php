<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser
 * @group Database
 */
class Recover2FAForUserTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return Recover2FAForUser::class;
	}

	public function testNonExistentUser() {
		$this->maintenance->setArg( 'user', 'foobar' );
		$this->maintenance->setArg( 'email', 'foobar@email.com' );
		$this->expectOutputString( "User foobar doesn't exist!" );
		$this->maintenance->execute();
	}
}
