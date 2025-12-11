$( () => {
	const form = new mw.ext.webauthn.RegisterFormWidget();

	form.on( 'addKey', ( desiredName ) => {
		const passkeyMode = !!Number( new URLSearchParams( document.location.search ).get( 'passkeyMode' ) );
		const registrator = new mw.ext.webauthn.Registrator( desiredName, null, passkeyMode );
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
