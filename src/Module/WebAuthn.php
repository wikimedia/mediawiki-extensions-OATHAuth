<?php

namespace MediaWiki\Extension\WebAuthn\Module;

use IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\Extension\WebAuthn\Auth\WebAuthnSecondaryAuthenticationProvider;
use MediaWiki\Extension\WebAuthn\Config\WebAuthnConfig;
use MediaWiki\Extension\WebAuthn\HTMLForm\WebAuthnAddKeyForm;
use MediaWiki\Extension\WebAuthn\HTMLForm\WebAuthnDisableForm;
use MediaWiki\Extension\WebAuthn\HTMLForm\WebAuthnManageForm;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use Message;
use RequestContext;

class WebAuthn implements IModule {
	/**
	 * Custom action for the manage form
	 */
	public const ACTION_ADD_KEY = 'addkey';

	public static function factory() {
		return new static();
	}

	/** @inheritDoc */
	public function getName() {
		return 'webauthn';
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
		if ( empty( $data ) ) {
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
		if ( $user->getModule() instanceof WebAuthn ) {
			$key = $user->getFirstKey();
			if ( $key !== null && $key instanceof WebAuthnKey ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run the validation for each of the registered keys
	 *
	 * @param OATHUser $user
	 * @param array $data
	 * @return bool
	 */
	public function verify( OATHUser $user, array $data ) {
		$keys = $user->getKeys();
		foreach ( $keys as $key ) {
			// Pass if any of the keys matches
			if ( $key->verify( $data, $user ) === true ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @param IContextSource|null $context optional for backwards compatibility
	 * @return IManageForm|null if no form is available for given action
	 */
	public function getManageForm(
		$action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context = null
	) {
		$module = $this;
		$context = $context ?: RequestContext::getMain();
		$enabledForUser = $user->getModule() instanceof self;
		if ( $action === OATHManage::ACTION_DISABLE && $enabledForUser ) {
			return new WebAuthnDisableForm( $user, $repo, $module, $context );
		}
		if ( $action === OATHManage::ACTION_ENABLE && !$enabledForUser ) {
			return new WebAuthnAddKeyForm( $user, $repo, $module, $context );
		}
		if ( $action === static::ACTION_ADD_KEY && $enabledForUser ) {
			return new WebAuthnAddKeyForm( $user, $repo, $module, $context );
		}

		if ( $enabledForUser ) {
			return new WebAuthnManageForm( $user, $repo, $module, $context );
		}

		return null;
	}

	/**
	 * @param string $id
	 * @param OATHUser $user
	 * @return IAuthKey|null
	 */
	public function findKeyByCredentialId( $id, $user ) {
		foreach ( $user->getKeys() as $key ) {
			if ( !( $key instanceof WebAuthnKey ) ) {
				continue;
			}
			if ( $key->getAttestedCredentialData()->getCredentialId() === $id ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Remove single key by its friendly name.
	 *
	 * This will just make changes in memory, not persist them!
	 *
	 * @param string $name
	 * @param OATHUser $user
	 *
	 * @return bool
	 */
	public function removeKeyByFriendlyName( $name, $user ) {
		$keys = $user->getKeys();
		$newKeys = array_filter( $keys, static function ( $key ) use ( $name ) {
			if ( !( $key instanceof WebAuthnKey ) ) {
				return false;
			}
			return $key->getFriendlyName() !== $name;
		} );

		$user->setKeys( $newKeys );
		return $newKeys !== $keys;
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
}
