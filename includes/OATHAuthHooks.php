<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;

/**
 * Hooks for Extension:OATHAuth
 *
 * @ingroup Extensions
 */
class OATHAuthHooks {
	/**
	 * Get the singleton OATH user repository
	 *
	 * @deprecated Use "OATHUserRepository" service
	 * @return OATHUserRepository
	 */
	public static function getOATHUserRepository() {
		return MediaWikiServices::getInstance()
			->getService( 'OATHUserRepository' );
	}

	/**
	 * Determine if two-factor authentication is enabled for the current user
	 *
	 * This isn't the preferred mechanism for controlling access to sensitive features
	 * (see AuthManager::securitySensitiveOperationStatus() for that) but there is no harm in
	 * keeping it.
	 *
	 * @param bool &$isEnabled Will be set to true if enabled, false otherwise
	 * @return bool False if enabled, true otherwise
	 */
	public static function onTwoFactorIsEnabled( &$isEnabled ) {
		$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$authUser = $userRepo->findByUser( RequestContext::getMain()->getUser() );
		if ( $authUser && $authUser->getModule() !== null ) {
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
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( __DIR__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/mysql/tables.sql" );
				$updater->addExtensionUpdate( [ [ __CLASS__, 'schemaUpdateOldUsersFromInstaller' ] ] );
				$updater->dropExtensionField(
					'oathauth_users',
					'secret_reset',
					"$base/sql/mysql/patch-remove_reset.sql"
				);
				$updater->addExtensionField(
					'oathauth_users',
					'module',
					"$base/sql/mysql/patch-add_generic_fields.sql"
				);
				$updater->addExtensionUpdate( [ [ __CLASS__, 'schemaUpdateSubstituteForGenericFields' ] ] );
				$updater->dropExtensionField(
					'oathauth_users',
					'secret',
					"$base/sql/mysql/patch-remove_module_specific_fields.sql"
				);
				break;

			case 'oracle':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/oracle/tables.sql" );
				break;

			case 'postgres':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/postgres/tables.sql" );
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
		global $wgOATHAuthDatabase;
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $wgOATHAuthDatabase );
		$dbw = $lb->getConnectionRef( DB_MASTER, [], $wgOATHAuthDatabase );
		return self::schemaUpdateOldUsers( $dbw );
	}

	/**
	 * Helper function for converting old, TOTP specific, column values to new structure
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateSubstituteForGenericFields( DatabaseUpdater $updater ) {
		global $wgOATHAuthDatabase;
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $wgOATHAuthDatabase );
		$dbw = $lb->getConnectionRef( DB_MASTER, [], $wgOATHAuthDatabase );
		return self::convertToGenericFields( $dbw );
	}

	/**
	 * Converts old, TOTP specific, column values to new structure
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function convertToGenericFields( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret' ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$batchSize = $services->getMainConfig()->get( 'UpdateRowsPerQuery' );
		$lbFactory = $services->getDBLoadBalancerFactory();
		while ( true ) {
			$lbFactory->waitForReplication();

			$res = $db->select(
				'oathauth_users',
				[ 'id', 'secret', 'scratch_tokens' ],
				[
					'module' => '',
					'data IS NULL',
					'secret IS NOT NULL'
				],
				__METHOD__,
				[ 'LIMIT' => $batchSize ]
			);

			if ( $res->numRows() === 0 ) {
				return true;
			}

			foreach ( $res as $row ) {
				$db->update(
					'oathauth_users',
					[
						'module' => 'totp',
						'data' => FormatJson::encode( [
							'secret' => $row->secret,
							'scratch_tokens' => $row->scratch_tokens
						] )
					],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}

		return true;
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
			Wikimedia\suppressWarnings();
			$scratchTokens = unserialize( base64_decode( $row->scratch_tokens ) );
			Wikimedia\restoreWarnings();
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
