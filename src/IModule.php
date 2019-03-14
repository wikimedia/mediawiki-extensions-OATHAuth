<?php

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Auth\AuthenticationProvider;

interface IModule {
	/**
	 * Name of the module
	 * @return string
	 */
	public function getName();

	/**
	 * @return \Message
	 */
	public function getDisplayName();

	/**
	 *
	 * @param array $data
	 * @return IAuthKey
	 */
	public function newKey( array $data );

	/**
	 * Get special page for managing the module
	 *
	 * @param OATHUserRepository $userRepo
	 * @param OATHUser $user
	 * @return \SpecialPage
	 */
	public function getTargetPage( OATHUserRepository $userRepo, OATHUser $user );

	/**
	 * @param OATHUser $user
	 * @return array
	 */
	public function getDataFromUser( OATHUser $user );

	/**
	 * @return AuthenticationProvider
	 */
	public function getSecondaryAuthProvider();

	/**
	 * Is this module currently enabled for the given user
	 * Arguably, module is enabled just by the fact its set on user
	 * but it might not be true for all future modules
	 *
	 * @param OATHUser $user
	 * @return boolean
	 */
	public function isEnabled( OATHUser $user );

	/**
	 * Run the validation
	 *
	 * @param OATHUser $user
	 * @param array $data
	 * @return boolean
	 */
	public function verify( OATHUser $user, array $data );

}
