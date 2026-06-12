/* global PublicKeyCredential:false */

mw.ext.webauthn.initPasswordlessLogin = function ( $root ) {
	$root = $root || $( document );
	// We don't initialize `form` here, because doing so immediately throws a user-visible error
	// if window.PublicKeyCredential doesn't exist (T427562). We can't construct separate
	// Authenticator objects in each code path, because they need to share state: when the user
	// clicks the "login with passkey" button, we need to abort the conditional authentication.
	let form = null;
	let auth = null;

	// Conditional UI for the username field
	if (
		$root.find( '.mw-userlogin-username' ).length &&
		window.PublicKeyCredential &&
		PublicKeyCredential.isConditionalMediationAvailable
	) {
		PublicKeyCredential.isConditionalMediationAvailable().then( ( isAvailable ) => {
			if ( !isAvailable ) {
				return;
			}

			form = form || new mw.ext.webauthn.LoginFormWidget();
			auth = auth || new mw.ext.webauthn.Authenticator( form.getAuthInfo() );

			auth.authenticate( true ).then(
				( credential ) => {
					form.submitWithCredential( credential );
				},
				( error ) => {
					// Don't display errors, these tend to be about failing to set up conditional
					// auth. Instead just silently don't display the conditional auth UI.
					mw.log( 'WebAuthn conditional authentication failed', error );
				}
			);
		} );
	}

	// "Log in with passkey" button
	$root.find( '#mw-input-passwordlessButton' ).on( 'click', ( e ) => {
		e.preventDefault();

		form = form || new mw.ext.webauthn.LoginFormWidget();
		auth = auth || new mw.ext.webauthn.Authenticator( form.getAuthInfo() );

		auth.authenticate().then(
			( credential ) => {
				form.submitWithCredential( credential );
			},
			( error ) => {
				form.dieWithError( error );
			}
		);
	} );
};

$( () => {
	// Bail when the login form isn't on the page (e.g. the QUnit test runner,
	// where this module may be pulled in as a test dependency). The widget
	// constructor below requires the form to exist.
	if ( !$( '#userloginForm' ).length ) {
		return;
	}
	mw.ext.webauthn.initPasswordlessLogin();
} );
