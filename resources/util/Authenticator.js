mw.ext.webauthn.Authenticator = function ( authInfo ) {
	OO.EventEmitter.call( this );
	this.authInfo = authInfo || null;
};

OO.initClass( mw.ext.webauthn.Authenticator );
OO.mixinClass( mw.ext.webauthn.Authenticator, OO.EventEmitter );

mw.ext.webauthn.Authenticator.prototype.authenticate = function () {
	const dfd = $.Deferred();
	if ( this.authInfo === null ) {
		this.getAuthInfo().done( ( response ) => {
			if ( !response.webauthn.hasOwnProperty( 'auth_info' ) ) {
				dfd.reject( 'webauthn-error-get-authinfo-fail' );
			}
			this.authInfo = response.webauthn.auth_info;
			this.authInfo = JSON.parse( this.authInfo );
			this.authenticateWithAuthInfo( dfd );
		} ).fail( ( error ) => {
			dfd.reject( error );
		}
		);
	} else {
		this.authenticateWithAuthInfo( dfd );
	}
	return dfd.promise();
};

mw.ext.webauthn.Authenticator.prototype.getAuthInfo = function () {
	return new mw.Api().get( {
		action: 'webauthn',
		func: 'getAuthInfo'
	} );
};

mw.ext.webauthn.Authenticator.prototype.authenticateWithAuthInfo = function ( dfd ) {
	// At this point we assume authInfo is set
	this.getCredentials()
		.then( ( assertion ) => {
			dfd.resolve( this.formatCredential( assertion ) );
		} )
		.catch( () => {
			// This usually happens when the process gets interrupted
			// - show generic interrupt error
			dfd.reject( 'webauthn-error-auth-generic' );
		} );
};

mw.ext.webauthn.Authenticator.prototype.getCredentials = function () {
	const publicKey = this.authInfo;
	publicKey.challenge = mw.ext.webauthn.util.base64ToByteArray( publicKey.challenge );

	publicKey.allowCredentials = publicKey.allowCredentials.map( ( data ) => Object.assign( data, {
		id: mw.ext.webauthn.util.base64ToByteArray( data.id )
	} ) );

	mw.log( 'PublicKeyCredentialRequestOptions: ', publicKey );
	return navigator.credentials.get( { publicKey: publicKey } ).then( ( credential ) => {
		mw.log( 'Credential:\n' + JSON.stringify( credential, null, 4 ) );
		return credential;
	} );
};

mw.ext.webauthn.Authenticator.prototype.formatCredential = function ( assertion ) {
	// encoding should match PublicKeyCredentialLoader::loadArray()
	this.credential = {
		id: assertion.id, // base64url encoded
		type: assertion.type,
		rawId: mw.ext.webauthn.util.byteArrayToBase64( new Uint8Array( assertion.rawId ),
			'base64', 'padded' ),
		response: {
			authenticatorData: mw.ext.webauthn.util.byteArrayToBase64(
				new Uint8Array( assertion.response.authenticatorData ), 'base64url', 'unpadded' ),
			// encoding should match CollectedClientData::createFormJson()
			clientDataJSON: mw.ext.webauthn.util.byteArrayToBase64(
				new Uint8Array( assertion.response.clientDataJSON ), 'base64url', 'unpadded' ),
			signature: mw.ext.webauthn.util.byteArrayToBase64(
				new Uint8Array( assertion.response.signature ), 'base64', 'padded' ),
			userHandle: assertion.response.userHandle ?
				mw.ext.webauthn.util.byteArrayToBase64( new Uint8Array( assertion.response.userHandle ),
					'base64', 'padded' ) :
				null
		}
	};
	return this.credential;
};
