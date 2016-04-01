<?php

/**
 * Hooks for Extension:OATHAuth
 *
 * @ingroup Extensions
 */
class OATHAuthHooks {
	/**
	 * Get the singleton OATH user repository
	 *
	 * @return OATHUserRepository
	 */
	public static function getOATHUserRepository() {
		global $wgOATHAuthDatabase;

		static $service = null;

		if ( $service == null ) {
			$service = new OATHUserRepository( wfGetLB( $wgOATHAuthDatabase ) );
		}

		return $service;
	}

	/**
	 * @param $extraFields array
	 * @return bool
	 */
	static function ChangePasswordForm( &$extraFields ) {
		$tokenField = array( 'wpOATHToken', 'oathauth-token', 'password', '' );
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
		$oathrepo = self::getOATHUserRepository();
		$oathuser = $oathrepo->findByUser( $user );
		# Though it's weird to default to true, we only want to deny
		# users who have two-factor enabled and have validated their
		# token.
		$result = true;

		if ( $oathuser->getKey() !== null ) {
			$result = $oathuser->getKey()->verifyToken( $token, $oathuser );
		}

		if ( $result ) {
			return true;
		} else {
			$errorMsg = 'oathauth-abortlogin';

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

		$oathrepo = self::getOATHUserRepository();
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

	/**
	 * Add the necessary user preferences for OATHAuth
	 *
	 * @param User $user
	 * @param array $preferences
	 *
	 * @return bool
	 */
	public static function manageOATH( User $user, array &$preferences ) {
		if ( !$user->isAllowed( 'oathauth-enable' ) ) {
			return true;
		}

		$oathUser = self::getOATHUserRepository()->findByUser( $user );

		$title = SpecialPage::getTitleFor( 'OATH' );
		$msg = $oathUser->getKey() !== null ? 'oathauth-disable' : 'oathauth-enable';

		$preferences[$msg] = array(
			'type' => 'info',
			'raw' => 'true',
			'default' => Linker::link(
				$title,
				wfMessage( $msg )->escaped(),
				array(),
				array( 'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText() )
			),
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/info',
		);

		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function OATHAuthSchemaUpdates( $updater ) {
		$base = dirname( __FILE__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/mysql/tables.sql" );
				$updater->addExtensionUpdate( array( array( __CLASS__, 'schemaUpdateOldUsersFromInstaller' ) ) );
				$updater->dropExtensionField( 'oathauth_users', 'secret_reset',
					"$base/sql/mysql/patch-remove_reset.sql" );
				break;
		}

		return true;
	}

	/**
	 * Helper function for converting old users to the new schema
	 * @see OATHAuthHooks::OATHAuthSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function schemaUpdateOldUsersFromInstaller( DatabaseUpdater $updater ) {
		return self::schemaUpdateOldUsers($updater->getDB());
	}

	/**
	 * Helper function for converting old users to the new schema
	 * @see OATHAuthHooks::OATHAuthSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function schemaUpdateOldUsers( DatabaseBase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret_reset' ) ) {
			return true;
		}

		$res = $db->select( 'oathauth_users', array( 'id', 'scratch_tokens' ), '', __METHOD__ );

		foreach ( $res as $row ) {
			$scratchTokens = unserialize( base64_decode( $row->scratch_tokens ) );
			if ( $scratchTokens ) {
				$db->update(
					'oathauth_users',
					array( 'scratch_tokens' => implode( ',', $scratchTokens ) ),
					array( 'id' => $row->id ),
					__METHOD__
				);
			}
		}

		return true;
	}
}
