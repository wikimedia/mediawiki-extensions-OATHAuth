<?php

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use Webauthn\PublicKeyCredentialSource;

class WebAuthnCredentialRepository {
	public function __construct( private OATHUser $oauthUser ) {
	}

	/**
	 * @param bool $lc Whether to return the names in lowercase form
	 */
	public function getFriendlyNames( bool $lc = false ): array {
		$friendlyNames = [];
		foreach ( WebAuthn::getWebAuthnKeys( $this->oauthUser ) as $key ) {
			$friendlyName = $key->getFriendlyName();
			if ( $friendlyName === null ) {
				continue;
			}
			if ( $lc ) {
				$friendlyName = strtolower( $friendlyName );
			}
			$friendlyNames[] = $friendlyName;
		}
		return $friendlyNames;
	}

	public function findOneByCredentialId(
		string $publicKeyCredentialId
	): ?PublicKeyCredentialSource {
		foreach ( WebAuthn::getWebAuthnKeys( $this->oauthUser ) as $key ) {
			if ( $key->getAttestedCredentialData()->credentialId === $publicKeyCredentialId ) {
				return $this->credentialSourceFromKey( $key );
			}
		}

		return null;
	}

	private function credentialSourceFromKey( WebAuthnKey $key ): PublicKeyCredentialSource {
		return PublicKeyCredentialSource::create(
			publicKeyCredentialId: $key->getAttestedCredentialData()->credentialId,
			type: $key->getType(),
			transports: $key->getTransports(),
			attestationType: $key->getAttestationType(),
			trustPath: $key->getTrustPath(),
			aaguid: $key->getAttestedCredentialData()->aaguid,
			credentialPublicKey: (string)$key->getAttestedCredentialData()->credentialPublicKey,
			userHandle: $key->getUserHandle(),
			counter: $key->getSignCounter(),
		);
	}
}
