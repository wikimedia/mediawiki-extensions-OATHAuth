<?php

class OATHUserRepository {
	private $dbr;

	private $dbw;

	public function __construct( LoadBalancer $lb ) {
		$this->dbr = $lb->getConnection( DB_SLAVE );
		$this->dbw = $lb->getConnection( DB_MASTER );
	}

	public function findByUser( User $user ) {
		$oathUser = new OATHUser( $user, null );

		$res = $this->dbr->selectRow( 'oathauth_users', '*', array( 'id' => $user->getId() ), __METHOD__ );
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
				'id' => $user->getUser()->getId(),
				'secret' => $user->getKey()->getSecret(),
				'scratch_tokens' => implode( ',', $user->getKey()->getScratchTokens() ),
			),
			__METHOD__
		);
	}

	public function remove( OATHUser $user ) {
		$this->dbw->delete(
			'oathauth_users',
			array( 'id' => $user->getUser()->getId() ),
			__METHOD__
		);
	}
}
