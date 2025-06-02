mw.ext.webauthn.CredentialForm = function ( cfg ) {
	cfg = cfg || {};
	this.$form = cfg.$form;
	this.$form.addClass( 'webauth-credential-form' );

	if ( !window.PublicKeyCredential ) {
		this.dieWithError(
			'webauthn-error-browser-unsupported',
			'webauthn-error-browser-unsupported-console'
		);
	}

	this.setControls();
	OO.EventEmitter.call( this );
};

OO.initClass( mw.ext.webauthn.CredentialForm );
OO.mixinClass( mw.ext.webauthn.CredentialForm, OO.EventEmitter );

mw.ext.webauthn.CredentialForm.prototype.setControls = function () {
	this.$credential = this.$form.find( 'input[name="credential"]' );
};

mw.ext.webauthn.CredentialForm.prototype.dieWithError = function ( message, consoleMsg ) {
	consoleMsg = consoleMsg || message;

	// Unrecoverable in this load - remove all content
	this.$form.children().hide();

	const errorMessage = new OO.ui.MessageWidget( {
		type: 'error',
		label: new OO.ui.HtmlSnippet( this.getErrorText( message || '' ) )
	} );

	const reloadButton = new OO.ui.ButtonWidget( {
		label: mw.message( 'webauthn-ui-reload-page-label' ).text()
	} );
	reloadButton.connect( this, {
		click: function () {
			window.location.reload();
		}
	} );

	this.$form.append(
		errorMessage.$element,
		$( '<p>' ).append( reloadButton.$element )
	);

	throw new Error( this.getErrorText( consoleMsg || '' ) );
};

mw.ext.webauthn.CredentialForm.prototype.getErrorText = function ( error ) {
	const message = mw.message( error );
	if ( message.exists() ) {
		return message.parse();
	}
	return error;
};

mw.ext.webauthn.CredentialForm.prototype.setCredential = function ( credential ) {
	credential = JSON.stringify( credential );
	this.$credential.val( credential );
};

mw.ext.webauthn.CredentialForm.prototype.submitWithCredential = function ( credential ) {
	this.setCredential( credential );
	this.$form.trigger( 'submit' );
};
