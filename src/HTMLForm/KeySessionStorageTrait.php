<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;

/**
 * Helper class to display and manage recovery codes within various contexts
 */
trait KeySessionStorageTrait {
	/**
	 * Helper function to generically track IAuthKeys in the user session
	 *
	 * @param string $keyType accepts current key names (TOTPKey, RecoveryCodeKeys)
	 *                        which may relate to any type of scratch token or recovery code
	 * @return IAuthKey|null
	 */
	public function setKeyDataInSession( string $keyType, array $keyData = [] ) {
		// RecoveryCodeKeys or TOTPKey
		$key = null;
		$sessionKey = $this->getSessionKeyName( $keyType );

		if ( count( $keyData ) === 0 ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$keyData = $this->getRequest()->getSession()->getSecret( $sessionKey, [] );
		}

		// TODO: Ideally determine key type via instanceof or ::class instead of strings
		if ( $keyType === 'TOTPKey' ) {
			$key = TOTPKey::newFromArray( $keyData );
			if ( !$key instanceof TOTPKey ) {
				$key = TOTPKey::newFromRandom();
			}
			if ( array_key_exists( 'scratch_tokens', $keyData )
				&& count( $keyData['scratch_tokens'] ) === 0
				&& count( $key->getScratchTokens() ) === 0
			) {
				$key->regenerateScratchTokens();
			}
		} elseif ( $keyType === 'RecoveryCodeKeys' ) {
			$key = RecoveryCodeKeys::newFromArray( $keyData );
			if ( array_key_exists( 'recoverycodekeys', $keyData ) && count( $keyData['recoverycodekeys'] ) === 0 ) {
				$key->regenerateRecoveryCodeKeys();
			}
		}

		if ( $key instanceof IAuthKey ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->getRequest()->getSession()->setSecret(
				$sessionKey,
				$key->jsonSerialize()
			);
		} else {
			// set session key to empty
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->getRequest()->getSession()->setSecret(
				$sessionKey,
				[]
			);
		}

		return $key;
	}

	/**
	 * @return array|null
	 */
	public function getKeyDataInSession( string $keyType ) {
		// @phan-suppress-next-line PhanUndeclaredMethod
		return $this->getRequest()->getSession()->getSecret( $this->getSessionKeyName( $keyType ) );
	}

	public function setKeyDataInSessionToNull( string $keyType ): void {
		// @phan-suppress-next-line PhanUndeclaredMethod
		$this->getRequest()->getSession()->setSecret( $this->getSessionKeyName( $keyType ), null );
	}

	private function getSessionKeyName( string $keyType ): string {
		return $keyType . '_oathauth_key';
	}
}
