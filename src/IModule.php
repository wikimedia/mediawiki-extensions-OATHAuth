<?php

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Auth\AuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;

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

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @return IManageForm|null if no form is available for given action
	 */
	public function getManageForm( $action, OATHUser $user, OATHUserRepository $repo );

}
