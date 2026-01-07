<template>
	<!-- Passkeys section (show first if user has 2FA keys) -->
	<div v-if="hasPasskeys" class="mw-special-OATHManage-passkeys">
		<h3>{{ $i18n( 'oathauth-passkeys-header' ) }}</h3>

		<cdx-accordion
			v-for="key in data.passkeys"
			:key="key.id"
			separation="outline"
		>
			<template #title>
				{{ key.name }}
			</template>
			<template #description>
				{{ key.timestamp }}
			</template>
			<form :action="formAction" class="mw-special-OATHManage-authmethods__method-actions">
				<input
					type="hidden"
					name="title"
					:value="pageTitle">
				<input
					type="hidden"
					name="module"
					:value="key.module">
				<input
					type="hidden"
					name="keyId"
					:value="key.id">
				<input
					type="hidden"
					name="warn"
					value="1">
				<cdx-button
					action="destructive"
					weight="primary"
					type="submit"
					name="action"
					value="delete"
				>
					{{ $i18n( 'oathauth-authenticator-delete' ) }}
				</cdx-button>
			</form>
		</cdx-accordion>
		<div class="mw-special-OATHManage-authmethods__addform">
			<add-passkey-button></add-passkey-button>
		</div>
	</div>

	<!-- Empty passkeys section -->
	<div v-if="hasKeys && !hasPasskeys" class="mw-special-OATHManage-passkeys--no-keys">
		<div class="mw-special-OATHManage-authmethods__addform">
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-placeholder' ) }}
			</p>
			<add-passkey-button></add-passkey-button>
		</div>
	</div>

	<!-- 2FA/Auth methods section -->
	<div
		class="mw-special-OATHManage-authmethods"
		:class="{ 'mw-special-OATHManage-authmethods--no-keys': !hasKeys }">
		<h3>{{ $i18n( 'oathauth-authenticator-header' ) }}</h3>
		<cdx-accordion
			v-for="key in data.keys"
			:key="key.id"
			separation="outline">
			<template #title>
				{{ key.name }}
			</template>
			<template #description>
				{{ key.timestamp }}
			</template>
			<form :action="formAction" class="mw-special-OATHManage-authmethods__method-actions">
				<input
					type="hidden"
					name="title"
					:value="pageTitle">
				<input
					type="hidden"
					name="module"
					:value="key.module">
				<input
					type="hidden"
					name="keyId"
					:value="key.id">
				<input
					type="hidden"
					name="warn"
					value="1">
				<cdx-button
					action="destructive"
					weight="primary"
					type="submit"
					name="action"
					value="delete"
				>
					{{ $i18n( 'oathauth-authenticator-delete' ) }}
				</cdx-button>
			</form>
		</cdx-accordion>

		<form :action="formAction" class="mw-special-OATHManage-authmethods__addform">
			<input
				type="hidden"
				name="title"
				:value="pageTitle">
			<input
				type="hidden"
				name="action"
				value="enable">

			<p v-if="!hasKeys" class="mw-special-OATHManage-authmethods__placeholder">
				{{ $i18n( 'oathauth-authenticator-placeholder' ) }}
			</p>

			<cdx-button
				v-for="module in data.modules"
				:key="module.name"
				type="submit"
				name="module"
				:value="module.name"
			>
				{{ module.labelMessage }}
			</cdx-button>
		</form>
	</div>

	<!-- Empty passkey without MFA section -->
	<div v-if="!hasKeys" class="mw-special-OATHManage-passkeys--no-keys">
		<h3>{{ $i18n( 'oathauth-passkeys-header' ) }}</h3>
		<div class="mw-special-OATHManage-authmethods__addform">
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-placeholder' ) }}
			</p>
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-no2fa' ) }}
			</p>
		</div>
	</div>
</template>

<script>
const { defineComponent, reactive, computed } = require( 'vue' );
const { CdxButton, CdxAccordion } = require( './codex.js' );
const AddPasskeyButton = require( './AddPasskeyButton.vue' );

module.exports = exports = defineComponent( {
	components: {
		CdxButton,
		CdxAccordion,
		AddPasskeyButton
	},
	setup() {
		const data = reactive( mw.config.get( 'wgOATHManageData' ) );
		const hasKeys = computed( () => data.keys.length > 0 );
		const hasPasskeys = computed( () => data.passkeys.length > 0 );

		const formAction = mw.config.get( 'wgScript' );
		const pageTitle = mw.config.get( 'wgPageName' );

		return {
			data,
			formAction,
			pageTitle,
			hasKeys,
			hasPasskeys
		};
	}
} );
</script>
