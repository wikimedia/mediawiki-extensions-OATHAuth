<?php

namespace MediaWiki\Extension\OATHAuth;

use stdClass;

interface IAuthKey {

	/**
	 * @param array|stdClass $data
	 * @param OATHUser $user
	 * @return mixed
	 */
	public function verify( $data, OATHUser $user );
}
