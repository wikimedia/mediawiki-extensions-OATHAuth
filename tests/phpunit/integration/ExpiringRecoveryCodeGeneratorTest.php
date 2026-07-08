<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\CentralAuth\CentralAuthUserCache;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\OATHAuth\ExpiringRecoveryCodeGenerator;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Mail\IEmailer;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use StatusValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\ExpiringRecoveryCodeGenerator
 * @covers \MediaWiki\Extension\OATHAuth\Notifications\Manager
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 */
class ExpiringRecoveryCodeGeneratorTest extends MediaWikiIntegrationTestCase {

	private const array RECOVERY_KEYS = [ 'H8572S2FB1LCGYWN', 'V61A5VEM42DGLDMU' ];

	/** @var string[] Recipient addresses captured by the IEmailer mock, in send order. */
	private array $sentEmails = [];

	protected function setUp(): void {
		parent::setUp();

		// Don't confuse our checks by "2FA has been enabled" notifications
		global $wgEchoNotifications;
		unset( $wgEchoNotifications['oathauth-enable'] );

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->overrideConfigValue( 'OATHRecoveryCodesCount', 10 );

		$this->sentEmails = [];
		$mailerMock = $this->createMock( IEmailer::class );
		$mailerMock->method( 'send' )
			->willReturnCallback( function ( $to ) {
				$this->sentEmails[] = $to->address;
				return StatusValue::newGood();
			} );
		$this->setService( 'Emailer', $mailerMock );
	}

	private function getGenerator(): ExpiringRecoveryCodeGenerator {
		return OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getExpiringRecoveryCodeGenerator();
	}

	private function expectRecoveryLogged(): void {
		$loggerMock = $this->createMock( OATHAuthLogger::class );
		$loggerMock->expects( $this->once() )->method( 'logOATHRecovery' );
		$this->setService( 'OATHAuth.Logger', $loggerMock );
	}

	/**
	 * Enable two-factor authentication for $user by creating a recovery-codes key holding
	 * the fixed {@link RECOVERY_KEYS}.
	 */
	private function enableRecoveryCodes( User $user ): void {
		$oathServices = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$userRepo = $oathServices->getUserRepository();
		$recoveryCodesModule = $oathServices->getModuleRegistry()
			->getModuleByKey( RecoveryCodes::MODULE_NAME );

		$oathUser = $userRepo->findByUser( $user );
		$userRepo->createKey( $oathUser, $recoveryCodesModule, [
			'recoverycodekeys' => self::RECOVERY_KEYS,
		], '127.0.0.1' );
	}

