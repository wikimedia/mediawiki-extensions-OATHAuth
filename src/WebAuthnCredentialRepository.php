<?php

namespace MediaWiki\Extension\WebAuthn;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnCredentialRepository implements PublicKeyCredentialSourceRepository {
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

	/**
	 * @return PublicKeyCredentialSource[]
	 */
	public function findAllForUserEntity(
		PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity
	): array {
		$res = [];
		foreach ( WebAuthn::getWebAuthnKeys( $this->oauthUser ) as $key ) {
			if ( $key->getUserHandle() === $publicKeyCredentialUserEntity->id ) {
				$res[] = $this->credentialSourceFromKey( $key );
			}
		}

		return $res;
	}

	/**
	 * @throws ConfigException
	 */
	public function saveCredentialSource(
		PublicKeyCredentialSource $publicKeyCredentialSource
	): void {
		$this->updateCounterFor(
			$publicKeyCredentialSource->publicKeyCredentialId,
			$publicKeyCredentialSource->counter
		);
	}

	private function credentialSourceFromKey( WebAuthnKey $key ): PublicKeyCredentialSource {
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
			'trustPath' => $key->getTrustPath()->jsonSerialize()
		] );
	}

	/**
	 * Set a new sign counter-value for the credential
	 */
	private function updateCounterFor( string $credentialId, int $newCounter ): void {
		foreach ( WebAuthn::getWebAuthnKeys( $this->oauthUser ) as $key ) {
			if ( $key->getAttestedCredentialData()->credentialId !== $credentialId ) {
				continue;
			}

			$key->setSignCounter( $newCounter );

			OATHAuthServices::getInstance()
				->getUserRepository()
				->updateKey(
					$this->oauthUser,
					$key
				);
		}
	}
}
