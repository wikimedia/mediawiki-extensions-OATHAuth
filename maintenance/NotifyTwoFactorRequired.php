<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Maintenance\Base\AllUsers;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\User;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class NotifyTwoFactorRequired extends AllUsers {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Sends a notification to users that they're required to have 2FA enabled. " .
			"Can be used to send to one user by passing the username, else all users on the wiki without 2FA enabled."
		);
		$this->addOption(
			'user', 'The username to send the notification to',
			false, true, false, true
		);
		$this->addOption(
			'date',
			'The date to include in the message sent to users (e.g. 20260630000000)',
			true, true
		);
		$this->requireExtension( 'Echo' );
	}

	private int $twoFAEnabled = 0;
	private int $twoFANeeded = 0;

	private int $twoFANotRequired = 0;

	protected function doWork( OATHUserRepository $repo, User $user, string $username ): void {
		$oathUser = $repo->findByUser( $user );
		if ( $oathUser->isTwoFactorAuthEnabled() ) {
			$this->output( "User $username already has two-factor authentication enabled!\n" );
			$this->twoFAEnabled++;
			return;
		}

		// If this script is not being run with --apply-to-all, check if the user is required to have 2FA
		// based on APCOND_OATH_HAS2FA for an appropriate group being in $wgRestrictedGroups
		if ( !$this->getOption( 'apply-to-all' ) && !$this->isRequiredToHave2FAEnabled( $user ) ) {
			$this->output( "User $username is not required to have two-factor authentication enabled!\n" );
			$this->twoFANotRequired++;
			return;
		}

		$date = $this->getOption( 'date' );
		Manager::notify2FARequiredForUser( $user, $date );
		$this->output(
		// phpcs:disable Generic.Files.LineLength.TooLong
			"User $username does not have two-factor authentication enabled, so notification has been sent!\n"
		);
		$this->twoFANeeded++;
	}

	protected function outputAtEnd(): void {
		$this->output(
			"2FA already enabled: {$this->twoFAEnabled}; 2FA needed: {$this->twoFANeeded}; "
				. "2FA not required: {$this->twoFANotRequired}\n"
		);
	}
}

// @codeCoverageIgnoreStart
$maintClass = NotifyTwoFactorRequired::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
