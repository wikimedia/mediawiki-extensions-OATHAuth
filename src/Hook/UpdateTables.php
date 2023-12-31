<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use DatabaseUpdater;
use FormatJson;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateForMultipleDevicesSupport;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class UpdateTables implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__, 2 );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionUpdateOnVirtualDomain(
			[ 'virtual-oathauth', 'addTable', 'oathauth_types', "$typePath/tables-generated.sql", true ]
		);

		// If the old table exists, ensure it's up-to-date so the migration
		// from the old schema to the new one can be done properly.
		if ( $updater->tableExists( 'oathauth_users' ) ) {
			switch ( $type ) {
				case 'mysql':
				case 'sqlite':
					// 1.36
					$updater->addExtensionUpdate(
						[ [ __CLASS__, 'schemaUpdateTOTPScratchTokensToArray' ] ]
					);

					break;

				case 'postgres':
					// 1.38
					$updater->addExtensionUpdateOnVirtualDomain( [
						'virtual-oathauth',
						'modifyTable',
						'oathauth_users',
						"$typePath/patch-oathauth_users-drop-oathauth_users_id_seq.sql",
						true
					] );
					break;
			}

			$updater->addExtensionUpdate( [
				'runMaintenance',
				UpdateForMultipleDevicesSupport::class,
				"$baseDir/maintenance/UpdateForMultipleDevicesSupport.php"
			] );
			$updater->addExtensionUpdateOnVirtualDomain( [ 'virtual-oathauth', 'dropTable', 'oathauth_users' ] );
		}

		// add new updates here
	}

	private static function getDatabase(): IDatabase {
		return MediaWikiServices::getInstance()
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
	}

	/**
	 * Helper function for converting single TOTP keys to the multi-key system
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateTOTPScratchTokensToArray( DatabaseUpdater $updater ) {
		return self::switchTOTPScratchTokensToArray();
	}

	/**
	 * Switch scratch tokens from string to an array
	 *
	 * @since 1.36
	 *
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPScratchTokensToArray() {
		$db = self::getDatabase();
		$res = $db->newSelectQueryBuilder()
			->select( [ 'id', 'data' ] )
			->from( 'oathauth_users' )
			->where( [ 'module' => 'totp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data, true );

			$updated = false;
			foreach ( $data['keys'] as &$k ) {
				if ( is_string( $k['scratch_tokens'] ) ) {
					$k['scratch_tokens'] = explode( ',', $k['scratch_tokens'] );
					$updated = true;
				}
			}
			unset( $k );

			if ( !$updated ) {
				continue;
			}

			$db->newUpdateQueryBuilder()
				->update( 'oathauth_users' )
				->set( [ 'data' => FormatJson::encode( $data ) ] )
				->where( [ 'id' => $row->id ] )
				->caller( __METHOD__ )
				->execute();
		}

		return true;
	}

}
