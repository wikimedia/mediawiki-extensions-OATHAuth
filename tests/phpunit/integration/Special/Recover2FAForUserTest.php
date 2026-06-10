<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\OATHAuth\ExpiringRecoveryCodeGenerator;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\Recover2FAForUser;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * Tests for the {@link Recover2FAForUser} special page itself. The recovery-code
 * generation and e-mail logic lives in {@link ExpiringRecoveryCodeGenerator} and is
 * covered by its own test.
 *
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\Recover2FAForUser
 */
class Recover2FAForUserTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->setGroupPermissions( 'sysop', 'oathauth-recover-for-user', true );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage(): Recover2FAForUser {
		$userRepo = $this->createMock( OATHUserRepository::class );
		$oathUser = $this->createMock( OATHUser::class );
		$oathUser->method( 'isTwoFactorAuthEnabled' )->willReturn( true );
		$userRepo->method( 'findByUser' )->willReturn( $oathUser );

		$oathServices = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new Recover2FAForUser(
			$oathServices->getExpiringRecoveryCodeGenerator(),
			$this->getServiceContainer()->getLinkRenderer(),
			$userRepo,
			$this->getServiceContainer()->getUserFactory(),
		);
	}

	public function testFormLoads() {
		$generator = $this->createMock( ExpiringRecoveryCodeGenerator::class );
		$generator->method( 'getCodesCount' )->willReturn( 10 );
		$generator->method( 'getUserByName' )->willReturn( null );
		$this->setService( 'OATHAuth.ExpiringRecoveryCodeGenerator', $generator );

		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-recover-intro: 10, 10)', $html );
		$this->assertStringContainsString( '(oathauth-recover-for-user-legend: 10)', $html );
		$this->assertStringContainsString( '(oathauth-enteruser)', $html );
		$this->assertStringContainsString( '(oathauth-enterrecoverreason)', $html );
	}

	public function testChecksPermissions() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage(
			'',
			null,
			null,
			$this->getTestUser()->getUser(),
		);
	}

	public function testShowsEmailFieldWhenTargetHasNoEmail() {
		$targetUser = $this->getTestUser()->getUser();
		$generator = $this->createMock( ExpiringRecoveryCodeGenerator::class );
		$generator->method( 'getUserByName' )->willReturn( $targetUser );
		$generator->method( 'getUserEmail' )->willReturn( null );
		$generator->method( 'getCodesCount' )->willReturn( 10 );
		$this->setService( 'OATHAuth.ExpiringRecoveryCodeGenerator', $generator );

		$html = $this->commonExecuteSpecialPage( [ 'user' => $targetUser->getName() ] );

		$this->assertStringContainsString( '(oathauth-enterrecoveremail)', $html );
	}

	public function testHidesEmailFieldWhenTargetHasEmail() {
		$targetUser = $this->getTestUser()->getUser();
		$generator = $this->createMock( ExpiringRecoveryCodeGenerator::class );
		$generator->method( 'getUserByName' )->willReturn( $targetUser );
		$generator->method( 'getUserEmail' )->willReturn( 'user@example.com' );
		$generator->method( 'getCodesCount' )->willReturn( 10 );
		$this->setService( 'OATHAuth.ExpiringRecoveryCodeGenerator', $generator );

		$html = $this->commonExecuteSpecialPage( [ 'user' => $targetUser->getName() ] );

		$this->assertStringNotContainsString( '(oathauth-enterrecoveremail)', $html );
	}

	public function testDelegatesToGeneratorAndReportsSuccess() {
		$targetUser = $this->getMutableTestUser()->getUser();

		$generator = $this->createMock( ExpiringRecoveryCodeGenerator::class );
		$generator->method( 'getUserByName' )->willReturn( $targetUser );
		$generator->method( 'getUserEmail' )->willReturn( 'user@example.com' );
		$generator->method( 'getCodesCount' )->willReturn( 10 );
		$generator->method( 'getTargetUser' )->willReturn( $targetUser );
		$generator->expects( $this->once() )
			->method( 'attemptToGenerateRecoveryCodes' )
			->willReturn( Status::newGood() );
		$this->setService( 'OATHAuth.ExpiringRecoveryCodeGenerator', $generator );

		$html = $this->commonExecuteSpecialPage( [
			'reason' => 'I am required!',
			'user' => $targetUser->getName(),
		] );

		$this->assertStringContainsString(
			"(oathauth-recoveredoath: 10, {$targetUser->getName()}",
			$html
		);
	}

	public function testShowsErrorWhenGenerationFails() {
		$targetUser = $this->getTestUser()->getUser();

		$generator = $this->createMock( ExpiringRecoveryCodeGenerator::class );
		$generator->method( 'getUserByName' )->willReturn( $targetUser );
		$generator->method( 'getUserEmail' )->willReturn( 'user@example.com' );
		$generator->method( 'getCodesCount' )->willReturn( 10 );
		$generator->expects( $this->once() )
			->method( 'attemptToGenerateRecoveryCodes' )
			->willReturn( Status::newFatal( 'oathauth-recover-fail-no-2fa' ) );
		$this->setService( 'OATHAuth.ExpiringRecoveryCodeGenerator', $generator );

		$html = $this->commonExecuteSpecialPage( [
			'reason' => 'I am required!',
			'user' => $targetUser->getName(),
		] );

		$this->assertStringContainsString( '(oathauth-recover-fail-no-2fa)', $html );
	}

	private function commonExecuteSpecialPage( array $params ): string {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( $params, true, ),
			null,
			$user,
		);
		return $html;
	}
}
