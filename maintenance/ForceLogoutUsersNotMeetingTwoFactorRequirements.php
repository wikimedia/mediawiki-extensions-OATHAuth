<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use InvalidateUserSessions;
use MediaWiki\Extension\OATHAuth\Maintenance\Base\AllUsers;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\User;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
// @codeCoverageIgnoreEnd

class ForceLogoutUsersNotMeetingTwoFactorRequirements extends AllUsers {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Force log out users that are not meeting the 2FA requirements of their user groups'
		);
	}

	private int $twoFAEnabled = 0;

	private int $twoFANotRequired = 0;

	private int $loggedOut = 0;

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

		$child = $this->createChild( InvalidateUserSessions::class );
		$child->addOption( 'user', $username );
		$child->execute();
		$this->loggedOut++;
	}

	protected function outputAtEnd(): void {
		$this->output(
			"2FA already enabled: {$this->twoFAEnabled}; 2FA not required: {$this->twoFANotRequired}; "
			. "Logged out: {$this->loggedOut}\n"
		);
	}

}

// @codeCoverageIgnoreStart
$maintClass = ForceLogoutUsersNotMeetingTwoFactorRequirements::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
