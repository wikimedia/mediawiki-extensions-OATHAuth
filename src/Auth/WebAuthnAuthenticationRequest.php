<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\WebAuthn\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

class WebAuthnAuthenticationRequest extends AuthenticationRequest {
	protected string $authInfo;

	protected string $credential;

	/** @inheritDoc */
	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'oathauth-describe-provider' ),
			'account' => new RawMessage( '$1', [ $this->username ] ),
		] + parent::describeCredentials();
	}

	/**
	 * Set the authentication data to be passed
	 * to the client for credential retrieval
	 */
	public function setAuthInfo( string $info ) {
		$this->authInfo = $info;
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		return [
			'auth_info' => [
				'type' => 'hidden',
				'value' => $this->authInfo,
				'label' => wfMessage( 'webauthn-authentication-info-label' ),
				'help' => wfMessage( 'webauthn-authentication-info-help' ),
			],
			'credential' => [
				'type' => 'hidden',
				'value' => '',
				'label' => wfMessage( 'webauthn-credential-label' ),
				'help' => wfMessage( 'webauthn-credential-help' ),
			]
		];
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		if ( !isset( $data['credential'] ) ) {
			return false;
		}
		$this->credential = $data['credential'];

		return true;
	}

	/** @inheritDoc */
	public function getSubmittedData() {
		return [
			'credential' => $this->credential
		];
	}
}
