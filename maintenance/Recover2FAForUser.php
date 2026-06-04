<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class Recover2FAForUser extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create and email recovery codes for a specific user' );
		$this->addArg( 'user', 'The username to create recovery codes for' );
		$this->addArg(
			'email',
			"The email address to send recovery codes to (if the user doesn't already have an email set)",
			false
		);
		$this->requireExtension( 'OATHAuth' );
	}

	public function execute() {
		$service = OATHAuthServices::getInstance( $this->getServiceContainer() )->getExpiringRecoveryCodeGenerator();

		$user = $this->getArg();
		$result = $service->attemptToGenerateRecoveryCodes(
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ),
			$user,
			$this->getArg( 1, '' ),
		);

		if ( !$result->isOK() ) {
			$this->fatalError( $result->getWikiText() );
		}

		$this->output( "Expiring recovery codes generated successfully and emailed to $user.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = Recover2FAForUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
