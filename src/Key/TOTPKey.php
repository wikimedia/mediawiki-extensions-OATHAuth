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
use DomainException;
use Exception;
use jakobo\HOTP\HOTP;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Wikimedia\ObjectCache\EmptyBagOStuff;

/**
 * Class representing a two-factor key
 *
 * Keys can be tied to OATHUsers
 *
 * @ingroup Extensions
 */
class TOTPKey implements IAuthKey {
	/** @var int|null */
	private ?int $id;

	/** @var array Two factor binary secret */
	private $secret;

	/** @var string[] List of recovery codes */
	public $recoveryCodes = [];

	/** @var string|null timestamp created for TOTP key */
	private ?string $createdTimestamp;

	/**
	 * The upper threshold number of recovery codes that if a user has less than, we'll try and notify them...
	 */
	private const RECOVERY_CODES_NOTIFICATION_NUMBER = 2;

	/**
	 * Number of recovery codes to be generated
	 */
	public const RECOVERY_CODES_COUNT = 10;

	/**
	 * Length (in bytes) that recovery codes should be
	 */
	private const RECOVERY_CODE_LENGTH = 10;

	/**
	 * @return TOTPKey
	 * @throws Exception
	 */
	public static function newFromRandom() {
		$object = new self(
			null,
			null,
			// 26 digits to give 128 bits - https://phabricator.wikimedia.org/T396951
			self::removeBase32Padding( Base32::encode( random_bytes( 26 ) ) ),
			[]
		);

		if ( !OATHAuthServices::getInstance()->getConfig()->get( 'OATHAllowMultipleModules' ) ) {
			$object->regenerateScratchTokens();
		}

		return $object;
	}

	/**
	 * @param string $paddedBase32String
	 * @return string
	 * @see T408225, T401393
	 */
	public static function removeBase32Padding( string $paddedBase32String ) {
		return rtrim( $paddedBase32String, '=' );
	}

	/**
	 * @param array $data
	 * @return TOTPKey|null on invalid data
	 * @throws UnexpectedValueException When encryption is not configured but db is encrypted
	 */
	public static function newFromArray( array $data ) {
		if ( !isset( $data['secret'] ) ) {
			return null;
		}

		$config = OATHAuthServices::getInstance()->getConfig();
		if ( !$config->get( 'OATHAllowMultipleModules' )
			&& !isset( $data['scratch_tokens'] ) ) {
			return null;
		}

		if ( isset( $data['nonce'] ) ) {
			$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
			if ( !$encryptionHelper->isEnabled() ) {
				throw new UnexpectedValueException(
					'Encryption is not configured but OATHAuth is attempting to use encryption'
				);
			}
			$data['encrypted_secret'] = $data['secret'];
			$data['secret'] = $encryptionHelper->decrypt( $data['secret'], $data['nonce'] );
		} else {
			$data['encrypted_secret'] = '';
			$data['nonce'] = '';
		}

		return new static(
			$data['id'] ?? null,
			$data['created_timestamp'] ?? null,
			$data['secret'] ?? '',
			$data['scratch_tokens'] ?? [],
			$data['encrypted_secret'],
			$data['nonce']
		);
	}

