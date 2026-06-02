<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Maintenance\PurgeExpiredTemporaryRecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\PurgeExpiredTemporaryRecoveryCodes
 * @group Database
 */
class PurgeExpiredTemporaryRecoveryCodesTest extends MaintenanceBaseTestCase {

	use UserWith2FATrait;

	protected function getMaintenanceClass() {
		return PurgeExpiredTemporaryRecoveryCodes::class;
	}

	public function testPurgeForNoRowsToPurge(): void {
		$this->expectOutputString( "Done. Updated 0 of 0 rows.\n" );
		$this->maintenance->execute();
	}

	public function testPurgeUserWith2FA(): void {
		$this->setupUserWith2FA();

		$this->expectOutputString( "Done. Updated 0 of 1 rows.\n" );
		$this->maintenance->execute();
	}

	public function testRemoveExpired(): void {
		$this->markTestSkipped( 'TODO: Need to insert DB row directly' );
		[ $userRepository, $moduleRegistry, $oathUser, ] = $this->setupConfig();

		ConvertibleTimestamp::setFakeTime( '20260101000000' );

		$userRepository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME ),
			RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [
				'ABCDEFGH',
				[ 'IJKLMNOP', [ 'expiry' => '20200101000000' ] ],
			] ] )->jsonSerialize(),
			'127.0.0.1'
		);

		$this->expectOutputString( "Done. Updated 1 of 1 rows.\n" );
		$this->maintenance->execute();
	}
}
