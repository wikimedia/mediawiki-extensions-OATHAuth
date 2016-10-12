<?php

/**
 * Hooks for Extension:OATHAuth
 * @deprecated B/C class for compatibility with pre-AuthManager core
 */
class OATHAuthLegacyHooks {
	/**
	 * @param $extraFields array
	 * @return bool
	 */
	static function ChangePasswordForm( &$extraFields ) {
		$tokenField = [ 'wpOATHToken', 'oathauth-token', 'password', '' ];
		array_push( $extraFields, $tokenField );

		return true;
	}

	/**
	 * @param $user User
	 * @param $password string
	 * @param $newpassword string
	 * @param &$errorMsg string
	 * @return bool
	 */
	static function AbortChangePassword( $user, $password, $newpassword, &$errorMsg ) {
		global $wgRequest;

		$token = $wgRequest->getText( 'wpOATHToken' );
		$oathrepo = OATHAuthHooks::getOATHUserRepository();
		$oathuser = $oathrepo->findByUser( $user );
		# Though it's weird to default to true, we only want to deny
		# users who have two-factor enabled and have validated their
		# token.
		$result = true;

		if ( $oathuser->getKey() !== null ) {
			// Don't increase pingLimiter, just check for limit exceeded.
			if ( $user->pingLimiter( 'badoath', 0 ) ) {
				$result = 'oathauth-abortlogin-throttled';
			} elseif ( !$oathuser->getKey()->verifyToken( $token, $oathuser ) ) {
				$result = 'oathauth-abortlogin';
			}
		}

		if ( $result === true ) {
			return true;
		} else {
			$errorMsg = $result;
			return false;
		}
	}

	/**
	 * @param $user User
	 * @param $password string
	 * @param &$abort int
	 * @param &$errorMsg string
	 * @return bool
	 */
	static function AbortLogin( $user, $password, &$abort, &$errorMsg ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$output = $context->getOutput();

		$oathrepo = OATHAuthHooks::getOATHUserRepository();
		$oathuser = $oathrepo->findByUser( $user );
		$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );

		if ( $oathuser->getKey() !== null && !$request->getCheck( 'token' ) ) {
			$encData = OATHAuthUtils::encryptSessionData(
				$request->getValues(),
				$uid
			);
			$request->setSessionData( 'oath_login', $encData );
			$request->setSessionData( 'oath_uid', $uid );
			$output->redirect( SpecialPage::getTitleFor( 'OATH' )->getFullURL( '', false, PROTO_CURRENT ) );
			return false;
		} else {
			return true;
		}
	}
}
