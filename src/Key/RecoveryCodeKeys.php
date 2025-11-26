<?php

namespace MediaWiki\Extension\OATHAuth\Key;

/**
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

use Base32\Base32;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\AuthKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use stdClass;
use UnexpectedValueException;

/**
 * Class representing a two-factor recovery code
 *
 * Recovery codes are tied to OATHUsers
 *
 * @ingroup Extensions
 */
class RecoveryCodeKeys extends AuthKey {
	/** @var string[] List of recovery codes */
	public $recoveryCodeKeys = [];

	/** @var string[] List of encrypted recovery codes */
	private $recoveryCodeKeysEncrypted = [];

	/** @var string optional nonce for encryption */
	private $nonce = '';

	/**
	 * Length (in bytes) that recovery codes should be
	 */
	private const RECOVERY_CODE_LENGTH = 10;

	/**
	 * @param array $data
	 * @return RecoveryCodeKeys|null on invalid data
	 * @throws UnexpectedValueException When encryption is not configured but db is encrypted
	 */
	public static function newFromArray( array $data ) {
		if ( !array_key_exists( 'recoverycodekeys', $data ) ) {
			return null;
		}
		if ( isset( $data['nonce'] ) ) {
			$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
			if ( !$encryptionHelper->isEnabled() ) {
				throw new UnexpectedValueException( 'Encryption is not configured but database has encrypted data' );
			}
			$data['recoverycodekeysencrypted'] = $data['recoverycodekeys'];
			$data['recoverycodekeys'] = $encryptionHelper->decryptStringArrayValues(
				$data['recoverycodekeys'],
				$data['nonce']
			);
		} else {
			$data['recoverycodekeysencrypted'] = [];
			$data['nonce'] = '';
		}

		return new static(
			$data['id'] ?? null,
			null,
			$data['created_timestamp'] ?? null,
			$data['recoverycodekeys'],
			$data['recoverycodekeysencrypted'],
			$data['nonce']
		);
	}

	/**
	 * @param ?int $id
	 * @param ?string $friendlyName
	 * @param ?string $createdTimestamp
	 * @param array $recoveryCodeKeys
	 * @param array $recoveryCodeKeysEncrypted
	 * @param string $nonce
	 */
	public function __construct(
		?int $id,
		?string $friendlyName,
		?string $createdTimestamp,
		array $recoveryCodeKeys,
		array $recoveryCodeKeysEncrypted,
		string $nonce = ''
	) {
		parent::__construct( $id, $friendlyName, $createdTimestamp );
		$this->recoveryCodeKeys = array_values( $recoveryCodeKeys );
		$this->recoveryCodeKeysEncrypted = array_values( $recoveryCodeKeysEncrypted );
		$this->nonce = $nonce;
	}

	public function getRecoveryCodeKeys(): array {
		return $this->recoveryCodeKeys;
	}

	public function getRecoveryCodeKeysEncryptedAndNonce(): array {
		return [ $this->recoveryCodeKeysEncrypted, $this->nonce ];
	}

	public function setRecoveryCodeKeysEncryptedAndNonce( array $recoveryCodeKeysEncrypted, string $nonce ): void {
		$this->recoveryCodeKeysEncrypted = $recoveryCodeKeysEncrypted;
		$this->nonce = $nonce;
	}

	/**
	 * @param array|stdClass $data
	 * @param OATHUser $user
	 */
	public function verify( $data, OATHUser $user ): bool {
		if ( !isset( $data['recoverycode'] ) ) {
			return false;
		}

		$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
		$logger = $this->getLogger();

		foreach ( $this->recoveryCodeKeys as $userRecoveryCode ) {
			if ( !hash_equals(
				$this->normaliseRecoveryCode( $data['recoverycode'] ),
				$userRecoveryCode
			) ) {
				continue;
			}

			$logger->info(
				// phpcs:ignore
				"OATHAuth {user} used a recovery code from {clientip}.", [
					'user' => $user->getUser()->getName(),
					'clientip' => $clientData['clientIp']
				]
			);

			return true;
		}

		return false;
	}

	public function removeRecoveryCode( OATHUser $user, string $codeToRemove ) {
		$codeToRemove = $this->normaliseRecoveryCode( $codeToRemove );
		$key = array_search( $codeToRemove, $this->recoveryCodeKeys );
		if ( $key === false ) {
			return;
		}

		unset( $this->recoveryCodeKeys[$key] );
		// T408297 - Unset the key for the same encrypted token.
		unset( $this->recoveryCodeKeysEncrypted[$key] );

		if ( $this->recoveryCodeKeys === [] ) {
			// If we just deleted the last recovery code, generate new ones
			$this->regenerateRecoveryCodeKeys();

			$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
			$this->getLogger()->info(
				'OATHAuth {user} had their recovery codes automatically regenerated.', [
					'user' => $user->getUser()->getName(),
					'clientip' => $clientData['clientIp']
				]
			);
		}
	}

	public function regenerateRecoveryCodeKeys(): void {
		$recoveryCodesCount = OATHAuthServices::getInstance()->getConfig()->get( 'OATHRecoveryCodesCount' );
		$this->recoveryCodeKeys = [];
		for ( $i = 0; $i < $recoveryCodesCount; $i++ ) {
			$this->recoveryCodeKeys[] = Base32::encode( random_bytes( self::RECOVERY_CODE_LENGTH ) );
		}
		// reset this when we regenerate codes
		$this->setRecoveryCodeKeysEncryptedAndNonce( [], '' );
	}

	/** @inheritDoc */
	public function getModule(): string {
		return RecoveryCodes::MODULE_NAME;
	}

	/** @inheritDoc */
	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
		if ( !$encryptionHelper->isEnabled() ) {
			// fallback to unencrypted recovery codes
			return [
				// T408299 - array_values() to renumber array keys
				'recoverycodekeys' => array_values( $this->getRecoveryCodeKeys() )
			];
		}

		[ $keys, $nonce ] = $this->getRecoveryCodeKeysEncryptedAndNonce();
		if ( $keys !== [] ) {
			// do not re - encrypt existing recovery codes
			return [
				// T408299 - array_values() to renumber array keys
				'recoverycodekeys' => array_values( $keys ),
				'nonce' => $nonce,
			];
		}

		// brand new set of recovery codes
		$nonce ??= '';
		$encData = $encryptionHelper->encryptStringArrayValues(
			// T408299 - array_values() to renumber array keys
			array_values( $this->getRecoveryCodeKeys() ),
			$nonce
		);
		$this->setRecoveryCodeKeysEncryptedAndNonce( $encData['encrypted_array'], $encData['nonce'] );
		return [
			'recoverycodekeys' => $encData['encrypted_array'],
			'nonce' => $encData['nonce']
		];
	}

	private function normaliseRecoveryCode( string $token ): string {
		return (string)preg_replace( '/\s+/', '', $token );
	}

	/**
	 * Check if a token is valid for this key.
	 *
	 * @param string $token Token to verify
	 *
	 * @return bool true if this is a recovery code for this key.
	 */
	public function isValidRecoveryCode( string $token ): bool {
		$token = $this->normaliseRecoveryCode( $token );
		foreach ( $this->recoveryCodeKeys as $key ) {
			if ( hash_equals( $key, $token ) ) {
				return true;
			}
		}
		return false;
	}
}
