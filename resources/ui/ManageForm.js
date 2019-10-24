( function () {

	mw.ext.webauthn.ManageFormWidget = function () {
		mw.ext.webauthn.ManageFormWidget.parent.call( this, {
			$form: $( '#webauthn-manage-form' )
		} );

		this.setupRegisteredKeys();

		// Enable the "add key" button
		if ( this.$form.children( 'span#button_add_key' ).length > 0 ) {
			OO.ui.ButtonWidget.static.infuse(
				this.$form.children( 'span#button_add_key' )
			).setDisabled( false );
		}
	};

	OO.inheritClass( mw.ext.webauthn.ManageFormWidget, mw.ext.webauthn.CredentialForm );

	mw.ext.webauthn.ManageFormWidget.prototype.setupRegisteredKeys = function () {
		this.$form.find( '.webauthn-key-layout' )
			.each( function ( k, keyLayout ) {
				var $keyLayout = $( keyLayout );
				var removeButton = OO.ui.ButtonInputWidget.static.infuse(
					$keyLayout.find( '.removeButton' )
				);
				removeButton.setDisabled( false );
				removeButton.on( 'click', function () {
					this.onRemoveClick( removeButton.getValue() );
				}.bind( this ) );
			}.bind( this ) );
	};

	mw.ext.webauthn.ManageFormWidget.prototype.onRemoveClick = function ( friendlyName ) {
		var authenticator = new mw.ext.webauthn.Authenticator();
		authenticator.authenticate().done(
			function ( credential ) {
				this.$form.find( 'input[name="remove_key"]' ).val( friendlyName );
				this.setCredential( credential );
				this.$form.trigger( 'submit' );
			}.bind( this )
		);
	};
}() );
