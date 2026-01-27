/* global PublicKeyCredential:false */
if ( window.PublicKeyCredential && PublicKeyCredential.isConditionalMediationAvailable ) {
	PublicKeyCredential.isConditionalMediationAvailable().then( ( isAvailable ) => {
		if ( !isAvailable ) {
			return;
		}

		$( () => {
			if ( $( '.mw-userlogin-username' ).length === 0 ) {
				return;
			}
			const form = new mw.ext.webauthn.LoginFormWidget();

			const authenticator = new mw.ext.webauthn.Authenticator(
				form.getAuthInfo(),
				true // passwordless login
			);
			// TODO retry after timeout
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
}
