$( () => {
	const form = new mw.ext.webauthn.LoginFormWidget();

	const authenticator = new mw.ext.webauthn.Authenticator(
		form.getAuthInfo()
	);

	function auth() {
		authenticator.authenticate().then(
			( credential ) => {
				form.submitWithCredential( credential );
			},
			( error ) => {
				form.dieWithError( error );
			}
		);
	}

	// If there is a "Continue with security key" button, wait for the user to click it
	// Otherwise, initiate the WebAuthn authentication immediately
	if ( $( '#mw-input-webauthnButton' ).length ) {
		$( '#mw-input-webauthnButton' ).on( 'click', ( e ) => {
			e.preventDefault();
			auth();
		} );
	} else {
		auth();
	}
} );
