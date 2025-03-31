( function () {
	$( () => {
		const form = new mw.ext.webauthn.CredentialForm( {
			$form: $( '#disable-webauthn-form' )
		} );

		const authenticator = new mw.ext.webauthn.Authenticator();
		authenticator.authenticate().then(
			( credential ) => {
				form.submitWithCredential( credential );
			},
			( error ) => {
				form.dieWithError( error );
			}
		);
	} );
}() );
