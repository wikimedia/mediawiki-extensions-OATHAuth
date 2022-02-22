<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Key;

use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\EncryptionHelper
 */
class EncryptionHelperTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
		// Generated once using `MWCryptRand::generateHex( 64 );`
		$this->setMwGlobals( 'wgOATHSecretKey', 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a' );
	}

	public function testEncryptionHelper() {
		$this->assertTrue( EncryptionHelper::isEnabled() );
		$magicPhrase = 'super secret phrase';
		$encrypted = EncryptionHelper::encrypt( $magicPhrase );
		$decrypted = EncryptionHelper::decrypt( $encrypted['secret'], $encrypted['nonce'] );
		$this->assertSame( $magicPhrase, $decrypted );
	}

	public function testInvalidEncryptionAttempt() {
		$this->assertTrue( EncryptionHelper::isEnabled() );
		$magicPhrase = 'super secret phrase';
		$invalidMagicPhrase = 'a different phrase that isn\'t encrypted';
		$encrypted = EncryptionHelper::encrypt( $magicPhrase );
		$this->expectException( UnexpectedValueException::class );
		$decrypted = EncryptionHelper::decrypt( $invalidMagicPhrase, $encrypted['nonce'] );
	}
}
