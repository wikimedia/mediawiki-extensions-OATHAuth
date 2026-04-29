<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Unit\Key;

use Cose\Algorithms;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\WebAuthnKey
 */
class WebAuthnKeyTest extends MediaWikiUnitTestCase {

	// phpcs:disable Generic.Files.LineLength.TooLong
	public const string KEY_RS1 =
		'pAEDAzn//iBZAQDHMVUPQA9Md0HpR62P5YJfsfM8RlyfLE5orSSL0rjFbg6gLiVCy4qyeCZyMr3fieO2lhH2wZcJnZ6Fsr5RStEJ2v4LihxqDTjqVdPaIZaqnaksfC7fErpnTl/y2yuVMZNLYGb6IEtTNYbapI3omoo7/zfxo/Vv3zE2yCm/HOdkKt9jFQX7TT16LkkAX3pq9Uoe5vzehhE31EtKtTl3vNl2Q6pvlEG0iocqskP2dlstzf21mhJUixBm81mY493kcl08cZcBpuiAIaHNF1nCiLAs9wN3jwwLg7WHq8PAx3R6Jk9vCEy9NsqfV7u/ycTIG0lkGKrhnp737njQrQIUzNJnIUMBAAE=';
	public const string KEY_RS256 =
		'pAEDAzkBACBZAQDdgsKhrJfNskRwTNsc2g7BDdNjrvDqQNjKFV4wAGgDzU1cIOG7sfjMvmpWer4VURCogT8LpGhM2TphcOQVWO20xY/qZzrSLBKGCo9b8NJy7264GRiRc5Yd/g+G8tlSbP4w+/ouIxk8N9xSkM3E1/U98AAZ4GTxiRivLKGCcks+z9DMVgz8D3doAC/qvooxQzMbXLOBtVY6ZsSf68pPIv17b0naolUQbKSQ6ZFa/DwrT/b778NXn3pWQt6CaimLi1cC9qDDM/bbNSzUsr/ybB6HgEcf79WQnmIyvkuY4FjoMhxWClDBVqT4e8no1ryqO+4VLjhgMWYmckZFpo9LEElDIUMBAAE=';

		/** @dataProvider providePublicKeys */
	public function testIsDeprecatedPublicKeyAlgorithm( string $key, int $algorithm, bool $deprecated ) {
		$this->assertEquals( $deprecated, WebAuthnKey::isDeprecatedPublicKeyAlgorithm( $algorithm ) );
	}

	public function providePublicKeys() {
		return [
			[
				self::KEY_RS1,
				Algorithms::COSE_ALGORITHM_RS1,
				true,
			],
			[
				self::KEY_RS256,
				Algorithms::COSE_ALGORITHM_RS256,
				false,
			]
		];
	}

	/** @dataProvider providePublicKeys */
	public function testGetPublicKeyAlgorithm( string $key, int $algorithm ) {
		$this->assertEquals( $algorithm, WebAuthnKey::getPublicKeyAlgorithm( $key ) );
	}
}
