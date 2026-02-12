<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;

class WebAuthnSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/** @inheritDoc */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$authenticator = WebAuthnAuthenticator::factory( $user );
		if ( !$authenticator->isEnabled() ) {
			return AuthenticationResponse::newAbstain();
		}
		$canAuthenticate = $authenticator->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			return AuthenticationResponse::newFail( $canAuthenticate->getMessage() );
		}
		$startAuthResult = $authenticator->startAuthentication();
		if ( $startAuthResult->isGood() ) {
			$request = new WebAuthnAuthenticationRequest( $startAuthResult->getValue()['json'] );
			$this->addModules();
			return AuthenticationResponse::newUI( [ $request ],
				wfMessage( 'oathauth-webauthn-ui-login-prompt' ) );
		}
		return AuthenticationResponse::newFail( $startAuthResult->getMessage() );
	}

	/** @inheritDoc */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		$authenticator = WebAuthnAuthenticator::factory( $user );
		$canAuthenticate = $authenticator->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			return AuthenticationResponse::newFail( $canAuthenticate->getMessage() );
		}

		/** @var WebAuthnAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass(
			$reqs,
			WebAuthnAuthenticationRequest::class
		);
		if ( !$request ) {
			// Re-ask user for credentials
			$request = new WebAuthnAuthenticationRequest( $authenticator->startAuthentication()->getValue()['json'] );
			$this->addModules();
			return AuthenticationResponse::newUI( [ $request ],
				wfMessage( 'oathauth-webauthn-error-credentials-missing' ), 'error' );
		}

		// Get credential retrieved from the client
		$verificationData = $request->getSubmittedData();
		if ( $verificationData['credential'] === '' ) {
			return AuthenticationResponse::newUI( [ $request ],
				wfMessage( 'oathauth-webauthn-error-credentials-missing' ), 'error' );
		}

		$authResult = $authenticator->continueAuthentication( $verificationData );
		if ( $authResult->isGood() ) {
			return AuthenticationResponse::newPass( $authResult->getValue()->getUser()->getName() );
		}

		$messages = $authResult->getMessages();

		if ( $messages === [] ) {
			return AuthenticationResponse::newFail( wfMessage( 'oathauth-webauthn-error-verification-failed' ) );
		}

		// Return the first error from the authenticator, if there is any
		return AuthenticationResponse::newFail( wfMessage( $messages[0] ) );
	}

	protected function addModules() {
		// It would be better to add modules in HTMLFormField class,
		// but that does not seem to work for the login form
		$out = RequestContext::getMain()->getOutput();
		$out->addModules( "ext.webauthn.login" );
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
