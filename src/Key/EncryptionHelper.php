<?php
declare( strict_types=1 );
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use Base32\Base32;
use MediaWiki\Config\ServiceOptions;
use RuntimeException;
use UnexpectedValueException;

/**
 * Wrapper around sodium's cryptobox to encrypt and decrypt the OTP secret
 */
class EncryptionHelper {
	/** @internal */
	public const CONSTRUCTOR_OPTIONS = [
		'OATHSecretKey',
	];

	private string|false $key;

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->key = $this->options->get( 'OATHSecretKey' );
	}

	/** @return bool Whether encryption is enabled. */
	public function isEnabled(): bool {
		if ( !$this->key ) {
			return false;
		}

		if ( !extension_loaded( 'sodium' ) ) {
			// @codeCoverageIgnoreStart
			throw new RuntimeException( 'OATHAuth encryption requires ext-sodium' );
			// @codeCoverageIgnoreEnd
		}

		if ( strlen( $this->key ) !== ( SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2 ) ) {
			throw new UnexpectedValueException( 'OATHAuth encryption key has invalid length' );
		}

		if ( !ctype_xdigit( $this->key ) ) {
			throw new UnexpectedValueException( 'OATHAuth encryption key must be in hexadecimal' );
		}

		return true;
	}

	/**
	 * Get the encryption secret key as bytes
	 */
	private function getKey(): string {
		return sodium_hex2bin( $this->key );
	}

	/**
	 * Decrypt the given ciphertext
	 *
	 * @param string $ciphertext base32 encoded
	 * @param string $nonce base32 encoded
	 * @return string
	 * @throws UnexpectedValueException When decryption fails
	 */
	public function decrypt( string $ciphertext, string $nonce ) {
		$plaintext = sodium_crypto_secretbox_open(
			Base32::decode( $ciphertext ),
			Base32::decode( $nonce ),
			$this->getKey(),
		);
		if ( $plaintext === false ) {
			throw new UnexpectedValueException( 'Unable to decrypt ciphertext' );
		}
		return $plaintext;
	}

	/**
	 * Encrypt the given plaintext
	 *
	 * @param string $plaintext What to encrypt
	 * @return string[] Array with 'secret' and 'nonce' keys, both base32 encoded
	 */
	public function encrypt( string $plaintext, string $nonce = '' ) {
		// Generates a unique nonce
		if ( $nonce === '' ) {
			$nonce = $this->generateNonce();
		}
		// Nonces are expected to be Base32-encoded
		$nonce = Base32::decode( $nonce );

		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $this->getKey() );
		return [
			'secret' => Base32::encode( $ciphertext ),
			'nonce' => Base32::encode( $nonce ),
		];
	}

	/**
	 * Generate unique base32-encoded nonce
	 */
	public function generateNonce(): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return Base32::encode( $nonce );
	}

	/**
	 * Shouldn't be used in normal circumstances; exists for maintenance script purposes
	 */
	public function setEncryptionKey( string $key ): void {
		$this->key = $key;
	}
}
