( function () {
	$( () => {
		const form = new mw.ext.webauthn.RegisterFormWidget();

		form.on( 'addKey', ( desiredName ) => {
			const registrator = new mw.ext.webauthn.Registrator( desiredName );
			registrator.register().then(
				( credential ) => {
					form.readyToSubmit = true;
					form.submitWithCredential( credential );
				},
				( error ) => {
					form.dieWithError( error );
				}
			);
		} );
	} );
}() );
