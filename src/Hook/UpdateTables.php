<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Hook;

use MediaWiki\Extension\OATHAuth\Maintenance\MoveRecoveryCodesFromTOTP;
use MediaWiki\Extension\OATHAuth\Maintenance\PopulateUserHandles;
use MediaWiki\Extension\OATHAuth\Maintenance\UpdateKeysToLatestVersion;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/** @codeCoverageIgnore */
class UpdateTables implements LoadExtensionSchemaUpdatesHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__, 2 );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionUpdateOnVirtualDomain(
			[ 'virtual-oathauth', 'addTable', 'oathauth_types', "$typePath/tables-generated.sql", true ]
		);

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
