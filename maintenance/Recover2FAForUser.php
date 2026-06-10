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
		$services = $this->getServiceContainer();
		$oathServices = OATHAuthServices::getInstance( $services );
		$recoveryCodeGenerator = $oathServices->getExpiringRecoveryCodeGenerator();
		$userRepo = $oathServices->getUserRepository();

		$username = $this->getArg();

		$user = $services->getUserFactory()->newFromName( $username );
		$enforce2FA = $this->getConfig()->get( 'OATHAuthEnforce2FAForAll' );

		if ( !$enforce2FA || ( $user && $userRepo->findByUser( $user )->isTwoFactorAuthEnabled() ) ) {
			$result = $recoveryCodeGenerator->attemptToGenerateRecoveryCodes(
				performer: User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ),
				username: $username,
				email: $this->getArg( 1, '' ),
				reason: 'Recover2FAForUser maintenance script',
				logToWiki: false,
			);
		} elseif ( $user && $userRepo->userIsRequiredToHave2FAEnabled( $user ) ) {
			$result = $recoveryCodeGenerator->attemptToCreateInitial2FACodes(
				performer: User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ),
				username: $username,
				email: $this->getArg( 1, '' ),
				sendEmail: true,
			);
		} else {
			$this->fatalError( wfMessage( 'oathauth-recover-fail-no-2fa-or-required' )->text() );
		}

		if ( !$result->isOK() ) {
			$this->fatalError( $result->getWikiText() );
		}

		$this->output( "Expiring recovery codes generated successfully and emailed to $username.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = Recover2FAForUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
