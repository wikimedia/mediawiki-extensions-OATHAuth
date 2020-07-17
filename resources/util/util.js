mw.ext = mw.ext || {};
mw.ext.webauthn = mw.ext.webauthn || {};

mw.ext.webauthn.util = {
	base64url2base64: function ( input ) {
		input = input
			.replace( /=/g, '' )
			.replace( /-/g, '+' )
			.replace( /_/g, '/' );

		const pad = input.length % 4;
		if ( pad ) {
			if ( pad === 1 ) {
				throw new Error( 'InvalidLengthError: Input base64url string is the wrong length to determine padding' );
			}
			input += new Array( 5 - pad ).join( '=' );
		}

		return input;
	}
};
