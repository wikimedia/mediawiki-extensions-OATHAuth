<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Hook;

use MediaWiki\Extension\OATHAuth\Hook\HookHandler;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers MediaWiki\Extension\OATHAuth\Hook\HookHandler
 */
class HookHandlerTest extends MediaWikiIntegrationTestCase {

	protected function createHookHandler(): HookHandler {
		$services = MediaWikiServices::getInstance();
		return new HookHandler(
			$services->get( 'OATHUserRepository' ),
			$services->get( 'OATHAuthModuleRegistry' ),
			$services->getPermissionManager(),
			$services->getMainConfig(),
			$services->getUserGroupManager()
		);
	}

	/** @dataProvider provideOnUserRequirementsCondition */
	public function testOnUserRequirementsCondition( string $condition, bool $has2FA, ?bool $expectedResult ) {
		$userIdentity = UserIdentityValue::newRegistered( 1, 'WikiUser' );

		$oathUser = $this->createMock( OATHUser::class );
		$oathUser->method( 'isTwoFactorAuthEnabled' )
			->willReturn( $has2FA );

		$userRepoMock = $this->createMock( OATHUserRepository::class );
		$userRepoMock->method( 'findByUser' )
			->with( $userIdentity )
			->willReturn( $oathUser );

		$this->setService( 'OATHUserRepository', $userRepoMock );

		$hookHandler = $this->createHookHandler();
		$result = null;
		$hookHandler->onUserRequirementsCondition(
			$condition,
			[],
			$userIdentity,
			false,
			$result
		);

		$this->assertSame( $expectedResult, $result );
	}

	public static function provideOnUserRequirementsCondition() {
		return [
			'User has 2FA' => [
				'condition' => APCOND_OATH_HAS2FA,
				'has2FA' => true,
				'expectedResult' => true,
			],
			'User does not have 2FA' => [
				'condition' => APCOND_OATH_HAS2FA,
				'has2FA' => false,
				'expectedResult' => false,
			],
			'Handler does not handle other conditions' => [
				'condition' => 'undefined condition',
				'has2FA' => false,
				'expectedResult' => null,
			],
		];
	}
}
