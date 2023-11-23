<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use ConfigException;
use DatabaseUpdater;
use FormatJson;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateForMultipleDevicesSupport;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IMaintainableDatabase;

class UpdateTables implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__, 2 );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionTable(
			'oathauth_types',
			"$typePath/tables-generated.sql"
		);

		// If the old table exists, ensure it's up-to-date so the migration
		// from the old schema to the new one can be done properly.
		if ( $updater->tableExists( 'oathauth_users' ) ) {
			switch ( $type ) {
				case 'mysql':
				case 'sqlite':
					// 1.34
					$updater->addExtensionField(
						'oathauth_users',
						'module',
						"$typePath/patch-add_generic_fields.sql"
					);

					$updater->addExtensionUpdate(
						[ [ __CLASS__, 'schemaUpdateSubstituteForGenericFields' ] ]
					);
					$updater->dropExtensionField(
						'oathauth_users',
						'secret',
						"$typePath/patch-remove_module_specific_fields.sql"
					);

					$updater->addExtensionUpdate(
						[ [ __CLASS__, 'schemaUpdateTOTPToMultipleKeys' ] ]
					);

					$updater->addExtensionUpdate(
						[ [ __CLASS__, 'schemaUpdateTOTPScratchTokensToArray' ] ]
					);

					break;

				case 'postgres':
					// 1.38
					$updater->modifyExtensionTable(
						'oathauth_users',
						"$typePath/patch-oathauth_users-drop-oathauth_users_id_seq.sql"
					);
					break;
			}

			$updater->addExtensionUpdate( [
				'runMaintenance',
				UpdateForMultipleDevicesSupport::class,
				"$baseDir/maintenance/UpdateForMultipleDevicesSupport.php"
			] );

			$updater->dropExtensionTable( 'oathauth_users' );
		}

		// add new updates here
	}

	/**
	 * @return IMaintainableDatabase
	 */
	private static function getDatabase() {
		$db = MediaWikiServices::getInstance()
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
		'@phan-var IMaintainableDatabase $db';
		return $db;
	}

	/**
	 * Helper function for converting old, TOTP specific, column values to new structure
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateSubstituteForGenericFields( DatabaseUpdater $updater ) {
		return self::convertToGenericFields();
	}

	/**
	 * Helper function for converting single TOTP keys to the multi-key system
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateTOTPToMultipleKeys( DatabaseUpdater $updater ) {
		return self::switchTOTPToMultipleKeys();
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
	 * Converts old, TOTP specific, column values to a newer structure
	 * @return bool
	 * @throws ConfigException
	 */
	public static function convertToGenericFields() {
		$db = self::getDatabase();

		if ( !$db->fieldExists( 'oathauth_users', 'secret', __METHOD__ ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$batchSize = $services->getMainConfig()->get( 'UpdateRowsPerQuery' );
		$lbFactory = $services->getDBLoadBalancerFactory();
		while ( true ) {
			$lbFactory->waitForReplication();

			$res = $db->newSelectQueryBuilder()
				->select( [ 'id', 'secret', 'scratch_tokens' ] )
				->from( 'oathauth_users' )
				->where( [
					'module' => '',
					'data IS NULL',
					'secret IS NOT NULL'
				] )
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $res->numRows() === 0 ) {
				return true;
			}

			foreach ( $res as $row ) {
				$db->update(
					'oathauth_users',
					[
						'module' => 'totp',
						'data' => FormatJson::encode( [
							'keys' => [ [
								'secret' => $row->secret,
								'scratch_tokens' => $row->scratch_tokens
							] ]
						] )
					],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}
	}

	/**
	 * Switch from using single keys to multi-key support
	 *
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPToMultipleKeys() {
		$db = self::getDatabase();

		if ( !$db->fieldExists( 'oathauth_users', 'data', __METHOD__ ) ) {
			return true;
		}

		$res = $db->newSelectQueryBuilder()
			->select( [ 'id', 'data' ] )
			->from( 'oathauth_users' )
			->where( [ 'module' => 'totp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data, true );
			if ( isset( $data['keys'] ) ) {
				continue;
			}
			$db->update(
				'oathauth_users',
				[
					'data' => FormatJson::encode( [
						'keys' => [ $data ]
					] )
				],
				[ 'id' => $row->id ],
				__METHOD__
			);
		}

		return true;
	}

	/**
	 * Switch scratch tokens from string to an array
	 *
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPScratchTokensToArray() {
		$db = self::getDatabase();

		if ( !$db->fieldExists( 'oathauth_users', 'data' ) ) {
			return true;
		}

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

			$db->update(
				'oathauth_users',
				[
					'data' => FormatJson::encode( $data )
				],
				[ 'id' => $row->id ],
				__METHOD__
			);
		}

		return true;
	}

}
