<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MWException;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPEnableForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPDisableForm;
use MediaWiki\Extension\OATHAuth\Auth\TOTPSecondaryAuthenticationProvider;

class TOTP implements IModule {
	public static function factory() {
		return new static();
	}

	/**
	 * Name of the module
	 * @return string
	 */
	public function getName() {
		return "totp";
	}

	public function getDisplayName() {
		return wfMessage( 'oathauth-module-totp-label' );
	}

	/**
	 *
	 * @param array $data
	 * @return IAuthKey
	 * @throws MWException
	 */
	public function newKey( array $data ) {
		if ( !isset( $data['secret'] ) || !isset( $data['scratch_tokens'] ) ) {
			throw new MWException( 'oathauth-invalid-data-format' );
		}
		return new TOTPKey(
			$data['secret'],
			explode( ',', $data['scratch_tokens'] )
		);
	}

	/**
	 * @param OATHUser $user
	 * @return array
	 * @throws MWException
	 */
	public function getDataFromUser( OATHUser $user ) {
		$key = $user->getFirstKey();
		if ( !( $key instanceof TOTPKey ) ) {
			throw new MWException( 'oathauth-invalid-key-type' );
		}
		return [
			'keys' => [ [
				'secret' => $key->getSecret(),
				'scratch_tokens' => implode( ',', $key->getScratchTokens() ),
			] ]
		];
	}

	/**
	 * @return SecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider() {
		return new TOTPSecondaryAuthenticationProvider();
	}

	/**
	 * @param OATHUser $user
	 * @param array $data
	 * @return bool|int
	 * @throws MWException
	 */
	public function verify( OATHUser $user, array $data ) {
		if ( !isset( $data['token'] ) ) {
			return false;
		}
		$key = $user->getFirstKey();
		if ( !( $key instanceof TOTPKey ) ) {
			return false;
		}
		return $key->verify( $data, $user );
	}

	/**
	 * Is this module currently enabled for the given user
	 *
	 * @param OATHUser $user
	 * @return bool
	 */
	public function isEnabled( OATHUser $user ) {
		return $user->getFirstKey() instanceof TOTPKey;
	}

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @return IManageForm|null
	 */
	public function getManageForm( $action, OATHUser $user, OATHUserRepository $repo ) {
		switch ( $action ) {
			case OATHManage::ACTION_ENABLE:
				return new TOTPEnableForm( $user, $repo, $this );
			case OATHManage::ACTION_DISABLE:
				return new TOTPDisableForm( $user, $repo, $this );
			default:
				return null;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getConfig() {
		return null;
	}
}