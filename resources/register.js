( function () {
	$( function () {
		const form = new mw.ext.webauthn.RegisterFormWidget();

		form.on( 'addKey', function ( desiredName ) {
			const registrator = new mw.ext.webauthn.Registrator( desiredName );
			registrator.register().then(
				function ( credential ) {
					form.readyToSubmit = true;
					form.submitWithCredential( credential );
				},
				function ( error ) {
					form.dieWithError( error );
				}
			);
		} );
	} );
}() );
