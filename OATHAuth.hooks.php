<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

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
			$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$service = new OATHUserRepository( $factory->getMainLB( $wgOATHAuthDatabase ) );
		}

		return $service;
	}

	/**
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo Field information array (union of the
	 *    AuthenticationRequest::getFieldInfo() responses).
	 * @param array $formDescriptor HTMLForm descriptor. The special key 'weight' can be set
	 *   to change the order of the fields.
	 * @param string $action One of the AuthManager::ACTION_* constants.
	 * @return bool
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		if ( isset( $fieldInfo['OATHToken'] ) ) {
			$formDescriptor['OATHToken'] += [
				'cssClass' => 'loginText',
				'id' => 'wpOATHToken',
				'size' => 20,
				'autofocus' => true,
				'persistent' => false,
			];
		}
		return true;
	}

	/**
	 * Determine if two-factor authentication is enabled for $wgUser
	 *
	 * This isn't the preferred mechanism for controlling access to sensitive features
	 * (see AuthManager::securitySensitiveOperationStatus() for that) but there is no harm in
	 * keeping it.
	 *
	 * @param bool &$isEnabled Will be set to true if enabled, false otherwise
	 * @return bool False if enabled, true otherwise
	 */
	public static function onTwoFactorIsEnabled( &$isEnabled ) {
		global $wgUser;

		$user = self::getOATHUserRepository()->findByUser( $wgUser );
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
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		if ( !$user->isAllowed( 'oathauth-enable' ) ) {
			return true;
		}

		$oathUser = self::getOATHUserRepository()->findByUser( $user );

		$title = SpecialPage::getTitleFor( 'OATH' );
		$msg = $oathUser->getKey() !== null ? 'oathauth-disable' : 'oathauth-enable';

		$preferences[$msg] = [
			'type' => 'info',
			'raw' => 'true',
			'default' => Linker::link(
				$title,
				wfMessage( $msg )->escaped(),
				[],
				[ 'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText() ]
			),
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/info', ];

		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__;
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/mysql/tables.sql" );
				$updater->addExtensionUpdate( [ [ __CLASS__, 'schemaUpdateOldUsersFromInstaller' ] ] );
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
		return self::schemaUpdateOldUsers( $updater->getDB() );
	}

	/**
	 * Helper function for converting old users to the new schema
	 * @see OATHAuthHooks::OATHAuthSchemaUpdates
	 *
	 * @param IDatabase $db
	 * @return bool
	 */
	public static function schemaUpdateOldUsers( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret_reset' ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'scratch_tokens' ],
			[ 'is_validated != 0' ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			MediaWiki\suppressWarnings();
			$scratchTokens = unserialize( base64_decode( $row->scratch_tokens ) );
			MediaWiki\restoreWarnings();
			if ( $scratchTokens ) {
				$db->update(
					'oathauth_users',
					[ 'scratch_tokens' => implode( ',', $scratchTokens ) ],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}

		// Remove rows from the table where user never completed the setup process
		$db->delete( 'oathauth_users', [ 'is_validated' => 0 ], __METHOD__ );

		return true;
	}
}
