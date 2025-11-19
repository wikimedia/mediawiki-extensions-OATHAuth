<?php

namespace MediaWiki\Extension\WebAuthn\Tests\Integration;

use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\WebAuthn\Key\WebAuthnKey
 */
class WebAuthnKeyTest extends MediaWikiIntegrationTestCase {

	public function testPasswordlessLoginPasskeysSupportsPasswordless(): void {
		$key = WebAuthnKey::newFromData(
		[ 'supportsPasswordless' => true, 'userHandle' => 'fakeHandle',
				'friendlyName' => 'testKey', 'counter' => 3, 'transports' => [],
				'aaguid' => 'f7dcf1ec-76ad-49cb-ae2a-6d8ed6736f88', 'publicKeyCredentialId' => 'none',
				'credentialPublicKey' => 'none' ] );
		$this->assertSame( true, $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysDoesntSupportPasswordless(): void {
		$key = WebAuthnKey::newFromData(
		[ 'supportsPasswordless' => false, 'userHandle' => 'fakeHandle',
				'friendlyName' => 'testKey', 'counter' => 3, 'transports' => [],
				'aaguid' => 'f7dcf1ec-76ad-49cb-ae2a-6d8ed6736f88', 'publicKeyCredentialId' => 'none',
				'credentialPublicKey' => 'none' ] );
		$this->assertSame( false, $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysSupportPasswordlessMissing(): void {
		$key = WebAuthnKey::newFromData(
		[ 'friendlyName' => 'testKey', 'userHandle' => 'fakeHandle', 'counter' => 3, 'transports' => [],
				'aaguid' => 'f7dcf1ec-76ad-49cb-ae2a-6d8ed6736f88', 'publicKeyCredentialId' => 'none',
				'credentialPublicKey' => 'none' ] );
		$this->assertSame( false, $key->supportsPasswordlessLogin() );
	}
}
