<?php

namespace MediaWiki\Extension\WebAuthn;

use Base64Url\Base64Url;
use Generator;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnCredentialRepository implements PublicKeyCredentialSourceRepository {
	private OATHUser $oauthUser;

	public function __construct( OATHUser $user ) {
		$this->oauthUser = $user;
	}

	/**
	 * @return Generator<WebAuthnKey>
	 */
	private function getWebAuthnKeys(): Generator {
		foreach ( $this->oauthUser->getKeys() as $key ) {
			if ( !$key instanceof WebAuthnKey ) {
				continue;
			}

			yield $key;
		}
	}

	/**
	 * @param bool $lc Whether to return the names in lowercase form
	 * @return array
	 */
	public function getFriendlyNames( $lc = false ) {
		$friendlyNames = [];
		foreach ( $this->getWebAuthnKeys() as $key ) {
			$friendlyName = $key->getFriendlyName();
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
		foreach ( $this->getWebAuthnKeys() as $key ) {
			if ( $key->getAttestedCredentialData()->getCredentialId() === $publicKeyCredentialId ) {
				return $this->credentialSourceFromKey( $key );
			}
		}

		return null;
	}

	/**
	 * @param PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity
	 * @return PublicKeyCredentialSource[]
	 */
	public function findAllForUserEntity(
		PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity
	): array {
		$res = [];
		foreach ( $this->getWebAuthnKeys() as $key ) {
			if ( $key->getUserHandle() === $publicKeyCredentialUserEntity->getId() ) {
				$res[] = $this->credentialSourceFromKey( $key );
			}
		}

		return $res;
	}

	/**
	 * @param PublicKeyCredentialSource $publicKeyCredentialSource
	 * @throws ConfigException
	 */
	public function saveCredentialSource(
		PublicKeyCredentialSource $publicKeyCredentialSource
	): void {
		$this->updateCounterFor(
			$publicKeyCredentialSource->getPublicKeyCredentialId(),
			$publicKeyCredentialSource->getCounter()
		);
	}

	/**
	 * @param WebAuthnKey $key
	 * @return PublicKeyCredentialSource
	 */
	private function credentialSourceFromKey( WebAuthnKey $key ) {
		return PublicKeyCredentialSource::createFromArray( [
			'userHandle' => Base64Url::encode( $key->getUserHandle() ),
			'aaguid' => $key->getAttestedCredentialData()->getAaguid()->toString(),
			'friendlyName' => $key->getFriendlyName(),
			'publicKeyCredentialId' => Base64Url::encode(
				$key->getAttestedCredentialData()->getCredentialId()
			),
			'credentialPublicKey' => Base64Url::encode(
				(string)$key->getAttestedCredentialData()->getCredentialPublicKey()
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
	 *
	 * @param string $credentialId
	 * @param int $newCounter
	 */
	private function updateCounterFor( string $credentialId, int $newCounter ): void {
		foreach ( $this->getWebAuthnKeys() as $key ) {
			if ( $key->getAttestedCredentialData()->getCredentialId() !== $credentialId ) {
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
