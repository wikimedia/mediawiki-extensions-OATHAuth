<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\DisableOATHForUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use SpecialPageTestBase;

/**
 * @author Taavi Väänänen & Dreamy Jazz
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\DisableOATHForUser
 */
class DisableOATHForUserTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage(): DisableOATHForUser {
		return new DisableOATHForUser(
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getCentralIdLookup()
		);
	}

	public function testFormLoads() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-disable-for-user)', $html );
		$this->assertStringContainsString( '(oathauth-disable-intro)', $html );
		$this->assertStringContainsString( '(oathauth-enteruser)', $html );
		$this->assertStringContainsString( '(oathauth-enterdisablereason)', $html );
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

	public function testFailsForNonexistentUser() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => 'I am required!',
					// Sharks are amazing, so no shark haters exist
					'user' => 'Shark hater',
				],
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-user-not-found)', $html );
	}

	public function testFailsForUserWithTwoFactorDisabled() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		$otherUser = $this->getTestUser()->getUser();

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => 'I am required!',
					'user' => $otherUser->getName(),
				],
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-user-not-does-not-have-oath-enabled)', $html );
	}

	public function testDisabledTwoFactorAuth() {
		$otherUser = $this->getTestUser()->getUser();

		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getUserRepository()
			->findByUser( $otherUser )
			->addKey( TOTPKey::newFromRandom() );

		$reason = 'I am required!';

		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => $reason,
					'user' => $otherUser->getName(),
				],
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( "(oathauth-disabledoath)", $html );

		$logEntry = $this->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'oath',
				'log_action' => 'disable-other',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $otherUser->getName() ),
			] )
			->fetchField();
		$this->assertNotNull( $logEntry );
	}
}
