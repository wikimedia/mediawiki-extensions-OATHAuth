<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Unit\Key;

use Cose\Algorithms;
use Cose\Key\Key;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\WebAuthnKey
 */
class WebAuthnKeyTest extends MediaWikiUnitTestCase {

	// phpcs:disable Generic.Files.LineLength.TooLong

	/** base64 encoded 2048-bit SHA1 key */
	public const string KEY_RS1_2048 =
		'pAEDAzn//iBZAQDHMVUPQA9Md0HpR62P5YJfsfM8RlyfLE5orSSL0rjFbg6gLiVCy4qyeCZyMr3fieO2lhH2wZcJnZ6Fsr5RStEJ2v4LihxqDTjqVdPaIZaqnaksfC7fErpnTl/y2yuVMZNLYGb6IEtTNYbapI3omoo7/zfxo/Vv3zE2yCm/HOdkKt9jFQX7TT16LkkAX3pq9Uoe5vzehhE31EtKtTl3vNl2Q6pvlEG0iocqskP2dlstzf21mhJUixBm81mY493kcl08cZcBpuiAIaHNF1nCiLAs9wN3jwwLg7WHq8PAx3R6Jk9vCEy9NsqfV7u/ycTIG0lkGKrhnp737njQrQIUzNJnIUMBAAE=';

	/** base64 encoded 2048-bit RSA256 key */
	public const string KEY_RS256_2048 =
		'pAEDAzkBACBZAQDdgsKhrJfNskRwTNsc2g7BDdNjrvDqQNjKFV4wAGgDzU1cIOG7sfjMvmpWer4VURCogT8LpGhM2TphcOQVWO20xY/qZzrSLBKGCo9b8NJy7264GRiRc5Yd/g+G8tlSbP4w+/ouIxk8N9xSkM3E1/U98AAZ4GTxiRivLKGCcks+z9DMVgz8D3doAC/qvooxQzMbXLOBtVY6ZsSf68pPIv17b0naolUQbKSQ6ZFa/DwrT/b778NXn3pWQt6CaimLi1cC9qDDM/bbNSzUsr/ybB6HgEcf79WQnmIyvkuY4FjoMhxWClDBVqT4e8no1ryqO+4VLjhgMWYmckZFpo9LEElDIUMBAAE=';

	/** base64 encoded 512-bit RSA256 key */
	public const string KEY_RS256_512 = 'pAEDAzkBACBYQMfUDlBl/SuI7uZTuZ5n5OkV1eQL8dSAdVMbDGGmpzG926gWFjNY0KEViYikZ9A4Ooj2A8vOTypqoEgFrb/214EhQwEAAQ==';

	/** base64 encoded 521-bit ES512 key */
	public const string KEY_ES512 = 'pQECAzgjIAMhWEIAIfAOXVPA4FrdlFcp7UFIda0P0mjfXagxwhz8qHrC9Quj8GF6JweW+mWDgKPUyBkDvUQjrh4Wg/vlgO8n3w8SwzMiWEIAxPWJPBsSeQN5YOyRqt8J/DomhjRQclrRxFsdEMe6E9KfEWSq48zsDleiVxnhXdzBd0xJLipEs50FyLvEgFXSZnk=';

	/** @dataProvider providePublicKeys */
	public function testIsDeprecatedPublicKeyAlgorithm( Key $key, int $algorithm, bool $deprecated ) {
		$this->assertEquals( $deprecated, WebAuthnKey::isDeprecatedPublicKeyAlgorithm( $algorithm ) );
	}

	public function providePublicKeys(): array {
		return [
			[
				WebAuthnKey::getCoseKey( base64_decode( self::KEY_RS1_2048 ) ),
				Algorithms::COSE_ALGORITHM_RS1,
				true,
			],
			[
				WebAuthnKey::getCoseKey( base64_decode( self::KEY_RS256_2048 ) ),
				Algorithms::COSE_ALGORITHM_RS256,
				false,
			]
		];
	}

	/** @dataProvider providePublicKeys */
	public function testGetPublicKeyAlgorithm( Key $key, int $algorithm ) {
		$this->assertEquals(
			$algorithm,
			WebAuthnKey::getPublicKeyAlgorithm( $key )
		);
	}

	public function testGetKeyLengthIfRsa() {
		$this->assertEquals(
			2048,
			WebAuthnKey::getRsaKeyLength(
				WebAuthnKey::getCoseKey( base64_decode( self::KEY_RS256_2048 ) )
			)
		);
		$this->assertEquals( 512,
			WebAuthnKey::getRsaKeyLength(
				WebAuthnKey::getCoseKey( base64_decode( self::KEY_RS256_512 ) )
			)
		);

		$this->assertNull(
			WebAuthnKey::getRsaKeyLength(
				WebAuthnKey::getCoseKey( base64_decode( self::KEY_ES512 ) )
			)
		);
	}
}
