<?php

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
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
		// TODO: createFromArray() is deprecated. Use Webauthn\Denormalizer\WebauthnSerializerFactory to create
		return PublicKeyCredentialSource::createFromArray( [
			'userHandle' => Base64UrlSafe::encodeUnpadded( $key->getUserHandle() ),
			'aaguid' => (string)$key->getAttestedCredentialData()->aaguid,
			'friendlyName' => $key->getFriendlyName(),
			'publicKeyCredentialId' => Base64UrlSafe::encodeUnpadded(
				$key->getAttestedCredentialData()->credentialId
			),
			'credentialPublicKey' => Base64UrlSafe::encodeUnpadded(
				(string)$key->getAttestedCredentialData()->credentialPublicKey
			),
			'counter' => $key->getSignCounter(),
			'userMWId' => $this->oauthUser->getUser()->getId(),
			'type' => $key->getType(),
			'transports' => $key->getTransports(),
			'attestationType' => $key->getAttestationType(),
			// TODO: Can we actually call jsonSerialize()?
			'trustPath' => $key->getTrustPath()->jsonSerialize(),
		] );
	}
}
