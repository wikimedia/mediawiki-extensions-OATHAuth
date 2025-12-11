const { createMwApp } = require( 'vue' );
const AddPasskeyButton = require( './AddPasskeyButton.vue' );

// The "Add passkey" button may not exist, because it's not shown to users who don't have 2FA
// set up
const addPasskeyButton = document.querySelector( '.mw-special-OATHManage-passkeys__addbutton' );
if ( addPasskeyButton ) {
	// Wrap the passkey button in a div first, otherwise createMwApp() will destroy all the
	// siblings of the button
	const wrapper = document.createElement( 'div' );
	addPasskeyButton.parentNode.insertBefore( wrapper, addPasskeyButton );
	wrapper.appendChild( addPasskeyButton );
	createMwApp( AddPasskeyButton ).mount( wrapper );
}
