( function( $, mw ) {
	$( function() {
		var form = new mw.ext.webauthn.LoginFormWidget();

		var authenticator = new mw.ext.webauthn.Authenticator(
			form.getAuthInfo()
		);
		authenticator.authenticate().then(
			function( credential ) {
				form.submitWithCredential( credential );
			},
			function( error ) {
				form.dieWithError( error );
			}
		);
	} );
} ) ( jQuery, mediaWiki );
