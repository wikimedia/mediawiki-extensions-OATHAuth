<?php

/**
 * Class representing a two-factor key
 *
 * Keys can be tied to OAUTHUsers
 *
 * @ingroup Extensions
 */
class OATHAuthKey {
	/**
	 * Represents that a token corresponds to the main secret
	 * @see verifyToken
	 */
	const MAIN_TOKEN = 1;

	/**
	 * Represents that a token corresponds to a scratch token
	 * @see verifyToken
	 */
	const SCRATCH_TOKEN = -1;

	/** @var string Two factor binary secret */
	private $secret;

	/** @var string[] List of scratch tokens */
	private $scratchTokens;

	/**
	 * Make a new key from random values
	 *
	 * @return OATHAuthKey
	 */
	public static function newFromRandom() {
		$object = new self(
			Base32::encode( MWCryptRand::generate( 10, true ) ),
			array()
		);

		$object->regenerateScratchTokens();

		return $object;
	}

	/**
	 * @param string $secret
	 * @param array $scratchTokens
	 */
	public function __construct( $secret, array $scratchTokens ) {
		// Currently harcoded values; might be used in future
		$this->secret = array(
			'mode' => 'hotp',
			'secret' => $secret,
			'period' => 30,
			'algorithm' => 'SHA1',
		);
		$this->scratchTokens = $scratchTokens;
	}

	/**
	 * @return String
	 */
	public function getSecret() {
		return $this->secret['secret'];
	}

	/**
	 * @return Array
	 */
	public function getScratchTokens() {
		return $this->scratchTokens;
	}

	/**
	 * Verify a token against the secret or scratch tokens
	 *
	 * @param string $token Token to verify
	 * @param OATHUser $user
	 *
	 * @return int|false Returns a constant represent what type of token was matched,
	 *  or false for no match
	 */
	public function verifyToken( $token, $user ) {
		global $wgOATHAuthWindowRadius;

		if ($this->secret['mode'] !== 'hotp') {
			throw new \DomainException( 'OATHAuth extension does not support non-HOTP tokens' );
		}

		// Prevent replay attacks
		$memc = ObjectCache::newAnything( array() );
		$memcKey = wfMemcKey( 'oauthauth', 'usedtokens', $user->getUser()->getId() );
		$lastWindow = (int)$memc->get( $memcKey );

		$retval = false;
		$results = HOTP::generateByTimeWindow(
			Base32::decode( $this->secret['secret'] ),
			$this->secret['period'], -$wgOATHAuthWindowRadius, $wgOATHAuthWindowRadius );
		// Check to see if the user's given token is in the list of tokens generated
		// for the time window.
		foreach ( $results as $window => $result ) {
			if ( $window > $lastWindow && $result->toHOTP( 6 ) === $token ) {
				$lastWindow = $window;
				$retval = self::MAIN_TOKEN;
				break;
			}
		}

		// See if the user is using a scratch token
		if ( !$retval ) {
			$length = count( $this->scratchTokens );
			// Detect condition where all scratch tokens have been used
			if ( $length == 1 && "" === $this->scratchTokens[0] ) {
				$retval = false;
			} else {
				for ( $i = 0; $i < $length; $i++ ) {
					if ( $token === $this->scratchTokens[$i] ) {
						// If there is a scratch token, remove it from the scratch token list
						unset( $this->scratchTokens[$i] );
						$oathrepo = new OATHUserRepository( wfGetLB() );
						$user->setKey( $this );
						$oathrepo->persist( $user );
						// Only return true if we removed it from the database
						$retval = self::SCRATCH_TOKEN;
						break;
					}
				}
			}
		}

		if ( $retval ) {
			$memc->set( $memcKey, $lastWindow,
				$this->secret['period'] * (1 + 2 * $wgOATHAuthWindowRadius) );
		}

		return $retval;
	}

	public function regenerateScratchTokens() {
		$scratchTokens = array();
		for ( $i = 0; $i < 5; $i++ ) {
			array_push( $scratchTokens, Base32::encode( MWCryptRand::generate( 10, true ) ) );
		}
		$this->scratchTokens = $scratchTokens;
	}
}
