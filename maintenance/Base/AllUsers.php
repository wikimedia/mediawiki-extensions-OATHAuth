<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance\Base;

use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;

abstract class AllUsers extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'skip-blocked', 'Skip users that are blocked' );
		$this->addOption(
			'apply-to-all',
			'Apply script to all users, rather than following current 2FA requirements in $wgRestrictedGroups'
		);
		$this->addOption(
			'allow-no-email',
			'Allow users without an email address to be processed',
		);
		$this->requireExtension( 'OATHAuth' );
	}

	abstract protected function doWork( OATHUserRepository $repo, User $user, string $username ): void;

	protected function outputAtEnd(): void {
	}

	protected function isRequiredToHave2FAEnabled( User $user ): bool {
		// If no groups are returned, they aren't required to have 2FA
		return OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository()
			->userIsRequiredToHave2FAEnabled( $user );
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

		$skipBlocked = $this->hasOption( 'skip-blocked' );

		$repo = OATHAuthServices::getInstance( $services )->getUserRepository();

		$total = 0;
		$blocked = 0;
		$otherSkipped = 0;
		$withoutEmail = 0;
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

			if ( !$this->hasOption( 'allow-no-email' ) && $user->getEmail() === '' ) {
				$this->output( "User $username does not have an email address set..\n" );
				$withoutEmail++;
			}

			$this->doWork( $repo, $user, $username );
		}

		$this->output(
			"Total: $total; Blocked: $blocked; Without email: $withoutEmail; Other skipped: $otherSkipped\n"
		);
		$this->outputAtEnd();
		$this->output( "Done.\n" );
	}
}
