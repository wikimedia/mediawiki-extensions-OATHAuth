<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Utils\BatchRowIterator;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class UpdateKeysToLatestVersion extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'OATHAuth' );
		$this->addDescription( '' );
		$this->setBatchSize( 500 );
	}

	public function execute() {
		$services = $this->getServiceContainer();

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );

		$sqb = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_type', 'oad_data' ] )
			->from( 'oathauth_devices' )
			->caller( __METHOD__ );

		$batches = new BatchRowIterator( $dbw, $sqb, 'oad_id', $this->getBatchSize() );

		$moduleRegistry = OATHAuthServices::getInstance( $services )->getModuleRegistry();
		$mapping = array_flip( $moduleRegistry->getModuleIds() );

		$startTime = time();
		$updatedRows = 0;
		$totalRows = 0;
		foreach ( $batches as $rows ) {
			$this->beginTransactionRound( __METHOD__ );

			foreach ( $rows as $row ) {
				$totalRows++;
				$keyData = FormatJson::decode( $row->oad_data, true );

				$version = match ( $mapping[$row->oad_type] ) {
					RecoveryCodes::MODULE_NAME => RecoveryCodeKeys::VERSION,
					TOTP::MODULE_NAME => TOTPKey::VERSION,
					WebAuthn::MODULE_NAME => WebAuthnKey::VERSION,
				};

				if ( isset( $keyData['version'] ) && (int)$keyData['version'] >= $version ) {
					continue;
				}

				// Re-serialize key to allow the key to update itself to the latest version
				$key = match ( $mapping[$row->oad_type] ) {
					RecoveryCodes::MODULE_NAME => RecoveryCodeKeys::newFromArray( $keyData ),
					TOTP::MODULE_NAME => TOTPKey::newFromArray( $keyData ),
					WebAuthn::MODULE_NAME => WebAuthnKey::newFromData( $keyData ),
				};

				$dbw->newUpdateQueryBuilder()
					->update( 'oathauth_devices' )
					->set( [ 'oad_data' => FormatJson::encode( $key ) ] )
					->where( [ 'oad_id' => $row->oad_id ] )
					->caller( __METHOD__ )
					->execute();

				$updatedRows += $dbw->affectedRows();
			}

			$this->commitTransactionRound( __METHOD__ );
		}

		$totalTimeInSeconds = time() - $startTime;
		$this->output( "Done. Processed {$totalRows} rows and updated {$updatedRows} keys " .
			"in {$totalTimeInSeconds} seconds.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdateKeysToLatestVersion::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
