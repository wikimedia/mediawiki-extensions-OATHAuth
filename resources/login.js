( function () {
	$( function () {
		const form = new mw.ext.webauthn.LoginFormWidget();

		const authenticator = new mw.ext.webauthn.Authenticator(
			form.getAuthInfo()
		);
		authenticator.authenticate().then(
			function ( credential ) {
				form.submitWithCredential( credential );
			},
			function ( error ) {
				form.dieWithError( error );
			}
		);
	} );
}() );
