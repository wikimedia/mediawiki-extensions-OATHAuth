<template>
	<cdx-button
		:disabled="!supportsPasskeys"
		@click.prevent="open = true"
	>
		{{ $i18n( 'oathauth-passkeys-add' ) }}
	</cdx-button>
	<add-passkey-dialog v-model:open="open"></add-passkey-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton } = require( './codex.js' );
const AddPasskeyDialog = require( './AddPasskeyDialog.vue' );

/**
 * Feature detection for the browser's support for passkeys. If the browser doesn't support the
 * passkey features we need, this returns false, and the "Add passkey" button will be disabled.
 *
 * @return {boolean}
 */
async function checkPasskeySupport() {
	if ( !window.PublicKeyCredential ) {
		return false;
	}
	// Try the mode modern getClientCapabilities API first
	if ( window.PublicKeyCredential.getClientCapabilities ) {
		const capabilities = await window.PublicKeyCredential.getClientCapabilities();
		return capabilities.userVerifyingPlatformAuthenticator;
	}
	// If that's not available, fall back to the older API
	if ( window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable ) {
		return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
	}
	return false;
}

module.exports = exports = defineComponent( {
	components: {
		CdxButton,
		AddPasskeyDialog
	},
	setup() {
		const open = ref( false );
		const supportsPasskeys = ref( true );
		checkPasskeySupport().then( ( support ) => {
			supportsPasskeys.value = support;
		} );

		return {
			supportsPasskeys,
			open
		};
	}
} );
</script>
