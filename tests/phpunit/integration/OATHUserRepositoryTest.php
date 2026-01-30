<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\OATHUserRepository
 */
class OATHUserRepositoryTest extends MediaWikiIntegrationTestCase {
	public function testLookupCreateRemoveKey(): void {
		$user = $this->getTestUser()->getUser();

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );
		$dbProvider->method( 'getReplicaDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );

		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$module = $moduleRegistry->getModuleByKey( 'totp' );

		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( 12345 );
		$lookupFactory = $this->createMock( CentralIdLookupFactory::class );
		$lookupFactory->method( 'getLookup' )->willReturn( $lookup );

		$logger = $this->createMock( LoggerInterface::class );

		$repository = new OATHUserRepository(
			$dbProvider,
			new EmptyBagOStuff(),
			$moduleRegistry,
			$lookupFactory,
			$logger
		);

		$oathUser = $repository->findByUser( $user );
		$this->assertEquals( 12345, $oathUser->getCentralId() );
		$this->assertEquals( [], $oathUser->getKeys() );

		/** @var TOTPKey $key */
		$key = $repository->createKey(
			$oathUser,
			$module,
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$this->assertNotEmpty(
			$this->getDb()->newSelectQueryBuilder()
				->select( '1' )
				->from( 'oathauth_devices' )
				->where( [ 'oad_user' => $oathUser->getCentralId() ] )
		);

		$this->assertArrayEquals( [ $key ], $oathUser->getKeys() );

		// Test looking it up again from the database
		$this->assertArrayEquals( [ $key ], $repository->findByUser( $user )->getKeys() );

		$repository->updateKey( $oathUser, $key );

		$repository->removeKey(
			$oathUser,
			$key,
			'127.0.0.1',
			true
		);

		$this->assertEquals( [], $oathUser->getKeys() );
		$this->assertEquals( [], $repository->findByUser( $user )->getKeys() );
	}

	public function testUserWithNoCentralId() {
		$user = $this->getTestUser()->getUser();

		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( 0 );
		$lookupFactory = $this->createMock( CentralIdLookupFactory::class );
		$lookupFactory->method( 'getLookup' )->willReturn( $lookup );

		$repository = new OATHUserRepository(
			$this->createNoOpMock( IConnectionProvider::class ),
			new EmptyBagOStuff(),
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$lookupFactory,
			$this->createMock( LoggerInterface::class ),
		);

		$oathUser = $repository->findByUser( $user );
		$this->assertSame( 0, $oathUser->getCentralId() );
		$this->assertEquals( [], $oathUser->getKeys() );
	}
}
