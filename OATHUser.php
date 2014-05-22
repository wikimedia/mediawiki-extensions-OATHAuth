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
	 * Constructor. Can't be called directly. Call one of the static NewFrom* methods
	 * @param User $user
	 * @param OATHAuthKey $key
	 */
	public function __construct( User $user, OATHAuthKey $key = null ) {
		$this->user = $user;
		$this->key = $key;
	}

	public function getUser() {
		return $this->user;
	}

	/**
	 * @return String
	 */
	public function getAccount() {
		global $wgSitename;

		return "$wgSitename:{$this->user->getName()}";
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
