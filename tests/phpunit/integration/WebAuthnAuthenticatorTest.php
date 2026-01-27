<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator
 * @group Database
 */
class WebAuthnAuthenticatorTest extends MediaWikiIntegrationTestCase {

	private function getMockUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getId' )
			->willReturn( 1 );
		$user->method( 'getName' )
			->willReturn( 'User' );
		return $user;
	}

	public function testFactory() {
		$this->assertInstanceOf(
			WebAuthnAuthenticator::class,
			WebAuthnAuthenticator::factory( $this->getMockUser() ),
		);
	}

	public function testIsEnabled() {
		$this->assertFalse(
			WebAuthnAuthenticator::factory( $this->getMockUser() )->isEnabled()
		);
	}

	public function testInPasskeyMode() {
		$this->setMwGlobals( 'wgOATHNewPasskeyFeatures', true );
		$auth = WebAuthnAuthenticator::factory( $this->getMockUser(), null, true );
		$res = TestingAccessWrapper::newFromObject( $auth )->inPasskeyMode();
		$this->assertTrue( $res );
	}
}
