<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\OATHAuth\ExpiringRecoveryCodeGenerator
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\Recover2FAForUser
 * @covers \MediaWiki\Extension\OATHAuth\Notifications\Manager
 * @group Database
 */
class Recover2FAForUserTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	private const string EMAIL_ADDRESS = 'foobar@email.com';

	protected function getMaintenanceClass() {
		return Recover2FAForUser::class;
	}

	public function testNonExistentUser(): void {
		$this->useLocalCentralIdLookup();

		$this->maintenance->setArg( 'user', 'foobar' );
		$this->maintenance->setArg( 'email', self::EMAIL_ADDRESS );
		$this->expectCallToFatalError();
		$this->expectOutputString( "No user account was found with that name\n" );
		$this->maintenance->execute();
	}

	public function testUserWithoutEmailAndNoEmailArgSet(): void {
		[ , $user, , , ] = $this->setupUserWith2FA();
		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		$this->expectCallToFatalError();
		$this->expectOutputString(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			"User doesn't have a confirmed email address associated with their account. Please provide an email address to send the message to.\n"
		);
		$this->maintenance->execute();
	}

	public function testUserWithout2FA(): void {
		$this->useLocalCentralIdLookup();

		$user = $this->getTestSysop()->getUser();
		$user->setEmail( self::EMAIL_ADDRESS );
		$user->saveSettings();
		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		$this->expectCallToFatalError();
		$this->expectOutputString(
			"User doesn't have two-factor authentication enabled, so recovery is not applicable.\n"
		);
		$this->maintenance->execute();
	}

	private function setEmailerMock(): void {
		$mailerMock = $this->createMock( IEmailer::class );
		$mailerMock->expects( $this->once() )
			->method( 'send' )
			->willReturnCallback( function ( $to ) {
				$this->assertSame( self::EMAIL_ADDRESS, $to->address );
				return StatusValue::newGood();
			} );
		$this->setService( 'Emailer', $mailerMock );
	}

	public function testRecover2FAForUser(): void {
		[ , $user, , , ] = $this->setupUserWith2FA();

		$user->setEmail( self::EMAIL_ADDRESS );
		$user->saveSettings();

		$this->setEmailerMock();

		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		// TODO: Shouldn't actually be needed...
		$this->maintenance->setArg( 'email', self::EMAIL_ADDRESS );
		$this->expectOutputString( "Expiring recovery codes generated successfully and emailed to $username.\n" );
		$this->maintenance->execute();
	}

	public function testTooManyRecoveryCodes(): void {
		[ , $user, , , ] = $this->setupUserWith2FA();

		$user->setEmail( self::EMAIL_ADDRESS );
		$user->saveSettings();

		// Generate more than the max..
		$this->overrideConfigValue( 'OATHRecoveryCodesCount', 10 );
		$this->overrideConfigValue( 'OATHMaxRecoveryCodesCount', 1 );

		$username = $user->getName();
		$this->maintenance->setArg( 'user', $username );
		// TODO: Shouldn't actually be needed...
		$this->maintenance->setArg( 'email', self::EMAIL_ADDRESS );
		$this->expectCallToFatalError();
		$this->expectOutputString(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			"The user has already reached a maximum possible number of recovery codes. Unable to generate additional recovery codes.\n"
		);
		$this->maintenance->execute();
	}
}
