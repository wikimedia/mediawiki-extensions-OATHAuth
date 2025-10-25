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
 *
 * @file
 * @ingroup Maintenance
 */

declare( strict_types = 1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Updates TOTP secret to an encrypted format in the database
 *
 * Usage: php UpdateTOTPSecretsToEncryptedFormat.php
 */
class UpdateTOTPSecretsToEncryptedFormat extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'OATHAuth' );
		$this->addDescription( 'Update TOTP secrets to use encypted format within database' );
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		if ( !extension_loaded( 'sodium' ) ) {
			$this->fatalError( "libsodium is not installed with php in this environment!" );
		}

		$encryptionHelper = OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getEncryptionHelper();

		if ( !$encryptionHelper->isEnabled() ) {
			// phpcs:disable Generic.Files.LineLength.TooLong
			$this->fatalError( "\$wgOATHSecretKey is not set correctly! It should be set to an immutable, 64-character hexadecimal value!" );
		}

		$startTime = time();
		$updatedCount = 0;
		$totalRows = 0;

		$services = $this->getServiceContainer();

		$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
		$totpModuleId = $moduleRegistry->getModuleId( TOTP::MODULE_NAME );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_data' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => $totpModuleId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$totalRows++;
			$data = FormatJson::decode( $row->oad_data, true );

			if ( !array_key_exists( 'nonce', $data ) ) {
				$key = TOTPKey::newFromArray( [
					'secret' => $data['secret'],
				] );
				$dataWithEncryptedSecret = $key->jsonSerialize();

				$dbw->newUpdateQueryBuilder()
					->update( 'oathauth_devices' )
					->set( [ 'oad_data' => FormatJson::encode( $dataWithEncryptedSecret ) ] )
					->where( [ 'oad_id' => $row->oad_id ] )
					->caller( __METHOD__ )
					->execute();

				$updatedCount++;
			}
		}

		$totalTimeInSeconds = time() - $startTime;
		$this->output( "Done. Updated {$updatedCount} of {$totalRows} rows in {$totalTimeInSeconds} seconds.\n" );
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdateTOTPSecretsToEncryptedFormat::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
