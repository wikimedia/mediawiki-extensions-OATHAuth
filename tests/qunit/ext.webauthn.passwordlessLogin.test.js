'use strict';

QUnit.module( 'ext.webauthn.passwordlessLogin', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.originalLoginFormWidget = mw.ext.webauthn.LoginFormWidget;
		this.originalAuthenticator = mw.ext.webauthn.Authenticator;
		this.originalPublicKeyCredential = window.PublicKeyCredential;

		// Stub LoginFormWidget so its constructor doesn't query #userloginForm
		// in the real document.
		mw.ext.webauthn.LoginFormWidget = function () {};
		mw.ext.webauthn.LoginFormWidget.prototype.getAuthInfo = function () {
			return {};
		};

		// Stub Authenticator so .authenticate() doesn't hit navigator.credentials.
		this.authenticateStub = this.sandbox.stub().returns( $.Deferred().promise() );
		const authenticateStub = this.authenticateStub;
		mw.ext.webauthn.Authenticator = function () {
			this.authenticate = authenticateStub;
		};

		this.isConditionalMediationAvailableStub = this.sandbox.stub()
			.returns( Promise.resolve( false ) );
		window.PublicKeyCredential = {
			isConditionalMediationAvailable: this.isConditionalMediationAvailableStub
		};
	},
	afterEach: function () {
		mw.ext.webauthn.LoginFormWidget = this.originalLoginFormWidget;
		mw.ext.webauthn.Authenticator = this.originalAuthenticator;
		if ( this.originalPublicKeyCredential ) {
			window.PublicKeyCredential = this.originalPublicKeyCredential;
		} else {
			delete window.PublicKeyCredential;
		}
	}
} ) );

QUnit.test( 'skips conditional mediation when .mw-userlogin-username is absent (T427419)', function ( assert ) {
	// Second stage of 2FA login: no username field in the DOM.
	const $fixture = $( '#qunit-fixture' );

	mw.ext.webauthn.initPasswordlessLogin( $fixture );

	assert.strictEqual(
		this.isConditionalMediationAvailableStub.called,
		false,
		'PublicKeyCredential.isConditionalMediationAvailable is not invoked'
	);
} );

QUnit.test( 'invokes conditional mediation when .mw-userlogin-username is present', function ( assert ) {
	const $fixture = $( '#qunit-fixture' );
	$fixture.append( $( '<input>' ).addClass( 'mw-userlogin-username' ) );

	mw.ext.webauthn.initPasswordlessLogin( $fixture );

	assert.strictEqual(
		this.isConditionalMediationAvailableStub.called,
		true,
		'PublicKeyCredential.isConditionalMediationAvailable is invoked'
	);
} );

QUnit.test( 'requests conditional authentication when mediation is available', function ( assert ) {
	this.isConditionalMediationAvailableStub.returns( Promise.resolve( true ) );
	const $fixture = $( '#qunit-fixture' );
	$fixture.append( $( '<input>' ).addClass( 'mw-userlogin-username' ) );

	mw.ext.webauthn.initPasswordlessLogin( $fixture );

	// Wait for the isConditionalMediationAvailable promise to resolve and chain
	// through to authenticator.authenticate( true ).
	return Promise.resolve().then( () => {
		assert.strictEqual(
			this.authenticateStub.callCount,
			1,
			'Authenticator.authenticate() is called once'
		);
		assert.strictEqual(
			this.authenticateStub.firstCall.args[ 0 ],
			true,
			'Authenticator.authenticate() is called with conditional=true'
		);
	} );
} );

QUnit.test( 'does not throw when PublicKeyCredential is unavailable', ( assert ) => {
	delete window.PublicKeyCredential;
	const $fixture = $( '#qunit-fixture' );
	$fixture.append( $( '<input>' ).addClass( 'mw-userlogin-username' ) );

	mw.ext.webauthn.initPasswordlessLogin( $fixture );

	assert.true( true, 'init completed without throwing' );
} );

QUnit.test( '#mw-input-passwordlessButton click triggers authentication', function ( assert ) {
	const $fixture = $( '#qunit-fixture' );
	const $button = $( '<button>' ).attr( 'id', 'mw-input-passwordlessButton' );
	$fixture.append( $button );

	mw.ext.webauthn.initPasswordlessLogin( $fixture );

	$button.trigger( 'click' );
	assert.strictEqual(
		this.authenticateStub.callCount,
		1,
		'Authenticator.authenticate() is called when the passkey button is clicked'
	);
} );
