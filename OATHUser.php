<?php

/**
 * Class representing a user from OATH's perspective
 *
 * @file
 * @ingroup Extensions
 */
class OATHUser {
	/** @var int User ID */
	private $id;

	/** @var string Two factor binary secret */
	private $secret;

	/** @var string New two factor secret when resetting */
	private $secretReset;

	/** @var string[] List of scratch tokens */
	private $scratchTokens;

	/** @var string[] New scratch tokens when resetting */
	private $scratchTokensReset;

	/** @var string Name for the two-factor account */
	private $account;

	/** @var bool Whether two-factor is enabled */
	private $isEnabled;

	/** @var bool Whether two-factor is validated */
	private $isValidated;

	/**
	 * Constructor. Can't be called directly. Call one of the static NewFrom* methods
	 * @param $id Int Database id for the group
	 * @param $account
	 * @param $secret
	 * @param $secretReset
	 * @param $scratchTokens
	 * @param $scratchTokensReset
	 * @param bool $isValidated bool
	 * @todo Get rid of telescoping constructor anti-pattern
	 */
	public function __construct( $id, $account, $secret = null, $secretReset = null,
		$scratchTokens = null, $scratchTokensReset = null, $isValidated = false
	) {
		$this->id = $id;
		$this->account = $account;
		$this->isEnabled = true;
		if ( $secret ) {
			$this->secret = $secret;
		} else {
			$this->secret = Base32::encode( MWCryptRand::generate( 10, true ) );
			$this->isEnabled = false;
		}
		if ( $secretReset ) {
			$this->secretReset = $secretReset;
		} else {
			$this->secretReset = Base32::encode( MWCryptRand::generate( 10, true ) );
		}
		if ( $scratchTokens ) {
			$this->scratchTokens = $scratchTokens;
		} else {
			$this->regenerateScratchTokens( false );
			$this->isEnabled = false;
		}
		if ( $scratchTokensReset ) {
			$this->scratchTokensReset = $scratchTokensReset;
		} else {
			$this->regenerateScratchTokens( true );
		}
		$this->isValidated = $isValidated;
	}

	/**
	 * @param $reset bool
	 */
	public function regenerateScratchTokens( $reset ) {
		$scratchTokens = array();
		for ( $i = 0; $i < 5; $i++ ) {
			array_push( $scratchTokens, Base32::encode( MWCryptRand::generate( 10, true ) ) );
		}
		if ( $reset ) {
			$this->scratchTokensReset = $scratchTokens;
		} else {
			$this->scratchTokens = $scratchTokens;
		}
	}

	/**
	 * @return String
	 */
	public function getAccount() {
		return $this->account;
	}

	/**
	 * @return String
	 */
	public function getSecret() {
		return $this->secret;
	}

	/**
	 * @return String
	 */
	public function getSecretReset() {
		return $this->secretReset;
	}

	/**
	 * @return Array
	 */
	public function getScratchTokens() {
		return $this->scratchTokens;
	}

	/**
	 * @return Array
	 */
	public function getScratchTokensReset() {
		return $this->scratchTokensReset;
	}

	/**
	 * @return Boolean
	 */
	public function isEnabled() {
		return $this->isEnabled;
	}

	/**
	 * @return Boolean
	 */
	public function isValidated() {
		return $this->isValidated;
	}

	/**
	 * @param $token
	 * @param $reset bool
	 * @return Boolean
	 */
	public function verifyToken( $token, $reset = false ) {
		global $wgOATHAuthWindowRadius;

		$memc = ObjectCache::newAnything( array() );

		// Prevent replay attacks
		$memcKey = wfMemcKey( 'oauthauth', 'usedtokens', $reset ? 'reset' : null, $this->getAccount() );
		$lastWindow = (int)$memc->get( $memcKey );

		$retval = false;
		$secret = $reset ? $this->secretReset : $this->secret;
		$results = HOTP::generateByTimeWindow(
			Base32::decode( $secret ),
			30, -$wgOATHAuthWindowRadius, $wgOATHAuthWindowRadius );
		// Check to see if the user's given token is in the list of tokens generated
		// for the time window.
		foreach ( $results as $window => $result ) {
			if ( $window > $lastWindow && $result->toHOTP( 6 ) === $token ) {
				$lastWindow = $window;
				$retval = true;
				break;
			}
		}

		// See if the user is using a scratch token
		$length = count( $this->scratchTokens );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( $token === $this->scratchTokens[$i] ) {
				// If there is a scratch token, remove it from the scratch token list
				unset( $this->scratchTokens[$i] );
				// Only return true if we removed it from the database
				$retval = $this->updateScratchTokens();
				break;
			}
		}

		if ( $retval ) {
			$memc->set( $memcKey, $lastWindow, 30 * (1 + 2 * $wgOATHAuthWindowRadius) );
		}

		return $retval;
	}

	/**
	 * @param $user User
	 * @return OATHUser|null
	 */
	public static function newFromUser( $user ) {
		global $wgSitename;

		$id = $user->getId();
		if ( $id === 0 ) {
			return null;
		}
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'oathauth_users',
			array(  'id',
				'secret',
				'secret_reset',
				'scratch_tokens',
				'scratch_tokens_reset',
				'is_validated' ),
			array(  'id' => $id ),
			__METHOD__ );

		if ( $row ) {
			return new OATHUser(
				$id,
				urlencode( $user->getName() ) . '@' . $wgSitename,
				$row->secret,
				$row->secret_reset,
				unserialize( base64_decode( $row->scratch_tokens ) ),
				unserialize( base64_decode( $row->scratch_tokens_reset ) ),
				$row->is_validated
			);
		} else {
			return new OATHUser(
				$id,
				urlencode( $user->getName() ) . '@' . $wgSitename
			);
		}
	}

	/**
	 * @param $username string
	 * @return OATHUser|null
	 */
	public static function newFromUsername( $username ) {
		$user = User::newFromName( $username, true );
		return OATHUser::newFromUser( $user );
	}

	/**
	 * @return bool
	 */
	public function enable() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->insert(
			'oathauth_users',
			array(  'id' => $this->id,
				'secret' => $this->secret,
				'scratch_tokens' => base64_encode( serialize( $this->scratchTokens ) ),
				'is_validated' => false,
			),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function setReset() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'oathauth_users',
			array(
				'secret_reset' => $this->secretReset,
				'scratch_tokens_reset' => base64_encode( serialize( $this->scratchTokensReset ) )
			),
			array( 'id' => $this->id ),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function reset() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'oathauth_users',
			array(
				'secret' => $this->secretReset,
				'secret_reset' => null,
				'scratch_tokens' => base64_encode( serialize( $this->scratchTokensReset ) ),
				'scratch_tokens_reset' => null,
			),
			array( 'id' => $this->id ),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function validate() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'oathauth_users',
			array( 'is_validated' => true ),
			array( 'id' => $this->id ),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function updateScratchTokens() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'oathauth_users',
			array( 'scratch_tokens' => base64_encode( serialize( $this->scratchTokens ) ) ),
			array( 'id' => $this->id ),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function disable() {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->delete(
			'oathauth_users',
			array( 'id' => $this->id ),
			__METHOD__
		);
	}
}
