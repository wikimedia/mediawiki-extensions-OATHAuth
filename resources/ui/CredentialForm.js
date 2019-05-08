( function( mw, $ ) {

	mw.ext.webauthn.CredentialForm = function( cfg ) {
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

	mw.ext.webauthn.CredentialForm.prototype.setControls = function() {
		this.$credential = this.$form.find( 'input[name="credential"]' );
	};

	mw.ext.webauthn.CredentialForm.prototype.dieWithError = function( message, consoleMsg ) {
		consoleMsg = consoleMsg || message;

		// Unrecoverable in this load - remove all content
		this.$form.children().remove();
		var icon = new OO.ui.IconWidget( {
			icon: 'alert'
		} );
		var label = new OO.ui.LabelWidget();
		label.$element.append( this.getErrorText( message || '' ) );

		var reloadLink = new OO.ui.ButtonWidget( {
			label: mw.message( 'webauthn-ui-reload-page-label' ).text(),
			framed: false
		} );
		reloadLink.connect( this, {
			click: function() {
				window.location.reload();
			}
		} );
		this.$form.append( new OO.ui.HorizontalLayout( {
			items: [
				icon, label, reloadLink
			],
			classes:[ 'form-error-message' ]
		} ).$element );

		throw new Error(  this.getErrorText( consoleMsg || '' ) );
	};

	mw.ext.webauthn.CredentialForm.prototype.getErrorText = function( error ) {
		var message = mw.message( error );
		if ( message.exists() ) {
			return message.parse();
		}
		return error;
	};


	mw.ext.webauthn.CredentialForm.prototype.setCredential = function( credential ) {
		credential = JSON.stringify( credential );
		this.$credential.val( credential );
	};

	mw.ext.webauthn.CredentialForm.prototype.submitWithCredential = function( credential ) {
		this.setCredential( credential );
		this.$form.submit();
	};

} )( mediaWiki, jQuery );
