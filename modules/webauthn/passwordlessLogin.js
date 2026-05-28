/* global PublicKeyCredential:false */

$( () => {
	// We don't initialize `form` here, because doing so immediately throws a user-visible error
	// if window.PublicKeyCredential doesn't exist (T427562)

	// Conditional UI for the username field
	if (
		$( '.mw-userlogin-username' ).length &&
		window.PublicKeyCredential &&
		PublicKeyCredential.isConditionalMediationAvailable
	) {
		PublicKeyCredential.isConditionalMediationAvailable().then( ( isAvailable ) => {
			if ( !isAvailable ) {
				return;
			}

			const form = new mw.ext.webauthn.LoginFormWidget();
			const authenticator = new mw.ext.webauthn.Authenticator( form.getAuthInfo() );

			authenticator.authenticate( true ).then(
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
	$( '#mw-input-passwordlessButton' ).on( 'click', ( e ) => {
		e.preventDefault();

		const form = new mw.ext.webauthn.LoginFormWidget();
		const authenticator = new mw.ext.webauthn.Authenticator( form.getAuthInfo() );

		authenticator.authenticate().then(
			( credential ) => {
				form.submitWithCredential( credential );
			},
			( error ) => {
				form.dieWithError( error );
			}
		);
	} );
} );
