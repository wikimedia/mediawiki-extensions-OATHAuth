<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Hook;

use MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP;
use MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateForMultipleDevicesSupport;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateKeysToLatestVersion;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class UpdateTables implements LoadExtensionSchemaUpdatesHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__, 2 );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionUpdateOnVirtualDomain(
			[ 'virtual-oathauth', 'addTable', 'oathauth_types', "$typePath/tables-generated.sql", true ]
		);

		// Ensure that the oathauth_users table is up-to-date if it exists, so that the migration
		// from the old schema to the new one can be done properly.
		if ( $updater->tableExists( 'oathauth_users' ) ) {
			// 1.41
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-oathauth',
				'runMaintenance',
				UpdateForMultipleDevicesSupport::class,
			] );
			$updater->addExtensionUpdateOnVirtualDomain( [ 'virtual-oathauth', 'dropTable', 'oathauth_users' ] );
		}

		// 1.45
		$updater->addPostDatabaseUpdateMaintenance( MoveRecoveryCodesFromTOTP::class );

		// 1.46
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-oathauth', 'addTable', 'oathauth_user_handles',
			"$typePath/patch-add-oathauth_user_handles.sql", true
		] );
		$updater->addPostDatabaseUpdateMaintenance( PopulateUserHandles::class );

		// add new updates here

		// 1.47
		// This was added in 1.47, but it should be run every time update.php is run, basically...
		$updater->addPostDatabaseUpdateMaintenance( UpdateKeysToLatestVersion::class );
	}
}
