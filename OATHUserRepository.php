<?php

class OATHUserRepository {
	private $dbr;

	private $dbw;

	public function __construct( LoadBalancer $lb ) {
		global $wgOATHAuthDatabase;
		$this->dbr = $lb->getConnection( DB_SLAVE, array(), $wgOATHAuthDatabase );
		$this->dbw = $lb->getConnection( DB_MASTER, array(), $wgOATHAuthDatabase );
	}

	public function findByUser( User $user ) {
		$oathUser = new OATHUser( $user, null );

		$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
		$res = $this->dbr->selectRow( 'oathauth_users', '*', array( 'id' => $uid ), __METHOD__ );
		if ($res) {
			$key = new OATHAuthKey( $res->secret, explode( ',', $res->scratch_tokens ) );
			$oathUser->setKey( $key );
		}

		return $oathUser;
	}

	public function persist( OATHUser $user ) {
		$this->dbw->replace(
			'oathauth_users',
			array( 'id' ),
			array(
				'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ),
				'secret' => $user->getKey()->getSecret(),
				'scratch_tokens' => implode( ',', $user->getKey()->getScratchTokens() ),
			),
			__METHOD__
		);
	}

	public function remove( OATHUser $user ) {
		$this->dbw->delete(
			'oathauth_users',
			array( 'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ) ),
			__METHOD__
		);
	}
}
