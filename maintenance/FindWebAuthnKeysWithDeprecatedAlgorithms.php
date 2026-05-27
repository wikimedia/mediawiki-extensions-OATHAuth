<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use Cose\Algorithms;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
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

class FindWebAuthnKeysWithDeprecatedAlgorithms extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( '' );
		$this->requireExtension( 'OATHAuth' );
	}

	public function execute() {
		$services = $this->getServiceContainer();

		$moduleRegistry = OATHAuthServices::getInstance( $services )->getModuleRegistry();
		$webauthnModuleId = $moduleRegistry->getModuleId( WebAuthn::MODULE_NAME );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );

		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_user', 'oad_data' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => $webauthnModuleId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$count = 0;
		$deprecatedCount = 0;
		foreach ( $res as $row ) {
			$count++;
			$keyData = FormatJson::decode( $row->oad_data, true );

			$publicKeyAlg = WebAuthnKey::getPublicKeyAlgorithm(
				WebAuthnKey::getCoseKey( base64_decode( $keyData['credentialPublicKey'] ) )
			);

			if ( $publicKeyAlg === null ) {
				$this->output( "Key Id {$row->oad_id} is not a valid COSE key.\n" );
				continue;
			}
			if ( WebAuthnKey::isDeprecatedPublicKeyAlgorithm( $publicKeyAlg ) ) {
				$deprecatedCount++;
				$algoString = Algorithms::getHashAlgorithmFor( $publicKeyAlg );
				$this->output(
					"Key Id {$row->oad_id} is using a deprecated algorithm: {$algoString}.\n"
				);
			}
		}

		$this->output( "{$count} keys found. {$deprecatedCount} keys are using deprecated algorithms.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = FindWebAuthnKeysWithDeprecatedAlgorithms::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
