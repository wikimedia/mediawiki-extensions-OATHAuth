<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

class WebAuthnAuthenticationRequest extends AuthenticationRequest {

	public string $credential;

	/**
	 * @param string $authInfo Serialized JSON blob obtained from
	 *   WebAuthnAuthenticator::startAuthentication()
	 * @param array{isReauth?:bool, showPrompt?:bool, showButton?:'passwordless'|'interstitial'|false} $options
	 *   - isReauth: Whether this request is for a reauthentication (default: false)
	 *   - showPrompt: Whether to display the prompt telling the user to use their security key (default: true)
	 *   - showButton: Whether to show a button that activates the WebAuthn authentication.
	 *       - 'passwordless': Display a "Log in with passkey" or "Continue with passkey" button
	 *       - 'interstitial': Display a "Continue with security key" button
	 *       - false: Don't display a button, and activate the WebAuthn authentication immediately (default)
	 */
	public function __construct(
		public string $authInfo,
		public array $options = []
	) {
		$this->options += [
			'isReauth' => false,
			'showPrompt' => true,
			'showButton' => false
		];
	}

	/** @inheritDoc */
	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'oathauth-describe-provider' ),
			'account' => new RawMessage( '$1', [ $this->username ] ),
		] + parent::describeCredentials();
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		$fields = [
			// The hidden auth_info field only exists to send the authInfo JSON blob to the client.
			// It's not used for authentication and ignored when submitted back to us, we get the
			// authInfo blob from the session instead.
			'auth_info' => [
				'type' => 'hidden',
				'value' => $this->authInfo,
				'label' => wfMessage( 'oathauth-webauthn-authentication-info-label' ),
				'help' => wfMessage( 'oathauth-webauthn-authentication-info-help' ),
			],
			'credential' => [
				'type' => 'hidden',
				'value' => '',
				'label' => wfMessage( 'oathauth-webauthn-credential-label' ),
				'help' => wfMessage( 'oathauth-webauthn-credential-help' ),
			]
		];

		if ( $this->options['showPrompt'] ) {
			$fields['webauthnLabel'] = [
				'type' => 'null',
				'value' => wfMessage( 'oathauth-webauthn-ui-login-prompt' ),
				// TODO: Use a different message for help?
				'help' => wfMessage( 'oathauth-webauthn-ui-login-prompt' ),
			];
		}

		if ( $this->options['showButton'] === 'passwordless' ) {
			$fields['passwordlessButton'] = [
				'type' => 'button',
				'label' => $this->options['isReauth'] ?
					wfMessage( 'oathauth-webauthn-reauth-passkey-button' ) :
					wfMessage( 'oathauth-webauthn-login-passkey-button' ),
			];
		} elseif ( $this->options['showButton'] === 'interstitial' ) {
			$fields['webauthnButton'] = [
				'type' => 'button',
				'label' => wfMessage( 'oathauth-webauthn-reauth-security-key-button' ),
			];
		}

		return $fields;
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		if ( !isset( $data['credential'] ) ) {
			return false;
		}
		$this->credential = $data['credential'];

		return true;
	}

	public function getSubmittedData(): array {
		// Don't trust the submitted auth_info, otherwise the user could control which challenge
		// we're validating against and do a replay attack. Instead, we use the authInfo blob
		// in the session, which we stored there when we issued the challenge.
		return [
			'credential' => $this->credential
		];
	}
}
