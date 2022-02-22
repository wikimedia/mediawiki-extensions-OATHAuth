<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use Base32\Base32;
use UnexpectedValueException;

/**
 * Wrapper around sodium's cryptobox to encrypt and decrypt the OTP secret
 */
class EncryptionHelper {

	/**
	 * Whether encryption is configured/enabled
	 *
	 * @return bool
	 */
	public static function isEnabled(): bool {
		global $wgOATHSecretKey;
		return extension_loaded( 'sodium' )
			&& strlen( $wgOATHSecretKey ) === ( SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2 )
			&& ctype_xdigit( $wgOATHSecretKey );
	}

	/**
	 * Get the encryption secret key as bytes
	 *
	 * @return string
	 */
	private static function getKey() {
		global $wgOATHSecretKey;
		return hex2bin( $wgOATHSecretKey );
	}

	/**
	 * Decrypt the given ciphertext
	 *
	 * @param string $ciphertext base32 encoded
	 * @param string $nonce base32 encoded
	 * @return string
	 * @throws UnexpectedValueException When decryption fails
	 */
	public static function decrypt( string $ciphertext, string $nonce ) {
		$plaintext = sodium_crypto_secretbox_open(
			Base32::decode( $ciphertext ),
			Base32::decode( $nonce ),
			self::getKey()
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
	public static function encrypt( string $plaintext ) {
		// Generate a unique nonce
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, self::getKey() );
		return [
			'secret' => Base32::encode( $ciphertext ),
			'nonce' => Base32::encode( $nonce ),
		];
	}
}
