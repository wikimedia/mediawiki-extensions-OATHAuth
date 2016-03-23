<?php

/**
 * Hooks for Extension:OATHAuth
 */
class OATHAuthHooks {
	/**
	 * @param $template UserloginTemplate
	 * @return bool
	 */
	static function ModifyUITemplate( &$template ) {
		$input = '<div><label for="wpOATHToken">'
			. wfMessage( 'oathauth-token' )->escaped()
			. '</label>'
			. Html::input( 'wpOATHToken', null, 'text', array(
					'class' => 'loginText', 'id' => 'wpOATHToken', 'tabindex' => '3', 'size' => '20'
				) ) . '</div>';

		$template->set( 'extrafields', $template->get( 'extrafields', '' ) . $input );

		return true;
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
		$result = self::authenticate( $user );
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
		$result = self::authenticate( $user );
		if ( $result ) {
			return true;
		} else {
			$abort = LoginForm::ABORTED;
			$errorMsg = 'oathauth-abortlogin';
			return false;
		}
	}

	/**
	 * @param $user User
	 * @return bool
	 */
	static function authenticate( $user ) {
		global $wgRequest;

		$token = $wgRequest->getText( 'wpOATHToken' );
		$oathrepo = new OATHUserRepository( wfGetLB() );
		$oathuser = $oathrepo->findByUser( $user );
		# Though it's weird to default to true, we only want to deny
		# users who have two-factor enabled and have validated their
		# token.
		$result = true;

		if ( $oathuser->getKey() !== null ) {
			$result = $oathuser->getKey()->verifyToken( $token, $oathuser );
		}

		return $result;
	}

	/**
	 * Determine if two-factor authentication is enabled for $wgUser
	 *
	 * @param bool &$isEnabled Will be set to true if enabled, false otherwise
	 *
	 * @return bool False if enabled, true otherwise
	 */
	static function TwoFactorIsEnabled( &$isEnabled ) {
		global $wgUser;

		$oathrepo = new OATHUserRepository( wfGetLB() );
		$user = $oathrepo->findByUser( $wgUser );
		if ( $user && $user->getKey() !== null ) {
			$isEnabled = true;
			# This two-factor extension is enabled by the user,
			# we don't need to check others.
			return false;
		} else {
			$isEnabled = false;
			# This two-factor extension isn't enabled by the user,
			# but others may be.
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
		$oathrepo = new OATHUserRepository( wfGetLB() );
		$oathUser = $oathrepo->findByUser( $user );

		$title = SpecialPage::getTitleFor( 'OATH' );
		if ( $oathUser->getKey() !== null ) {
			$preferences['oath-disable'] = array(
				'type' => 'info',
				'raw' => 'true',
				'default' => Linker::link(
					$title,
					wfMessage( 'oathauth-disable' )->escaped(),
					array(),
					array(
						'action' => 'disable',
						'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
					)
				),
				'label-message' => 'oathauth-prefs-label',
				'section' => 'personal/info',
			);
		} else {
			$preferences['oath-enable'] = array(
				'type' => 'info',
				'raw' => 'true',
				'default' => Linker::link(
					$title,
					wfMessage( 'oathauth-enable' )->escaped(),
					array(),
					array(
						'action' => 'enable',
						'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
					)
				),
				'label-message' => 'oathauth-prefs-label',
				'section' => 'personal/info',
			);
		}

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
