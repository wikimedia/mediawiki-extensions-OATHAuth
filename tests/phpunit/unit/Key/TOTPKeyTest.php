<?php
declare( strict_types=1 );
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Unit\Key;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\TOTPKey
 */
class TOTPKeyTest extends MediaWikiUnitTestCase {

	public static function provideRemove(): array {
		return [
			// no text to be removed
			[
				'foo',
				'foo'
			],
			// trailing = should be removed
			[
				'foo====',
				'foo'
			],
		];
	}

	/** @dataProvider provideRemove */
	public function testRemoveBase32Padding( string $input, string $output ): void {
		$this->assertEquals( $output, TOTPKey::removeBase32Padding( $input ) );
	}

	public static function provideAdd(): array {
		return [
			// padded to a string length of 8 chars
			[
				'1234',
				'1234===='
			],
			// already a string length of 8 chars, so not changed
			[
				'12345678',
				'12345678'
			],
			// already padded to 8 chars, so not changed
			[
				'1234====',
				'1234===='
			],
			// some padding, but not to 8 chars...
			[
				'1234===',
				'1234===='
			],
			// some padding, but to more than 8 chars... padding trimmed, and reset to correct length
			[
				'1234=====',
				'1234===='
			],
		];
	}

	/** @dataProvider provideAdd */
	public function testAddBase32Padding( string $input, string $output ): void {
		$this->assertEquals( $output, TOTPKey::addBase32Padding( $input ) );
	}
}
