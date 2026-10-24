<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\DeprecatedKeyException;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\Tests\Unit\Key\WebAuthnKeyTest as KeyUnitTest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\WebAuthnKey
 * @covers \MediaWiki\Extension\OATHAuth\Key\AuthKey
 * @group Database
 */
class WebAuthnKeyTest extends MediaWikiIntegrationTestCase {

	public const array KEY_DATA = [
		'userHandle' => 'fakeHandle',
		'friendlyName' => 'testKey',
		'counter' => 3,
		'transports' => [],
		'aaguid' => 'f7dcf1ec-76ad-49cb-ae2a-6d8ed6736f88',
		'publicKeyCredentialId' => 'none',
		'credentialPublicKey' => 'none',
	];

	public function testPasswordlessLoginPasskeysSupportsPasswordless(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);
		$key->setPasswordlessSupport( true );
		$this->assertTrue( $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysDoesntSupportPasswordless(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA + [ 'supportsPasswordless' => false ]
		);
		$this->assertFalse( $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysSupportPasswordlessMissing(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);
		$this->assertFalse( $key->supportsPasswordlessLogin() );
	}

	public function testJsonSerialize() {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);

		$this->assertEquals(
			$key,
			WebAuthnKey::newFromData(
				$key->jsonSerialize()
			)
		);
	}

	private function getOATHUser(): OATHUser {
		/** @var OATHUserRepository $repo */
		$repo = $this->getServiceContainer()->getService( 'OATHAuth.UserRepository' );
		return $repo->findByUser( $this->getTestUser()->getUser() );
	}

	public function testExceptionIfRsaIsShortSHA1() {
		$this->expectException( DeprecatedKeyException::class );
		WebAuthnKey::newKey()->checkIfDeprecated(
			base64_decode( KeyUnitTest::KEY_RS1_2048 ),
			$this->getOATHUser(),
			true,
			true
		);
	}

	public function testExceptionIfRsaIsShort() {
		$this->expectException( DeprecatedKeyException::class );
		WebAuthnKey::newKey()->checkIfDeprecated(
			base64_decode( KeyUnitTest::KEY_RS256_512 ),
			$this->getOATHUser(),
			true,
			true
		);
	}

	public function testNoExceptionifRsaIsNotShort() {
		$this->expectNotToPerformAssertions();
		WebAuthnKey::newKey()->checkIfDeprecated(
			base64_decode( KeyUnitTest::KEY_RS256_2048 ),
			$this->getOATHUser(),
			true,
			true
		);
	}

	public function testNoExceptionForES512() {
		$this->expectNotToPerformAssertions();
		WebAuthnKey::newKey()->checkIfDeprecated(
			base64_decode( KeyUnitTest::KEY_ES512 ),
			$this->getOATHUser(),
			true,
			true
		);
	}
}
