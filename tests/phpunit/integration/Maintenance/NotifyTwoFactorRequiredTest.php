<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\OATHAuth\Maintenance\NotifyTwoFactorRequired;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Maintenance\NotifyTwoFactorRequired
 * @covers \MediaWiki\Extension\OATHAuth\Notifications\Manager
 * @group Database
 */
class NotifyTwoFactorRequiredTest extends MaintenanceBaseTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
	}

	protected function getMaintenanceClass() {
		return NotifyTwoFactorRequired::class;
	}

	public function testNotifyNoUsers(): void {
		$this->maintenance->setOption( 'date', '20260630000000' );

		$this->expectOutputString(
			"Total: 0; Blocked: 0; Other skipped: 0\n" .
			"2FA already enabled: 0; 2FA needed: 0\n" .
			"Done.\n"
		);

		$this->maintenance->execute();
	}

	public function testNotifyOneUser(): void {
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();

		$this->maintenance->setOption( 'date', '20260630000000' );
		$this->expectOutputRegex(
			"/User {$user->getName()} does not have two-factor authentication enabled, so notification has been sent!/"
		);

		$notificationCreated = false;
		$this->setTemporaryHook(
			'BeforeEchoEventInsert',
			static function ( Event $event ) use ( &$notificationCreated ) {
				if ( $event->getType() === 'oathauth-twofactor-required' ) {
					$notificationCreated = true;
				}
			}
		);

		$this->maintenance->execute();

		$this->assertTrue( $notificationCreated );
	}
}
