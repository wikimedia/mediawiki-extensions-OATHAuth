<?php

namespace MediaWiki\Extension\WebAuthn\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\Extension\WebAuthn\Auth\WebAuthnSecondaryAuthenticationProvider;
use MediaWiki\Extension\WebAuthn\Config\WebAuthnConfig;
use MediaWiki\Extension\WebAuthn\HTMLForm\WebAuthnAddKeyForm;
use MediaWiki\Extension\WebAuthn\HTMLForm\WebAuthnManageForm;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use MediaWiki\Message\Message;

class WebAuthn implements IModule {
	/**
	 * Custom action for the manage form
	 */
	public const ACTION_ADD_KEY = 'addkey';

	public const MODULE_ID = "webauthn";

	public static function factory(): IModule {
		return new static();
	}

	/**
	 * @return WebAuthnKey[]
	 */
	public static function getWebAuthnKeys( OATHUser $user ): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $user->getKeysForModule( self::MODULE_ID );
	}

	/** @inheritDoc */
	public function getName() {
		return self::MODULE_ID;
	}

	/** @inheritDoc */
	public function getDisplayName() {
		return wfMessage( 'webauthn-module-label' );
	}

	/**
	 * @param array $data
	 * @return WebAuthnKey
	 */
	public function newKey( array $data = [] ) {
		if ( !$data ) {
			return WebAuthnKey::newKey();
		}
		return WebAuthnKey::newFromData( $data );
	}

	/**
	 * @return WebAuthnSecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider() {
		return new WebAuthnSecondaryAuthenticationProvider();
	}

	/** @inheritDoc */
	public function isEnabled( OATHUser $user ) {
		return (bool)self::getWebAuthnKeys( $user );
	}

	/**
	 * Run the validation for each of the registered keys
	 *
	 * @param OATHUser $user
	 * @param array $data
	 * @return bool
	 */
	public function verify( OATHUser $user, array $data ) {
		$keys = self::getWebAuthnKeys( $user );
		foreach ( $keys as $key ) {
			// Pass if any of the keys matches
			if ( $key->verify( $data, $user ) === true ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the appropriate form for the given action.
	 * If the ability to add nenw credentials is disabled by configuration,
	 * the empty string will be returned for any action other than ACTION_DISABLE.
	 * The value null will be returned If no suitable form is found otherwise.
	 *
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @param IContextSource|null $context optional for backwards compatibility
	 * @return IManageForm|string|null
	 */
	public function getManageForm(
		$action,
		OATHUser $user,
		OATHUserRepository $repo,
		?IContextSource $context = null
	) {
		$module = $this;
		$context = $context ?: RequestContext::getMain();
		$enabledForUser = $this->isEnabled( $user );

		if ( $context->getConfig()->get( 'WebAuthnNewCredsDisabled' ) === false ) {
			if ( $action === OATHManage::ACTION_ENABLE || $action === static::ACTION_ADD_KEY ) {
				return new WebAuthnAddKeyForm( $user, $repo, $module, $context );
			}
			if ( $enabledForUser ) {
				return new WebAuthnManageForm( $user, $repo, $module, $context );
			}
			return null;
		} else {
			return '';
		}
	}

	/**
	 * @param string $id
	 * @param OATHUser $user
	 * @return IAuthKey|null
	 */
	public function findKeyByCredentialId( $id, $user ) {
		foreach ( self::getWebAuthnKeys( $user ) as $key ) {
			if ( $key->getAttestedCredentialData()->getCredentialId() === $id ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Get a single key by its name.
	 *
	 * @param string $name
	 * @param OATHUser $user
	 *
	 * @return WebAuthnKey|null
	 */
	public function getKeyByFriendlyName( string $name, OATHUser $user ): ?WebAuthnKey {
		foreach ( self::getWebAuthnKeys( $user ) as $key ) {
			if ( $key->getFriendlyName() === $name ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * @return WebAuthnConfig
	 */
	public function getConfig() {
		return new WebAuthnConfig();
	}

	/** @inheritDoc */
	public function getDescriptionMessage() {
		return wfMessage( 'webauthn-module-description' );
	}

	/**
	 * Message that will be shown when user is disabling the module,
	 * to warn the user of token/data loss
	 *
	 * @return Message|null
	 */
	public function getDisableWarningMessage() {
		return null;
	}

	/** @inheritDoc */
	public function getAddKeyMessage(): Message {
		return wfMessage( 'webauthn-add-security-key' );
	}

	/** @inheritDoc */
	public function getDeleteSuccessMessage( IAuthKey $key ): Message {
		'@phan-var WebAuthnKey $key';
		return wfMessage( 'webauthn-security-key-delete-success', $key->getFriendlyName() );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return false;
	}
}
