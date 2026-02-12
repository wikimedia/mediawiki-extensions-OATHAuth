<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Enforce2FA;

use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\OATHAuth\Enforce2FA\UserRequirementsConditionCheckerWith2FAAssumption;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 */
class Mandatory2FACheckerTest extends MediaWikiIntegrationTestCase {

	public function testGetGroupsRequiring2FA_simpleCondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'interface-admin' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
			'checkuser' => [
				'memberConditions' => [	APCOND_OATH_HAS2FA ],
			],
			'sysop' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
			]
		] );

		$this->setUserGroupManagerMock( [ 'interface-admin', 'sysop' ] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [ 'interface-admin' ], $groups2FA );
	}

	public function testGroupsRequiring2FA_complexCondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'interface-admin' => [
				'memberConditions' => [
					// 2FA not required, user meets the other condition
					'|', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 0 ]
				]
			],
			'checkuser' => [
				'memberConditions' => [
					// 2FA not required, user doesn't meet the other condition
					'&', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 1000 ]
				]
			],
			'sysop' => [
				'memberConditions' => [
					// 2FA required
					'&', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 0 ]
				]
			]
		] );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( 0 );
		$this->setService( 'UserEditTracker', $userEditTracker );

		$this->setUserGroupManagerMock( [ 'interface-admin', 'checkuser', 'sysop' ] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [ 'sysop' ], $groups2FA );
	}

	public function testGroupsRequiring2FA_no2FACondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'sysop' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
			],
		] );

		$this->setUserGroupManagerMock( [ 'sysop' ] );

		$userRequirementsChecker = $this->createMock( UserRequirementsConditionCheckerWith2FAAssumption::class );
		$userRequirementsChecker->expects( $this->never() )
			->method( 'recursivelyCheckCondition' );
		$this->setService( 'OATHAuth.UserConditionCheckerWith2FAAssumption', $userRequirementsChecker );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [], $groups2FA );
	}

	public function testGroupsRequiring2FA_crossWiki() {
		$this->setUserGroupManagerMock( [ 'sysop' ] );

		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) {
				if ( $setting === 'wgRestrictedGroups' && $wiki === 'remote-wiki' ) {
					return [
						'sysop' => [
							'memberConditions' => [ APCOND_OATH_HAS2FA ],
						],
					];
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;

		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$userRemote = UserIdentityValue::newRegistered( 1, 'TestUser', 'remote-wiki' );
		$this->assertSame( [], $checker->getGroupsRequiring2FA( $userLocal ) );
		$this->assertSame( [ 'sysop' ], $checker->getGroupsRequiring2FA( $userRemote ) );
	}

	private function setUserGroupManagerMock( array $userGroups ) {
		$userGroupManagerFactory = $this->createMock( UserGroupManagerFactory::class );
		$userGroupManagerFactory->method( 'getUserGroupManager' )
			->willReturnCallback( function () use ( $userGroups ) {
				$ugm = $this->createMock( UserGroupManager::class );
				$ugm->method( 'getUserGroups' )
					->willReturn( $userGroups );
				return $ugm;
			} );
		$this->setService( 'UserGroupManagerFactory', $userGroupManagerFactory );
	}
}
