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
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use SpecialPageTestBase;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\OATHManage
 */
class OATHManageTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage() {
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new OATHManage(
			$services->getUserRepository(),
			$services->getModuleRegistry(),
			$this->getServiceContainer()->getAuthManager(),
			$this->getServiceContainer()->getUserGroupManager()
		);
	}

	public function testPageLoadsWithSummary() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $output ] = $this->executeSpecialPage( '', null, null, $user );
		$this->assertStringContainsString( '(oathmanage-summary)', $output );
	}

	public function testTOTPEnableCreationForm() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		$request = new FauxRequest(
			[ 'action' => 'enable', 'module' => 'totp' ],
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-step1', $output );
	}

	public function testTOTPDisableForm() {
		$user = $this->getTestUser()->getUser();
		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$request = new FauxRequest(
			[ 'action' => 'disable', 'module' => 'totp' ]
		 );

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-disable-method-warning', $output );
	}

	public function testRecoveryCodeFormRenders() {
		$user = $this->getTestUser()->getUser();
		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$request = new FauxRequest(
			[ 'module' => 'recoverycodes' ]
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-recoverycodes-regenerate-warning', $output );
	}

	public function testMaxKeysPerUser() {
		$user = $this->getTestUser();
		$maxTestKeys = 5;
		$this->setMwGlobals( 'wgOATHMaxKeysPerUser', $maxTestKeys );
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		for ( $i = 0; $i < 6; $i++ ) {
			$key = TOTPKey::newFromRandom();
			$userRepository->createKey(
				$userRepository->findByUser( $user->getUserIdentity() ),
				OATHAuthServices::getInstance( $this->getServiceContainer() )
					->getModuleRegistry()
					->getModuleByKey( 'totp' ),
				$key->jsonSerialize(),
				'127.0.0.1'
			);
		}

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 6, $oathUser->getKeys() );

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user->getUser() );
		$request = new FauxRequest(
			[ 'action' => 'enable', 'module' => 'totp' ]
		 );

		$this->expectException( ErrorPageError::class );
		$this->expectExceptionMessage( wfMessage( 'oathauth-max-keys-exceeded-message', $maxTestKeys ) );
		$this->executeSpecialPage( '', $request, null, $user->getUser() );
	}

	public function testPasskeysSectionHiddenWhenFeatureDisabled() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		$this->overrideConfigValue( 'OATHNewPasskeyFeatures', false );

		[ $output ] = $this->executeSpecialPage( '', null, null, $user );

		// Should NOT show the header at all
		$this->assertStringNotContainsString( '(oathauth-passkeys-header)', $output );
	}

	public function testPasskeysSectionAllowsAddingPasskeysWhenUserHas2fa() {
		// Setup user + existing TOTP key
		$user = $this->getTestUser()->getUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$totpKey = TOTPKey::newFromRandom();
		$userRepo->createKey(
			$userRepo->findByUser( $user ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$totpKey->jsonSerialize(),
			'127.0.0.1'
		);

		$this->overrideConfigValue( 'OATHNewPasskeyFeatures', true );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		RequestContext::getMain()->setLanguage( 'qqx' );

		[ $output ] = $this->executeSpecialPage( '', null, null, $user );

		$this->assertStringContainsString( '(oathauth-passkeys-header)', $output );
		$this->assertStringContainsString( '(oathauth-passkeys-add)', $output );
		$this->assertStringNotContainsString( '(oathauth-passkeys-no2fa)', $output );
	}

	public function testDeleteLastKeyWithCorrectConfirmation() {
		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = TOTPKey::newFromRandom();
		$userRepo->createKey(
		$userRepo->findByUser( $user->getUserIdentity() ),
		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getModuleRegistry()
			->getModuleByKey( 'totp' ),
		$key->jsonSerialize(),
		'127.0.0.1'
		);

		$oathUser = $userRepo->findByUser( $user->getUser() );
		$this->assertCount( 1, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$confirmText = wfMessage( 'oathauth-authenticator-delete-text' )
		->inLanguage( 'qqx' )
		->text();

		$token = $session->getToken( '' );

		$request = new FauxRequest(
		[
			'action' => 'delete',
			'module' => 'totp',
			'keyId' => $keyId,
			'wpremove-confirm-box' => $confirmText,
			'wpEditToken' => $token,
		],
		true,
		$session
		);

		$this->executeSpecialPage( '', $request, null, $user->getUser() );

		$oathUser = $userRepo->findByUser( $user->getUser() );
		$this->assertCount( 0, $oathUser->getKeys() );
	}

	public function testDeleteLastKeyWithWrongConfirmation() {
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$userRepository->createKey(
		$userRepository->findByUser( $testUser->getUserIdentity() ),
		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getModuleRegistry()
			->getModuleByKey( 'totp' ),
		TOTPKey::newFromRandom()->jsonSerialize(),
		'127.0.0.1'
		);

		$oathUser = $userRepository->findByUser( $user );
		$this->assertCount( 1, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$session = $context->getRequest()->getSession();

		$request = new FauxRequest(
		[
			'action' => 'delete',
			'keyId' => $keyId,
			'wpremove-confirm-box' => 'wrong-string',
			'wpEditToken' => $session->getToken( '' ),
		],
		true,
		$session
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );

		$oathUser = $userRepository->findByUser( $user );
		$this->assertCount( 1, $oathUser->getKeys() );

		$this->assertStringContainsString( 'oathauth-delete-wrong-confirm-message', $output );
	}

	public function testDeleteNonLastKeyWithoutConfirmation() {
		$user = $this->getTestUser();
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		for ( $i = 0; $i < 2; $i++ ) {
			$key = TOTPKey::newFromRandom();
			$userRepository->createKey(
				$userRepository->findByUser( $user->getUserIdentity() ),
				OATHAuthServices::getInstance( $this->getServiceContainer() )
					->getModuleRegistry()
					->getModuleByKey( 'totp' ),
				$key->jsonSerialize(),
				'127.0.0.1'
			);
		}

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 2, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user->getUser() );
		$session = $context->getRequest()->getSession();
		$request = new FauxRequest(
			[
				'action' => 'delete',
				'module' => 'totp',
				'keyId' => $keyId
			],
			true,
			$session
		);

		$this->executeSpecialPage( '', $request, null, $user->getUser() );

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 1, $oathUser->getKeys() );
	}
}