	private function getRecoveryCodeKeys( User $user ): array {
		$oathUser = OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getUserRepository()
			->findByUser( $user );
		$keys = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $keys );
		$key = $keys[0];
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );
		return $keys;
	}

	/**
	 * Assert that every e-mail sent during recovery - the recovery-codes e-mail plus any
	 * Echo notification e-mails to the same user - went to the expected address, and that
	 * at least one (the recovery-codes e-mail) was sent.
	 */
	private function assertAllEmailsSentTo( string $expectedEmail ): void {
		$this->assertNotEmpty(
			$this->sentEmails,
			'Expected at least the recovery-codes e-mail to be sent'
		);
		$this->assertSame( [ $expectedEmail ], array_unique( $this->sentEmails ) );
	}

	/** @dataProvider provideGeneratedAdditionalCodes */
	public function testGeneratedAdditionalCodes(
		?string $userEmail,
		bool $userEmailConfirmed,
		?string $enteredEmail,
		string $expectedEmail
	) {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->overrideConfigValue( 'OATHAdditionalRecoveryCodesValidityDays', 5 );

		$this->expectRecoveryLogged();

		$targetUser = $this->getMutableTestUser()->getUser();
		$targetUser->setEmail( $userEmail ?? '' );
		if ( $userEmailConfirmed ) {
			$targetUser->setEmailAuthenticationTimestamp( '20250101000000' );
		}
		$targetUser->saveSettings();

		$this->enableRecoveryCodes( $targetUser );

		$key = $this->getRecoveryCodeKeys( $targetUser )[0];
		$this->assertCount( 2, $key->getRecoveryCodeKeys() );

		$generator = $this->getGenerator();
		$status = $generator->attemptToGenerateRecoveryCodes(
			$this->getTestSysop()->getUser(),
			$targetUser->getName(),
			$enteredEmail ?? '',
			'I am required!'
		);
		$this->assertStatusGood( $status );

		$this->assertAllEmailsSentTo( $expectedEmail );
		$this->assertSame( $targetUser->getName(), $generator->getTargetUser()->getName() );

		$newKey = $this->getRecoveryCodeKeys( $targetUser )[0];
		$newCodes = $newKey->getRecoveryCodeKeys();
		$this->assertSame( 10, $generator->getCodesCount() );
		$this->assertCount( 12, $newCodes );
		$this->assertSame( self::RECOVERY_KEYS, array_slice( $newCodes, 0, 2 ) );

		$tempCode = $newKey->getRecoveryCodes()[2];
		$this->assertSame( '20260106000000', $tempCode->getExpiryTimestamp() );
	}

	public static function provideGeneratedAdditionalCodes(): array {
		return [
			'User has email, no email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => true,
				'enteredEmail' => null,
				'expectedEmail' => 'user@example.com',
			],
			// Ensure that user's own email takes precedence
			'User has email, email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => true,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'user@example.com',
			],
			'User has unconfirmed email, email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => false,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'other@example.com',
			],
			'User has no email, email entered' => [
				'userEmail' => null,
				'userEmailConfirmed' => false,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'other@example.com',
			],
		];
	}

	public function testUsesCentralAccountEmailIfNoLocal() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$this->overrideConfigValue( 'OATHAdditionalRecoveryCodesValidityDays', 5 );

		$this->expectRecoveryLogged();

		$centralAuthUserCacheMock = $this->createMock( CentralAuthUserCache::class );
		$centralAuthUserCacheMock->method( 'get' )
			->willReturnCallback( static function ( $name, $fromPrimary ) {
				$centralUser = new CentralAuthUser( $name );
				$centralUser->setEmail( 'global@example.com' );
				$centralUser->setEmailAuthenticationTimestamp( '20250101000000' );
				return $centralUser;
			} );
		$this->setService( 'CentralAuth.CentralAuthUserCache', $centralAuthUserCacheMock );

		// This user has no local e-mail address but has a global one
		$targetUser = $this->getMutableTestUser()->getUser();
		$targetUser->setEmail( '' );
		$targetUser->saveSettings();

		$this->enableRecoveryCodes( $targetUser );

		$status = $this->getGenerator()->attemptToGenerateRecoveryCodes(
			$this->getTestSysop()->getUser(),
			$targetUser->getName(),
			'',
			'I am required!'
		);
		$this->assertStatusGood( $status );

		$this->assertAllEmailsSentTo( 'global@example.com' );
	}

	public function testFailsForNonexistentUser() {
		$status = $this->getGenerator()->attemptToGenerateRecoveryCodes(
			$this->getTestSysop()->getUser(),
			// Sharks are amazing, so no shark haters exist
			'Shark hater',
			'',
			'I am required!'
		);

		$this->assertStatusError( 'oathauth-user-not-found', $status );
		$this->assertSame( [], $this->sentEmails );
	}

	public function testFailsForUserWithTwoFactorDisabled() {
		$targetUser = $this->getMutableTestUser()->getUser();
		$targetUser->setEmail( 'user@example.com' );
		$targetUser->setEmailAuthenticationTimestamp( '20250101000000' );
		$targetUser->saveSettings();

		$status = $this->getGenerator()->attemptToGenerateRecoveryCodes(
			$this->getTestSysop()->getUser(),
			$targetUser->getName(),
			'',
			'I am required!'
		);

		$this->assertStatusError( 'oathauth-recover-fail-no-2fa', $status );
		$this->assertSame( [], $this->sentEmails );
	}

	public function testFailsWhenUserHasNoEmailAndNoneEntered() {
		// This user has no e-mail address and none is provided
		$targetUser = $this->getMutableTestUser()->getUser();
		$targetUser->setEmail( '' );
		$targetUser->saveSettings();

		$this->enableRecoveryCodes( $targetUser );

		$status = $this->getGenerator()->attemptToGenerateRecoveryCodes(
			$this->getTestSysop()->getUser(),
			$targetUser->getName(),
			'',
			'I am required!'
		);

		$this->assertStatusError( 'oathauth-recover-fail-email-required', $status );
		$this->assertSame( [], $this->sentEmails );

		// Ensure that no codes were generated (still just the original two)
		$newCodes = $this->getRecoveryCodeKeys( $targetUser )[0]->getRecoveryCodeKeys();
		$this->assertCount( 2, $newCodes );
	}
}
