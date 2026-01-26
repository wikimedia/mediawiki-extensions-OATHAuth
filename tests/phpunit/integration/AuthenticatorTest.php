<?php

namespace MediaWiki\Extension\WebAuthn\Tests\Integration;

use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\WebAuthn\Authenticator
 * @group Database
 */
class AuthenticatorTest extends MediaWikiIntegrationTestCase {

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
			Authenticator::class,
			Authenticator::factory( $this->getMockUser() ),
		);
	}

	public function testIsEnabled() {
		$this->assertFalse(
			Authenticator::factory( $this->getMockUser() )->isEnabled()
		);
	}

	public function testInPasskeyMode() {
		$this->setMwGlobals( 'wgOATHNewPasskeyFeatures', true );
		$auth = Authenticator::factory( $this->getMockUser(), null, true );
		$res = TestingAccessWrapper::newFromObject( $auth )->inPasskeyMode();
		$this->assertTrue( $res );
	}
}
