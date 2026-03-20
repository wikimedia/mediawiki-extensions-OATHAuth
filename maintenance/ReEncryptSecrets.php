<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use Exception;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ReEncryptSecrets extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Re-encrypts TOTP secrets and recovery codes' );
		$this->addArg( 'old', 'The old secret value' );
		$this->addArg( 'new', 'The new secret value' );
		$this->requireExtension( 'OATHAuth' );
	}

	public function execute() {
		if ( !extension_loaded( 'sodium' ) ) {
			// @codeCoverageIgnoreStart
			$this->fatalError( "libsodium is not installed with php in this environment!" );
			// @codeCoverageIgnoreEnd
		}

		$services = $this->getServiceContainer();
		$encryptionHelper = OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getEncryptionHelper();

		if ( !$encryptionHelper->isEnabled() ) {
			// @codeCoverageIgnoreStart
			// phpcs:disable Generic.Files.LineLength.TooLong
			$this->fatalError( "\$wgOATHSecretKey is not set correctly! It should be set to an immutable, 64-character hexadecimal value!" );
			// @codeCoverageIgnoreEnd
		}

		$old = $this->getArg( 0 );
		$new = $this->getArg( 1 );

		$oldKey = $this->getConfig()->get( 'OATHSecretKey' );
		if ( $oldKey !== $old ) {
			$this->fatalError(
				"The old key is not the same as \$wgOATHSecretKey. Unable to decrypt existing secrets."
			);
		} elseif ( $old === $new ) {
			$this->fatalError(
				"The old key is the same as the new key. No reason to re-encrypt existing secrets."
			);
		} else {
			$this->output( "The old key is the same as \$wgOATHSecretKey.\n" );
		}

		$encryptionHelper->setEncryptionKey( $old );
		try {
			$encryptionHelper->isEnabled();
		} catch ( Exception $ex ) {
			$this->fatalError( "The 'old' parameter is not set correctly! " . $ex->getMessage() );
		}

		$encryptionHelper->setEncryptionKey( $new );
		try {
			$encryptionHelper->isEnabled();
		} catch ( Exception $ex ) {
			$this->fatalError( "The 'new' parameter is not set correctly! " . $ex->getMessage() );
		}

		$startTime = time();
		$updatedCount = 0;
		$totalRows = 0;

		$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
		$totpModuleId = $moduleRegistry->getModuleId( TOTP::MODULE_NAME );
		$recoveryModuleId = $moduleRegistry->getModuleId( RecoveryCodes::MODULE_NAME );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_data', 'oad_type' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => [ $totpModuleId, $recoveryModuleId ] ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$totalRows++;
			$data = FormatJson::decode( $row->oad_data, true );

			$key = null;

			$encryptionHelper->setEncryptionKey( $old );

			if ( (int)$row->oad_type === $totpModuleId ) {
				$key = TOTPKey::newFromArray( $data );
			} elseif ( (int)$row->oad_type === $recoveryModuleId ) {
				$key = RecoveryCodeKeys::newFromArray( $data );
			} else {
				// @codeCoverageIgnoreStart
				// Impossible
				$this->output( "Unable to update row with oad_id {$row->oad_id} and oad_type {$row->oad_type}.\n" );
				continue;
				// @codeCoverageIgnoreEnd
			}

			$key->forceReEncrypt = true;

			$encryptionHelper->setEncryptionKey( $new );

			$dbw->newUpdateQueryBuilder()
				->update( 'oathauth_devices' )
				->set( [ 'oad_data' => FormatJson::encode( $key->jsonSerialize() ) ] )
				->where( [ 'oad_id' => $row->oad_id ] )
				->caller( __METHOD__ )
				->execute();

			$updatedCount++;
			if ( $updatedCount % 50 === 0 ) {
				$this->output( "{$updatedCount}\n" );
			}
		}

		$totalTimeInSeconds = time() - $startTime;
		$this->output( "Done. Updated {$updatedCount} of {$totalRows} rows in {$totalTimeInSeconds} seconds.\n" );
		$this->output( "Please set \$wgOATHSecretKey to the new value in LocalSettings.php.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = ReEncryptSecrets::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
