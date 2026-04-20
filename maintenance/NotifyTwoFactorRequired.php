<?php

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Notifications\Manager;
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

class NotifyTwoFactorRequired extends Maintenance {

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
		$this->addOption( 'skip-blocked', 'Skip users that are blocked' );
		$this->requireExtension( 'OATHAuth' );
		$this->requireExtension( 'Echo' );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$userFactory = $services->getUserFactory();

		/** @var User[] $users */
		$users = [];
		'@phan-var User[] $users';
		if ( $this->hasOption( 'user' ) ) {
			foreach ( (array)$this->getOption( 'user' ) as $username ) {
				$user = $userFactory->newFromName( $username );
				if ( $user === null || $user->getId() === 0 ) {
					$this->error( "User $username doesn't exist!" );
				}
				$users[] = $user;
			}
		} else {
			$db = $services
				->getDBLoadBalancerFactory()
				->getReplicaDatabase( 'virtual-oathauth' );

			$res = $db->newSelectQueryBuilder()
				->select( 'user_name' )
				->from( 'user' )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$users[] = $userFactory->newFromName( $row->user_name );
			}
		}

		$repo = OATHAuthServices::getInstance( $services )->getUserRepository();
		$skipBlocked = $this->hasOption( 'skip-blocked' );

		$date = $this->getOption( 'date' );

		$total = 0;
		$blocked = 0;
		$twoFAEnabled = 0;
		$twoFANeeded = 0;
		$otherSkipped = 0;
		foreach ( $users as $user ) {
			$total++;
			$username = $user->getName();
			if ( $skipBlocked && $user->getBlock() !== null ) {
				$this->output( "User $username is blocked, skipping...\n" );
				$blocked++;
				continue;
			}

			if ( $user->isSystemUser() ) {
				$this->output( "User $username is a system user, skipping...\n" );
				$otherSkipped++;
				continue;
			}

			if ( !$user->isNamed() ) {
				$otherSkipped++;
				continue;
			}

			$oathUser = $repo->findByUser( $user );
			if ( $oathUser->isTwoFactorAuthEnabled() ) {
				$this->output( "User $username already has two-factor authentication enabled!\n" );
				$twoFAEnabled++;
			} else {
				Manager::notify2FARequiredForUser( $user, $date );
				$this->output(
					"User $username does not have two-factor authentication enabled, so notification has been sent!\n"
				);
				$twoFANeeded++;
			}
		}

		$this->output(
			"Total: $total; Blocked: $blocked; Other skipped: $otherSkipped\n" .
			"2FA already enabled: $twoFAEnabled; 2FA needed: $twoFANeeded\n"
		);
		$this->output( "Done.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = NotifyTwoFactorRequired::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
