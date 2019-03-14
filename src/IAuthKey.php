<?php

namespace MediaWiki\Extension\OATHAuth;

interface IAuthKey {

	/**
	 * @param string $token
	 * @param OATHUser $user
	 * @return mixed
	 */
	public function verify( $token, OATHUser $user );
}