	public function __construct(
		?int $id,
		?string $createdTimestamp,
		string $secret,
		array $recoveryCodes,
		string $encryptedSecret = '',
		string $nonce = ''
	) {
		$this->id = $id;
		$this->createdTimestamp = $createdTimestamp;
		// Currently hardcoded values; might be used in the future
		$this->secret = [
			'mode' => 'hotp',
			'secret' => $secret,
			'period' => 30,
			'algorithm' => 'SHA1',
			'encrypted_secret' => $encryptedSecret,
			'nonce' => $nonce
		];
		$this->recoveryCodes = array_values( $recoveryCodes );
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getSecret(): string {
		return $this->secret['secret'];
	}

	public function getCreatedTimestamp(): ?string {
		return $this->createdTimestamp;
	}

	public function setEncryptedSecretAndNonce( string $encryptedSecret, string $nonce ) {
		$this->secret['encrypted_secret'] = $encryptedSecret;
		$this->secret['nonce'] = $nonce;
	}

	public function getEncryptedSecretAndNonce(): array {
		return [
			$this->secret['encrypted_secret'],
			$this->secret['nonce'],
		];
	}

	/**
	 * @return string[]
	 */
	public function getScratchTokens() {
		return $this->recoveryCodes;
	}

	/**
	 * @param array|null $data
	 */
	public function setScratchTokens( $data ): void {
		$this->recoveryCodes = $data;
	}

	/**
	 * @param array $data
	 * @param OATHUser $user
	 * @return bool
	 * @throws DomainException
	 */
	public function verify( $data, OATHUser $user ) {
		global $wgOATHAuthWindowRadius;

		$token = $data['token'] ?? '';

		if ( $this->secret['mode'] !== 'hotp' ) {
			throw new DomainException( 'OATHAuth extension does not support non-HOTP tokens' );
		}

		// Prevent replay attacks
		$services = MediaWikiServices::getInstance();
		$store = $services->getMainObjectStash();

		if ( $store instanceof EmptyBagOStuff ) {
			// Try and find some usable cache if the MainObjectStash isn't useful
			$store = $services->getObjectCacheFactory()->getLocalServerInstance( CACHE_ANYTHING );
		}

		$key = $store->makeKey( 'oathauth-totp', 'usedtokens', $user->getCentralId() );
		$lastWindow = (int)$store->get( $key );

		$results = HOTP::generateByTimeWindow(
			Base32::decode( $this->secret['secret'] ),
			$this->secret['period'],
			-$wgOATHAuthWindowRadius,
			$wgOATHAuthWindowRadius
		);

		// Remove any whitespace from the received token, which can be an intended group separator
		$token = preg_replace( '/\s+/', '', $token );

		$clientIP = RequestContext::getMain()->getRequest()->getIP();

		$logger = $this->getLogger();

		// Check to see if the user's given token is in the list of tokens generated
		// for the time window.
		foreach ( $results as $window => $result ) {
			if ( $window <= $lastWindow || !hash_equals( $result->toHOTP( 6 ), $token ) ) {
				continue;
			}

			$lastWindow = $window;

			$logger->info( 'OATHAuth user {user} entered a valid OTP from {clientip}', [
				'user' => $user->getAccount(),
				'clientip' => $clientIP,
			] );

			$store->set(
				$key,
				$lastWindow,
				$this->secret['period'] * ( 1 + 2 * $wgOATHAuthWindowRadius )
			);

			return true;
		}

		// services used below for scratch tokens and recovery codes
		$config = OATHAuthServices::getInstance()->getConfig();
		$oathRepo = OATHAuthServices::getInstance()->getUserRepository();

		// See if the user is using a legacy TOTP scratch token (aka recovery code)
		foreach ( $this->recoveryCodes as $i => $recoveryCode ) {
			if ( !hash_equals( $token, $recoveryCode ) ) {
				continue;
			}

			// Remove used TOTP-attached recovery code
			array_splice( $this->recoveryCodes, $i, 1 );

			// UPDATE: With T232336 soon to be completed, nearly all of the TOTP scratch
			// token related code will be able to be removed.  The current plan is to support
			// older scratch tokens and let them simply run out, in which case a user's
			// older TOTP factor will be migrated automatically or via a maintenance script
			// to the separate Recovery Code module
			if ( count( $this->recoveryCodes ) <= self::RECOVERY_CODES_NOTIFICATION_NUMBER ) {
				Manager::notifyRecoveryTokensRemaining(
					$user,
					self::RECOVERY_CODES_NOTIFICATION_NUMBER,
					self::RECOVERY_CODES_COUNT
				);
			}

			if ( $config->get( 'OATHAllowMultipleModules' ) && count( $this->recoveryCodes ) === 0 ) {
				// if the user has no more TOTP-attached scratch tokens,
				// let's try to create recovery codes for them, if
				// multiple module support is enabled.  For now, there is
				// no convenient way to alert the user of the new code, but they will
				// see a usable Recovery Code module under Special:OATHManage
				RecoveryCodeKeys::maybeCreateOrUpdateRecoveryCodeKeys( $user );

				$logger->info(
					// phpcs:ignore
					"OATHAuth {user} from {clientip} had recovery codes created for them after using their final TOTP scratch token.", [
						'user' => $user->getUser()->getName(),
						'clientip' => $clientIP
					]
				);
			}

			$logger->info( 'OATHAuth user {user} used a recovery token from {clientip}', [
				'user' => $user->getAccount(),
				'clientip' => $clientIP,
			] );

			OATHAuthServices::getInstance()
				->getUserRepository()
				->updateKey( $user, $this );
			return true;
		}

		// See if the user is using a newer recovery code
		// Both TOTP-attached scratch tokens and recovery codes will be accepted
		if ( $config->get( 'OATHAllowMultipleModules' ) ) {
			$moduleDbKeysRecCodes = $user->getKeysForModule( RecoveryCodes::MODULE_NAME );

			if ( array_key_exists( 0, $moduleDbKeysRecCodes ) ) {
				$objRecoveryCodeKeys = array_shift( $moduleDbKeysRecCodes );
				// @phan-suppress-next-line PhanUndeclaredMethod
				foreach ( $objRecoveryCodeKeys->getRecoveryCodeKeys() as $userRecoveryCode ) {
					if ( !hash_equals(
						$token,
						$userRecoveryCode
					) ) {
						continue;
					}

					RecoveryCodeKeys::maybeCreateOrUpdateRecoveryCodeKeys( $user, $userRecoveryCode );

					$logger->info(
						// phpcs:ignore
						"OATHAuth {user} used a recovery code from {clientip} and had their existing recovery codes regenerated automatically.", [
							'user' => $user->getUser()->getName(),
							'clientip' => $clientIP
						]
					);

					return true;
				}
			}
		}

		return false;
	}

	public function regenerateScratchTokens() {
		$codes = [];
		for ( $i = 0; $i < self::RECOVERY_CODES_COUNT; $i++ ) {
			$codes[] = Base32::encode( random_bytes( self::RECOVERY_CODE_LENGTH ) );
		}
		$this->recoveryCodes = $codes;
	}

	/**
	 * Check if a token is one of the recovery codes for this two-factor key.
	 *
	 * @param string $token Token to verify
	 *
	 * @return bool true if this is a recovery code.
	 */
	public function isScratchToken( $token ) {
		$token = preg_replace( '/\s+/', '', $token );
		return in_array( $token, $this->recoveryCodes, true );
	}

	/** @inheritDoc */
	public function getModule(): string {
		return TOTP::MODULE_NAME;
	}

	/**
	 * @return LoggerInterface
	 */
	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	public function jsonSerialize(): array {
		$encryptedData = $this->getEncryptedSecretAndNonce();
		$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
		if ( $encryptionHelper->isEnabled() && in_array( '', $encryptedData ) ) {
			$data = $encryptionHelper->encrypt( $this->getSecret() );
			$this->setEncryptedSecretAndNonce( $data['secret'], $data['nonce'] );
		} elseif ( $encryptionHelper->isEnabled() ) {
			$data = [
				'secret' => $encryptedData[0],
				'nonce' => $encryptedData[1]
			];
		} else {
			$data = [ 'secret' => $this->getSecret() ];
		}

		$tokens = $this->getScratchTokens();
		if ( count( $tokens ) ) {
			$data['scratch_tokens'] = $tokens;
		}

		return $data;
	}
}
