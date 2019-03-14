<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Auth\AuthenticationProvider;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\TOTPDisable;
use MediaWiki\Extension\OATHAuth\Special\TOTPEnable;
use TOTPSecondaryAuthenticationProvider;
use MWException;

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
	 * @param OATHUserRepository $userRepo
	 * @param OATHUser $user
	 * @return TOTPDisable|TOTPEnable
	 */
	public function getTargetPage( OATHUserRepository $userRepo, OATHUser $user ) {
		if ( $user->getKey() === null ) {
			return new TOTPEnable( $userRepo, $user );
		}
		return new TOTPDisable( $userRepo, $user );
	}

	/**
	 * @param OATHUser $user
	 * @return array
	 * @throws MWException
	 */
	public function getDataFromUser( OATHUser $user ) {
		$key = $user->getKey();
		if ( !( $key instanceof TOTPKey ) ) {
			throw new MWException( 'oathauth-invalid-key-type' );
		}
		return [
			'secret' => $key->getSecret(),
			'scratch_tokens' => implode( ',', $key->getScratchTokens() ),
		];
	}

	/**
	 * @return AuthenticationProvider
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
		$token = $data['token'];
		$key = $user->getKey();
		if ( !( $key instanceof TOTPKey ) ) {
			return false;
		}
		return $key->verify( $token, $user );
	}

	/**
	 * Is this module currently enabled for the given user
	 *
	 * @param OATHUser $user
	 * @return bool
	 */
	public function isEnabled( OATHUser $user ) {
		return $user->getKey() !== false;
	}
}
