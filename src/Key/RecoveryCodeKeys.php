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
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
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
class RecoveryCodeKeys implements IAuthKey {
	/** @var int|null */
	private ?int $id;

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
	 * Amount of recovery code module instances allowed per user in oathauth_devices
	 */
	public const RECOVERY_CODE_MODULE_COUNT = 1;

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
			$data['recoverycodekeys'],
			$data['recoverycodekeysencrypted'],
			$data['nonce']
		);
	}

	/**
	 * @param int|null $id the database id of this key
	 * @param array $recoveryCodeKeys
	 * @param array $recoveryCodeKeysEncrypted
	 * @param string $nonce
	 */
	public function __construct(
		?int $id,
		array $recoveryCodeKeys,
		array $recoveryCodeKeysEncrypted,
		string $nonce = ''
	) {
		$this->id = $id;
		$this->recoveryCodeKeys = array_values( $recoveryCodeKeys );
		$this->recoveryCodeKeysEncrypted = array_values( $recoveryCodeKeysEncrypted );
		$this->nonce = $nonce;
	}

	/** @inheritDoc */
	public function getId(): ?int {
		return $this->id;
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

		// See if the user is using a recovery code
		$moduleDbKeysRecCodes = $user->getKeysForModule( RecoveryCodes::MODULE_NAME );
		if ( count( $moduleDbKeysRecCodes ) > self::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( wfMessage( 'oathauth-recoverycodes-too-many-instances' )->text() );
		}

		$config = OATHAuthServices::getInstance()->getConfig();
		$oathRepo = OATHAuthServices::getInstance()->getUserRepository();
		$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
		$logger = $this->getLogger();

		// lets see if they still have TOTP-attached scratch tokens
		// they are trying to use
		$moduleDbKeysTOTP = $user->getKeysForModule( TOTP::MODULE_NAME );

		// should be safe to assume only one TOTP with attached
		// scratch tokens prior to multiple module migration
		if ( array_key_exists( 0, $moduleDbKeysTOTP ) ) {
			$objTOTPKey = array_shift( $moduleDbKeysTOTP );
			// @phan-suppress-next-line PhanUndeclaredProperty
			foreach ( $objTOTPKey->recoveryCodes as $i => $userScratchToken ) {
				if ( hash_equals( preg_replace( '/\s+/', '', $data['recoverycode'] ), $userScratchToken ) ) {
					// @phan-suppress-next-line PhanUndeclaredProperty
					array_splice( $objTOTPKey->recoveryCodes, $i, 1 );

					$logger->info( 'OATHAuth user {user} used a TOTP-attached scratch token from {clientip}', [
						'user' => $user->getAccount(),
						'clientip' => $clientData['clientIp'],
					] );

					$oathRepo->updateKey( $user, $objTOTPKey );
					return true;
				}
			}
		}

		if ( array_key_exists( 0, $moduleDbKeysRecCodes ) ) {
			$objRecoveryCodeKeys = array_shift( $moduleDbKeysRecCodes );
			// @phan-suppress-next-line PhanUndeclaredProperty
			foreach ( $objRecoveryCodeKeys->recoveryCodeKeys as $userRecoveryCode ) {
				if ( hash_equals(
						preg_replace( '/\s+/', '', $data['recoverycode'] ),
						$userRecoveryCode
					) ) {

					self::maybeCreateOrUpdateRecoveryCodeKeys( $user, $userRecoveryCode );

					$logger->info(
						// phpcs:ignore
						"OATHAuth {user} used a recovery code from {clientip} and had their existing recovery codes regenerated automatically.", [
							'user' => $user->getUser()->getName(),
							'clientip' => $clientData['clientIp']
						]
					);

					return true;
				}
			}
		}

		return false;
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
			return [ 'recoverycodekeys' => $this->getRecoveryCodeKeys() ];
		}

		$encryptedData = $this->getRecoveryCodeKeysEncryptedAndNonce();
		if ( $encryptedData[0] === [] ) {
			// brand new set of recovery codes or reduced set + same nonce
			$nonce = $encryptedData[1] ?? '';
			$encData = $encryptionHelper->encryptStringArrayValues( $this->getRecoveryCodeKeys(), $nonce );
			$this->setRecoveryCodeKeysEncryptedAndNonce( $encData['encrypted_array'], $encData['nonce'] );
			return [
				'recoverycodekeys' => $encData['encrypted_array'],
				'nonce' => $encData['nonce']
			];
		}

		// do not reencrypt existing, unchanged recovery codes
		return [
			'recoverycodekeys' => $encryptedData[0],
			'nonce' => $encryptedData[1]
		];
	}

	/**
	 * @throws UnexpectedValueException
	 */
	public static function maybeCreateOrUpdateRecoveryCodeKeys( OATHUser $user, string $usedRecoveryCode = '' ): bool {
		$uid = $user->getCentralId();
		if ( !$uid ) {
			throw new UnexpectedValueException( wfMessage( 'oathauth-invalidrequest' )->escaped() );
		}

		// see if recovery codes module exists for user
		$moduleDbKeys = $user->getKeysForModule( RecoveryCodes::MODULE_NAME );

		if ( count( $moduleDbKeys ) > self::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( wfMessage( 'oathauth-recoverycodes-too-many-instances' ) );
		}

		if ( array_key_exists( 0, $moduleDbKeys )
			&& $moduleDbKeys[0] instanceof self ) {
			$objRecCodeKeys = $moduleDbKeys[0];
		} else {
			$objRecCodeKeys = self::newFromArray( [ 'recoverycodekeys' => [] ] );
		}

		// remove used recovery code
		if ( $usedRecoveryCode ) {
			$key = array_search( $usedRecoveryCode, $objRecCodeKeys->recoveryCodeKeys );
			if ( is_int( $key ) && $key <= count( $objRecCodeKeys->recoveryCodeKeys ) ) {
				unset( $objRecCodeKeys->recoveryCodeKeys[$key] );
			}
		}

		// only regenerate if there are no tokens left or these are brand new recovery codes
		if ( count( $objRecCodeKeys->recoveryCodeKeys ) === 0 ) {
			$objRecCodeKeys->regenerateRecoveryCodeKeys();
		}

		$recoveryCodeKeys = $objRecCodeKeys->getRecoveryCodeKeys();
		if ( count( $recoveryCodeKeys ) > 0 && !in_array( '', $recoveryCodeKeys ) ) {
			$oathRepo = OATHAuthServices::getInstance()->getUserRepository();
			$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
			if ( $moduleRegistry->getModuleByKey( $objRecCodeKeys->getModule() )->isEnabled( $user ) ) {
				$oathRepo->updateKey(
					$user,
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$objRecCodeKeys
				);
			} else {
				$oathRepo->createKey(
					$user,
					$moduleRegistry->getModuleByKey( $objRecCodeKeys->getModule() ),
					$objRecCodeKeys->jsonSerialize(),
					RequestContext::getMain()->getRequest()->getIP()
				);
			}
		}

		return true;
	}
}
