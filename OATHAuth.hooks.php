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
		$template->set( 'extrafields', $input );

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
		$oathuser = OATHUser::newFromUser( $user );
		# Though it's weird to default to true, we only want to deny
		# users who have two-factor enabled and have validated their
		# token.
		$result = true;
		if ( $oathuser && $oathuser->isEnabled() && $oathuser->isValidated() ) {
			$result = $oathuser->verifyToken( $token );
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

		$user = OATHUser::newFromUser( $wgUser );
		if ( $user && $user->isEnabled() && $user->isValidated() ) {
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
		$oathUser = OATHUser::newFromUser( $user );

		$title = SpecialPage::getTitleFor( 'OATH' );
		if ( $oathUser->isEnabled() && $oathUser->isValidated() ) {
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
			$preferences['oath-reset'] = array(
				'type' => 'info',
				'raw' => 'true',
				'default' => Linker::link(
					$title,
					wfMessage( 'oathauth-reset' )->escaped(),
					array(),
					array(
						'action' => 'reset',
						'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
					)
				),
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
				$updater->addExtensionTable( 'oathauth_users', "$base/oathauth.sql" );
				break;
		}
		return true;
	}
}
