<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

// @codeCoverageIgnoreStart
use MediaWiki\Extension\OATHAuth\Maintenance\Base\AllUsers;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\User;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class Enable2FAForUsersWithout extends AllUsers {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Enables 2FA (using temporary recovery codes) that are required to have it"
		);
	}

	private int $twoFAEnabled = 0;
	private int $twoFASetup = 0;

	private int $twoFANotRequired = 0;

	protected function doWork( OATHUserRepository $repo, User $user, string $username ): void {
		$oathUser = $repo->findByUser( $user );
		if ( $oathUser->isTwoFactorAuthEnabled() ) {
			$this->output( "User $username already has two-factor authentication enabled!\n" );
			$this->twoFAEnabled++;
			return;
		}

		// If this script is not being run with --apply-to-all, check if the user is required to have 2FA
		// based on APCOND_OATH_HAS2FA
		if ( !$this->getOption( 'apply-to-all' ) && !$this->isRequiredToHave2FAEnabled( $user ) ) {
			$this->output( "User $username is not required to have two-factor authentication enabled!\n" );
			$this->twoFANotRequired++;

			return;
		}

		static $performer = null;
		if ( $performer === null ) {
			$performer = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		}

		$expiringGenerator = OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getExpiringRecoveryCodeGenerator();

		// TODO: What do we want to actually do if a user doesn't have an email set? Generate anyway?
		$expiringGenerator->attemptToCreateInitial2FACodes(
			performer: $performer,
			username: $username,
			email: $user->getEmail(),
			sendEmail: true,
		);

		$this->output(
		// phpcs:disable Generic.Files.LineLength.TooLong
			"User $username did not have two-factor authentication enabled, so initial codes have been sent by email!\n"
		);
		$this->twoFASetup++;
	}

	protected function outputAtEnd(): void {
		$this->output(
			"2FA already enabled: {$this->twoFAEnabled}; 2FA setup: {$this->twoFASetup}; "
				. "2FA not required: {$this->twoFANotRequired}\n"
		);
	}
}

// @codeCoverageIgnoreStart
$maintClass = Enable2FAForUsersWithout::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
