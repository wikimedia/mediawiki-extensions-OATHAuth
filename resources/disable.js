( function( $, mw ) {
	$( function() {
		var form = new mw.ext.webauthn.CredentialForm( {
			$form: $( '#disable-webauthn-form' )
		} );

		var authenticator = new mw.ext.webauthn.Authenticator();
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
