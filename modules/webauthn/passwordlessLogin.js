/* global PublicKeyCredential:false */

$( () => {
	const form = new mw.ext.webauthn.LoginFormWidget();
	const authenticator = new mw.ext.webauthn.Authenticator( form.getAuthInfo() );

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
