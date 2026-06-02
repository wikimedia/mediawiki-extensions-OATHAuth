<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser
 * @group Database
 */
class Recover2FAForUserTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	private const string EMAIL_ADDRESS = 'foobar@email.com';

	protected function getMaintenanceClass() {
		return Recover2FAForUser::class;
	}

	public function testNonExistentUser(): void {
		$this->maintenance->setArg( 'user', 'foobar' );
		$this->maintenance->setArg( 'email', self::EMAIL_ADDRESS );
		$this->expectOutputString( "No user account was found with that name\n" );
		$this->maintenance->execute();
	}

	public function testRecover2FAForUser(): void {
		[ , $user, , , ] = $this->setupUserWith2FA();

		$user->setEmail( self::EMAIL_ADDRESS );

		$mailerMock = $this->createMock( IEmailer::class );
		$mailerMock->expects( $this->once() )
			->method( 'send' )
			->willReturnCallback( function ( $to ) {
				$this->assertSame( self::EMAIL_ADDRESS, $to->address );
				return StatusValue::newGood();
			} );
		$this->setService( 'Emailer', $mailerMock );

		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		$this->maintenance->setArg( 'email', self::EMAIL_ADDRESS );
		$this->expectOutputString( "Expiring recovery codes generated successfully and emailed to $username.\n" );
		$this->maintenance->execute();
	}
}
