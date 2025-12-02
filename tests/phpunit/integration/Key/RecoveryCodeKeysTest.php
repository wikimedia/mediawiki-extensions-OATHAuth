<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\Tests\Integration\EncryptionTestTrait;
use MediaWiki\Request\WebRequest;
use MediaWikiIntegrationTestCase;
use SodiumException;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys
 * @covers \MediaWiki\Extension\OATHAuth\Key\EncryptionHelper
 * @covers \MediaWiki\Extension\OATHAuth\Module\TOTP
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 * @group Database
 */
class RecoveryCodeKeysTest extends MediaWikiIntegrationTestCase {
	use EncryptionTestTrait;

	private const NONCE = '7LRMXBX2AKPYWDBUBDHCN2WCFJXFX4XR2GZRV7Q=';
	private const VALID_RECOVERY_KEY = [ '88as3hh433jj2o22' ];
	private const INVALID_RECOVERY_KEY = [ '88asdyf09sadf' ];

	public function testDeserializationUnencrypted() {
		$this->assertNull( RecoveryCodeKeys::newFromArray( [] ) );

		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 1 );
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$key->regenerateRecoveryCodeKeys();
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$key->regenerateRecoveryCodeKeys();
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );
	}

	public function testNewFromArrayWithNonce() {
		$this->setMwGlobals( 'wgOATHSecretKey', false );
		$this->expectException( UnexpectedValueException::class );
		$keyArray = [
			'recoverycodekeys' => self::INVALID_RECOVERY_KEY,
			'nonce' => 'bad_value',
		];
		RecoveryCodeKeys::newFromArray( $keyArray );

		$this->encryptionIntegrationTestSetup();

		$this->expectException( SodiumException::class );
		RecoveryCodeKeys::newFromArray( $keyArray );

		$key = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => self::VALID_RECOVERY_KEY,
			'nonce' => self::NONCE,
		] );
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );
	}

	public function testNewFromArrayWithEncryption() {
		$this->encryptionIntegrationTestSetup();

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();

		$keysPostSerialization = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => $data['recoverycodekeys'],
			'nonce' => $data['nonce'],
		] );

		$this->assertEquals(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->decryptStringArrayValues( $data['recoverycodekeys'], $data['nonce'] ),
			$keysPostSerialization->getRecoveryCodeKeys(),
		);
	}

	public function testJsonSerializerWithEncryption() {
		$this->encryptionIntegrationTestSetup();
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'recoverycodekeys', $data );
		$config = OATHAuthServices::getInstance( $this->getServiceContainer() )->getConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $data['recoverycodekeys'] );
		$this->assertNotEquals( $data['recoverycodekeys'], $keys->getRecoveryCodeKeys() );
	}

	public function testDoNotReencryptEncryptedKeyData() {
		$this->encryptionIntegrationTestSetup();

		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->jsonSerialize();

		[ $oldEncryptedRecoveryCodes, $oldNonce ] = $keys->getRecoveryCodeKeysEncryptedAndNonce();

		$newData = $keys->jsonSerialize();
		$this->assertEquals( $oldEncryptedRecoveryCodes, $newData['recoverycodekeys'] );
		$this->assertEquals( $oldNonce, $newData['nonce'] );
	}

	public function testGetSetFunctions(): void {
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$this->assertNull( $keys->getId() );
		$this->assertSame( [], $keys->getRecoveryCodeKeys() );

		$currentRecCodeKeys = $keys->getRecoveryCodeKeys();
		$keys->regenerateRecoveryCodeKeys();
		$this->assertNotSame( $currentRecCodeKeys, $keys->getRecoveryCodeKeys() );

		$this->assertSame( RecoveryCodes::MODULE_NAME, $keys->getModule() );
	}

	public function testVerify(): void {
		$mockWebRequest = $this->createMock( WebRequest::class );
		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'GetSecurityLogContext',
			static function ( array $info, array &$context ) {
				$context['foo'] = 'bar';
			}
		);
		$mockWebRequest->method( 'getSecurityLogContext' )
			->willReturn( [ 'clientIp' => '1.1.1.1' ] );

		$testData = [];
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$this->assertFalse( $keys->verify( $testData, $mockOATHUser ) );

		$keys->regenerateRecoveryCodeKeys();

		$testData = [ 'recoverycode' => 'bad_token' ];
		$this->assertFalse( $keys->verify( $testData, $mockOATHUser ) );

		$config = OATHAuthServices::getInstance( $this->getServiceContainer() )->getConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $keys->getRecoveryCodeKeys() );

		// Test that verify works with a generated key
		$testData = [ 'recoverycode' => $keys->getRecoveryCodeKeys()[0] ];
		$this->assertTrue( $keys->verify( $testData, $mockOATHUser ) );
	}

	public function testIsValidRecoveryCode(): void {
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [ '64SZLJTTPRI5XBUE' ] ] );
		$this->assertTrue( $key->isValidRecoveryCode( '64SZLJTTPRI5XBUE' ) );
		// Whitespace is stripped
		$this->assertTrue( $key->isValidRecoveryCode( ' 64SZLJTTPRI5XBUE ' ) );
		// Wrong token
		$this->assertFalse( $key->isValidRecoveryCode( 'WIQGC24UJUFXQDW4' ) );
	}
}
