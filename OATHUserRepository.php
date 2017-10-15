<?php

class OATHUserRepository {
	/** @var LoadBalancer */
	protected $lb;

	/** @var BagOStuff */
	protected $cache;

	/**
	 * OATHUserRepository constructor.
	 * @param LoadBalancer $lb
	 * @param BagOStuff $cache
	 */
	public function __construct( LoadBalancer $lb, BagOStuff $cache ) {
		$this->lb = $lb;
		$this->cache = $cache;
	}

	/**
	 * @param User $user
	 * @return OATHUser
	 */
	public function findByUser( User $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$oathUser = new OATHUser( $user, null );

			$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
			$res = $this->getDB( DB_REPLICA )->selectRow(
				'oathauth_users',
				'*',
				[ 'id' => $uid ],
				__METHOD__
			);
			if ( $res ) {
				$key = new OATHAuthKey( $res->secret, explode( ',', $res->scratch_tokens ) );
				$oathUser->setKey( $key );
			}

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * @param OATHUser $user
	 */
	public function persist( OATHUser $user ) {
		$this->getDB( DB_MASTER )->replace(
			'oathauth_users',
			[ 'id' ],
			[
				'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ),
				'secret' => $user->getKey()->getSecret(),
				'scratch_tokens' => implode( ',', $user->getKey()->getScratchTokens() ),
			],
			__METHOD__
		);
		$this->cache->set( $user->getUser()->getName(), $user );
	}

	/**
	 * @param OATHUser $user
	 */
	public function remove( OATHUser $user ) {
		$this->getDB( DB_MASTER )->delete(
			'oathauth_users',
			[ 'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ) ],
			__METHOD__
		);
		$this->cache->delete( $user->getUser()->getName() );
	}

	/**
	 * @param integer $index DB_MASTER/DB_REPLICA
	 * @return DBConnRef
	 */
	private function getDB( $index ) {
		global $wgOATHAuthDatabase;

		return $this->lb->getConnectionRef( $index, [], $wgOATHAuthDatabase );
	}
}
