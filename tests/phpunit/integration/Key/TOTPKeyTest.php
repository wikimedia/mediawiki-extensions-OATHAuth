<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use InvalidArgumentException;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWikiIntegrationTestCase;
use SodiumException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys
 * @covers \MediaWiki\Extension\OATHAuth\Key\TOTPKey
 */
class TOTPKeyTest extends MediaWikiIntegrationTestCase {
	public function encryptionTestSetup() {
		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
		$this->setMwGlobals( 'wgOATHSecretKey', 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a' );
		$this->getServiceContainer()->resetServiceForTesting( 'OATHAuth.EncryptionHelper' );
		$this->assertTrue(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->isEnabled(),
		);
	}

	public function testDeserialization(): void {
		$this->setMwGlobals( 'wgOATHAllowMultipleModules', false );
		$key = TOTPKey::newFromRandom();
		$deserialized = TOTPKey::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getSecret(), $deserialized->getSecret() );
		$this->assertSame( $key->getScratchTokens(), $deserialized->getScratchTokens() );
	}

	public function testIsScratchToken(): void {
		$key = TOTPKey::newFromArray( [
			'secret' => '123456',
			'scratch_tokens' => [ '64SZLJTTPRI5XBUE' ],
		] );
		$this->assertTrue( $key->isScratchToken( '64SZLJTTPRI5XBUE' ) );
		// Whitespace is stripped
		$this->assertTrue( $key->isScratchToken( ' 64SZLJTTPRI5XBUE ' ) );
		// Wrong token
		$this->assertFalse( $key->isScratchToken( 'WIQGC24UJUFXQDW4' ) );
	}

	public function testNewFromArrayWithNonceNoEncryption(): void {
		// bad nonce value will throw a sodium exception
		$this->encryptionTestSetup();

		$this->expectException( SodiumException::class );
		$key = TOTPKey::newFromArray( [
			'secret' => '123456',
			'scratch_tokens' => [ '64SZLJTTPRI5XBUE' ],
			'nonce' => '789101112',
		] );
	}

	public function testNewFromArrayWithEncryption(): void {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();

		$key = TOTPKey::newFromArray( [
			'secret' => $data['secret'],
			'scratch_tokens' => $data['scratch_tokens'],
			'nonce' => $data['nonce'],
		] );

		$this->assertEquals(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->decrypt( $data['secret'], $data['nonce'] ),
			$key->getSecret(),
		);
	}

	public function testNewFromFunctionsMultiModules(): void {
		$this->setMwGlobals( 'wgOATHAllowMultipleModules', true );
		$key = TOTPKey::newFromArray( [ 'scratch_tokens' => [ 'ABCDEFGHI=' ] ] );
		$this->assertSame( null, $key );

		$this->setMwGlobals( 'wgOATHAllowMultipleModules', false );
		$key = TOTPKey::newFromArray( [ 'secret' => 'ABCDEFGHI=' ] );
		$this->assertSame( null, $key );
	}

	public function testJsonSerializerWithEncryption(): void {
		$this->encryptionTestSetup();
		$this->setMwGlobals( 'wgOATHAllowMultipleModules', false );

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'secret', $data );
		$this->assertArrayHasKey( 'scratch_tokens', $data );
		$this->assertCount( TOTPKey::RECOVERY_CODES_COUNT, $data['scratch_tokens'] );
		$this->assertNotEquals( $data['secret'], $key->getSecret() );
	}

	public function testDoNotReencryptEncryptedKeyData(): void {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();
		$encryptedData = $key->getEncryptedSecretAndNonce();
		$oldEncryptedSecret = $encryptedData[0];
		$oldNonce = $encryptedData[1];

		$newData = $key->jsonSerialize();
		$this->assertEquals( $oldEncryptedSecret, $newData['secret'] );
		$this->assertEquals( $oldNonce, $newData['nonce'] );
	}

	public function testGetSetFunctions(): void {
		$key = TOTPKey::newFromRandom();
		$this->assertNull( $key->getId() );
		$this->assertIsString( $key->getSecret() );
		$this->assertEquals( 48, strlen( $key->getSecret() ) );

		$testTokens = [ 'ABCDEFGHIJKLMNO1', 'ABCDEFGHIJKLMNO1', 'ABCDEFGHIJKLMNO1' ];
		$key->setScratchTokens( $testTokens );
		$this->assertSame( $testTokens, $key->getScratchTokens() );

		$currentTokens = $key->getScratchTokens();
		$key->regenerateScratchTokens();
		$this->assertNotSame( $currentTokens, $key->getScratchTokens() );

		$this->assertSame( TOTP::MODULE_NAME, $key->getModule() );
	}

	public function testVerify(): void {
		$mockOATHUser = $this->createMock( OATHUser::class );

		$testData1 = [];
		$key = TOTPKey::newFromRandom();
		$this->assertSame( false, $key->verify( $testData1, $mockOATHUser ) );

		$key->regenerateScratchTokens();
		$testData2 = [ 'token' => 'bad_token' ];
		$this->assertSame( false, $key->verify( $testData2, $mockOATHUser ) );

		// oathUser without actual keys
		$this->setMwGlobals( 'wgOATHAllowMultipleModules', false );
		$this->expectException( InvalidArgumentException::class );
		$scratchTokens = $key->getScratchTokens();
		$testData3 = [ 'token' => array_shift( $scratchTokens ) ];
		$this->assertSame( true, $key->verify( $testData3, $mockOATHUser ) );
	}
}
