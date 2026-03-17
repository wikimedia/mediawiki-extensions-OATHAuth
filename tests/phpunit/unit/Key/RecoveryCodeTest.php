<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Unit\Key;

use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCode;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCode
 */
class RecoveryCodeTest extends MediaWikiUnitTestCase {

	public function testAvoidsReencryption() {
		$encryptionHelper = $this->createStub( EncryptionHelper::class );
		$code = new RecoveryCode( $encryptionHelper, 'CODE', [ 'foo' => 'bar' ], 'ENCRYPTED', 'NONCE' );

		$this->assertSame( 'CODE', $code->getCode() );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
		$this->assertSame( 'NONCE', $code->getNonce() );
		$this->assertSame( [ 'foo' => 'bar' ], $code->getData() );

		$this->assertTrue( $code->test( 'CODE' ) );
	}

	public function testEncryption() {
		$encryptionHelper = $this->createMock( EncryptionHelper::class );
		$encryptionHelper->method( 'isEnabled' )
			->willReturn( true );
		$encryptionHelper->method( 'encrypt' )
			->willReturnCallback( static function ( $code, $nonce ) {
				if ( $code === 'CODE' && $nonce === 'NONCE' ) {
					return [ 'secret' => 'ENCRYPTED', 'nonce' => 'NONCE' ];
				}
				return [ 'secret' => 'WRONG', 'nonce' => $nonce ];
			} );
		$code = new RecoveryCode( $encryptionHelper, 'CODE' );

		$this->assertSame( 'CODE', $code->getCode() );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
		$this->assertSame( 'NONCE', $code->getNonce() );
	}

	public function testEncryptionThrowsIfDisabled() {
		$encryptionHelper = $this->createMock( EncryptionHelper::class );
		$encryptionHelper->method( 'isEnabled' )
			->willReturn( false );
		$encryptionHelper->method( 'encrypt' )
			->willReturnCallback( static function ( $code, $nonce ) {
				if ( $code === 'CODE' && $nonce === 'NONCE' ) {
					return [ 'secret' => 'ENCRYPTED', 'nonce' => 'NONCE' ];
				}
				return [ 'secret' => 'WRONG', 'nonce' => $nonce ];
			} );
		$code = new RecoveryCode( $encryptionHelper, 'CODE' );

		$this->expectException( RuntimeException::class );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
	}
}
