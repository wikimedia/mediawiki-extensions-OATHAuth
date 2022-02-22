<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWikiIntegrationTestCase;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\TOTPKey
 */
class TOTPKeyTest extends MediaWikiIntegrationTestCase {
	public function encryptionTestSetup() {
		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
		$this->setMwGlobals( 'wgOATHSecretKey', 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a' );
		$this->assertTrue( EncryptionHelper::isEnabled() );
	}

	public function testDeserialization() {
		$key = TOTPKey::newFromRandom();
		$deserialized = TOTPKey::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getSecret(), $deserialized->getSecret() );
		$this->assertSame( $key->getScratchTokens(), $deserialized->getScratchTokens() );
	}

	public function testIsScratchToken() {
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

	public function testNewFromArrayWithNonceNoEncryption() {
		$this->expectException( UnexpectedValueException::class );
		$key = TOTPKey::newFromArray( [
			'secret' => '123456',
			'scratch_tokens' => [ '64SZLJTTPRI5XBUE' ],
			'nonce' => '789101112',
		] );
	}

	public function testNewFromArrayWithEncryption() {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();

		$key = TOTPKey::newFromArray( [
			'secret' => $data['secret'],
			'scratch_tokens' => $data['scratch_tokens'],
			'nonce' => $data['nonce'],
		] );

		$this->assertEquals( EncryptionHelper::decrypt( $data['secret'], $data['nonce'] ), $key->getSecret() );
	}

	public function testJsonSerializerWithEncryption() {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'secret', $data );
		$this->assertArrayHasKey( 'scratch_tokens', $data );
		$this->assertCount( TOTPKey::RECOVERY_CODES_COUNT, $data['scratch_tokens'] );
		$this->assertNotEquals( $data['secret'], $key->getSecret() );
	}

	public function testDoNotReencryptEncryptedKeyData() {
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
}
