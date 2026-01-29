<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthLogger
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @group Database
 */
class OATHAuthLoggerTest extends MediaWikiIntegrationTestCase {

	public function testLogImplicitVerification(): void {
		$logger = OATHAuthServices::getInstance()->getLogger();

		$this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'oath', 'log_action' => 'verify' ] )
			->caller( __METHOD__ )
			->assertEmptyResult();

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();
		$logger->logImplicitVerification( $performer, $target );

		$actorStore = $this->getServiceContainer()->getActorStore();
		$performerActorId = $actorStore->findActorId( $performer, $this->getDb() );

		$this->newSelectQueryBuilder()
			->select( [ 'log_actor', 'log_namespace', 'log_title' ] )
			->from( 'logging' )
			->where( [ 'log_type' => 'oath', 'log_action' => 'verify' ] )
			->caller( __METHOD__ )
			->assertRowValue( [
				(string)$performerActorId,
				(string)NS_USER,
				str_replace( ' ', '_', $target->getName() ),
			] );
	}

	public function testLogImplicitVerification_CheckUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$logger = OATHAuthServices::getInstance()->getLogger();

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();

		// Creating test users may insert some CU logs, so we expect that the total count increases by one,
		// but we don't operate on actual numbers.
		$countBefore = $this->newSelectQueryBuilder()
			->select( 'cule_id' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->fetchRowCount();

		$logger->logImplicitVerification( $performer, $target );

		$countAfter = $this->newSelectQueryBuilder()
			->select( 'cule_id' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->fetchRowCount();

		$this->assertSame( 1, $countAfter - $countBefore );
	}
}
