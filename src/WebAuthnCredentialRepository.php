<?php

namespace MediaWiki\Extension\WebAuthn;

use Base64Url\Base64Url;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Key\WebAuthnKey;
use MediaWiki\MediaWikiServices;
use User;
use FormatJson;
use Database;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use MWException;
use ConfigException;

class WebAuthnCredentialRepository implements PublicKeyCredentialSourceRepository {
	/**
	 * @var array
	 */
	protected $credentials = [];

	/**
	 * @var bool
	 */
	protected $loaded = false;

	/**
	 * @var Database
	 */
	protected $db;

	/**
	 * @var WebAuthn
	 */
	protected $module;

	/**
	 * @param User $mwUser
	 * @param bool $lc Whether to return lowercased names
	 * @return array
	 */
	public function getFriendlyNamesForMWUser( User $mwUser, $lc = false ) {
		$this->load();
		$friendlyNames = [];
		foreach ( $this->credentials as $id => $data ) {
			if ( $data['userMWId'] !== $mwUser->getId() ) {
				continue;
			}
			$friendlyName = $data['friendlyName'];
			if ( $lc ) {
				$friendlyName = strtolower( $friendlyName );
			}
			$friendlyNames[] = $friendlyName;
		}
		return $friendlyNames;
	}

	/**
	 * @param string $publicKeyCredentialId
	 * @return PublicKeyCredentialSource|null
	 */
	public function findOneByCredentialId(
		string $publicKeyCredentialId
	): ?PublicKeyCredentialSource {
		$this->load();
		if ( isset( $this->credentials[$publicKeyCredentialId] ) ) {
			return PublicKeyCredentialSource::createFromArray( $this->credentials[$publicKeyCredentialId] );
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
		foreach ( $this->credentials as $credId => $data ) {
			if ( $data['userHandle'] === $publicKeyCredentialUserEntity->getId() ) {
				$res[] = PublicKeyCredentialSource::createFromArray( $data );
			}
		}
		return $res;
	}

	/**
	 * @param PublicKeyCredentialSource $publicKeyCredentialSource
	 * @throws MWException
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
	 * Loads credentials from DB
	 */
	private function load() {
		if ( $this->loaded ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		/** @var OATHAuth $oath */
		$oath = $services->getService( 'OATHAuth' );
		$this->module = $oath->getModuleByKey( 'webauthn' );

		$database = $services->getMainConfig()->get( 'OATHAuthDatabase' );

		$lb = $services->getDBLoadBalancerFactory()->getMainLB( $database );
		$this->db = $lb->getConnectionRef( DB_MASTER, [], $database );

		$res = $this->db->select(
			'oathauth_users',
			[ 'id', 'data' ],
			[ 'module' => 'webauthn' ]
		);

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data );
			foreach ( $data->keys as $key ) {
				$data = [
					'userHandle' => Base64Url::encode( base64_decode( $key->userHandle ) ),
					'aaguid' => $key->aaguid,
					'friendlyName' => $key->friendlyName,
					'publicKeyCredentialId' => Base64Url::encode( base64_decode( $key->publicKeyCredentialId ) ),
					'credentialPublicKey' => Base64Url::encode( base64_decode( $key->credentialPublicKey ) ),
					'counter' => (int)$key->counter,
					'userMWId' => (int)$row->id,
					'type' => $key->type,
					'transports' => $key->transports,
					'attestationType' => $key->attestationType,
					'trustPath' => (array)$key->trustPath
				];

				$decodedCredentialId = base64_decode( $key->publicKeyCredentialId );
				$this->credentials[$decodedCredentialId] = $data;
			}
		}
		$this->loaded = true;
	}

	/**
	 * Set new sign counter value for the credential
	 *
	 * @param string $credentialId
	 * @param int $newCounter
	 * @throws MWException
	 * @throws \ConfigException
	 */
	private function updateCounterFor( string $credentialId, int $newCounter ): void {
		$this->load();
		// Do this over the module - do not edit raw DB data
		$mwUserId = $this->credentials[$credentialId]['userMWId'];
		/** @var OATHUserRepository $userRepo */
		$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$oathUser = $userRepo->findByUser( User::newFromId( $mwUserId ) );
		$key = $this->module->findKeyByCredentialId( $credentialId, $oathUser );
		if ( $key === null || !( $key instanceof WebAuthnKey ) ) {
			return;
		}
		$key->setSignCounter( $newCounter );
		$dbData = $this->module->getDataFromUser( $oathUser );
		$this->db->update(
			'oathauth_users',
			[ 'data' => FormatJson::encode( $dbData ) ],
			[ 'id' => $mwUserId, 'module' => 'webauthn' ]
		);
	}
}
