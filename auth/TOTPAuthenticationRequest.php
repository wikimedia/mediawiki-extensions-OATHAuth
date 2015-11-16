<?php

use MediaWiki\Auth\AuthenticationRequest;

/**
 * AuthManager value object for the TOTP second factor of an authentication: a pseudorandom token
 * that is generated from the current time indepdendently by the server and the client.
 */
class TOTPAuthenticationRequest extends AuthenticationRequest {
	public $OATHToken;

	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'oathauth-describe-provider' ),
			'account' => new \RawMessage( '$1', [ $this->username ] ),
		] + parent::describeCredentials();
	}

	public function getFieldInfo() {
		return array(
			'OATHToken' => array(
				'type' => 'string',
				'label' => wfMessage( 'oathauth-auth-token-label' ),
				'help' => wfMessage( 'oathauth-auth-token-help' ),
			),
		);
	}
}
