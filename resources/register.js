( function( $, mw ) {
	$( function() {
		var form = new mw.ext.webauthn.RegisterFormWidget();

		form.on( 'addKey', function( desiredName ) {
			var registrator = new mw.ext.webauthn.Registrator( desiredName );
			registrator.register().then(
				function( credential ) {
					form.readyToSubmit = true;
					form.submitWithCredential( credential );
				},
				function( error ) {
					form.dieWithError( error );
				}
			);
		} );
	} );
} )( jQuery, mediaWiki );

