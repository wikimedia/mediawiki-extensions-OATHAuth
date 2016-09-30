<?php

class OATHUserRepository {
	/** @var LoadBalancer */
	protected $lb;

	/**
	 * OATHUserRepository constructor.
	 * @param LoadBalancer $lb
	 */
	public function __construct( LoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * @param User $user
	 * @return OATHUser
	 */
	public function findByUser( User $user ) {
		$oathUser = new OATHUser( $user, null );

		$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
		$res = $this->getDB( DB_SLAVE )
			->selectRow( 'oathauth_users', '*', [ 'id' => $uid ], __METHOD__ );
		if ( $res ) {
			$key = new OATHAuthKey( $res->secret, explode( ',', $res->scratch_tokens ) );
			$oathUser->setKey( $key );
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
	}

	/**
	 * @param integer $index DB_MASTER/DB_SLAVE
	 * @return DBConnRef
	 */
	private function getDB( $index ) {
		global $wgOATHAuthDatabase;

		return $this->lb->getConnectionRef( $index, [], $wgOATHAuthDatabase );
	}
}
