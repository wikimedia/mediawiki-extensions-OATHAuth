<?php

/**
 * Class representing a user from OATH's perspective
 *
 * @ingroup Extensions
 */
class OATHUser {
	/** @var User */
	private $user;

	/** @var OATHAuthKey|null */
	private $key;

	/**
	 * Constructor. Can't be called directly. Use OATHUserRepository::findByUser instead.
	 * @param User $user
	 * @param OATHAuthKey $key
	 */
	public function __construct( User $user, OATHAuthKey $key = null ) {
		$this->user = $user;
		$this->key = $key;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return String
	 */
	public function getIssuer() {
		global $wgSitename, $wgOATHAuthAccountPrefix;
		if ( $wgOATHAuthAccountPrefix !== false ) {
			return $wgOATHAuthAccountPrefix;
		}
		return $wgSitename;
	}

	/**
	 * @return String
	 */
	public function getAccount() {
		return $this->user->getName();
	}

	/**
	 * Get the key associated with this user.
	 *
	 * @return null|OATHAuthKey
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Set the key associated with this user.
	 *
	 * @param OATHAuthKey|null $key
	 */
	public function setKey( OATHAuthKey $key = null ) {
		$this->key = $key;
	}
}
