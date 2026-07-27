<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

/**
 * AuthManager value object for the Recovery Codes second factor of authentication:
 * a pre-generated recovery code (aka scratch token) that is created whenever an OATH
 * user enables at least one form of 2FA (TOTP, WebAuthn, etc.) and is regenerated upon
 * each successful usage of a recovery code.
 */
class RecoveryCodesAuthenticationRequest extends AuthenticationRequest {
	public string $RecoveryCode;

	public function __construct(
		public bool $initialCodesOnly = false
	) {
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
		$fields = [];
		if ( $this->initialCodesOnly ) {
			$fields['info-temporary-recovery-code'] = [
				'type' => 'null',
				'value' => wfMessage( 'oathauth-auth-initial-recovery-code-info' )
			];
		}

		return $fields + [
			'RecoveryCode' => [
				'type' => 'string',
				'label' => $this->initialCodesOnly ?
					wfMessage( 'oathauth-auth-initial-recovery-code-label' ) :
					wfMessage( 'oathauth-auth-recovery-code-label' ),
				'help' => $this->initialCodesOnly ?
					wfMessage( 'oathauth-auth-initial-recovery-code-help' ) :
					wfMessage( 'oathauth-auth-recovery-code-help' ),
			]
		];
	}
}
