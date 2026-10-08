<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth;

use InvalidArgumentException;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class OATHAuthModuleRegistry {

	/** @var array<string,int>|null Maps module names to ids */
	private ?array $moduleIds = null;

	/**
	 * @param IConnectionProvider $dbProvider
	 * @param ObjectFactory $objectFactory
	 * @param array<string,callable> $modules
	 */
	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly ObjectFactory $objectFactory,
		private readonly array $modules,
	) {
	}

	public function moduleExists( string $moduleKey ): bool {
		return isset( $this->modules[$moduleKey] );
	}

	public function getModuleByKey( string $key ): IModule {
		if ( !isset( $this->modules[$key] ) ) {
			throw new InvalidArgumentException( "No such two-factor module $key" );
		}

		$data = $this->modules[$key];
		return $this->objectFactory->createObject(
			$data,
			[ 'assertClass' => IModule::class ]
		);
	}

	/**
	 * Get all modules registered on the wiki
	 *
	 * @return IModule[]
	 */
	public function getAllModules(): array {
		$modules = [];
		foreach ( $this->modules as $key => $callback ) {
			$modules[$key] = $this->getModuleByKey( $key );
		}
		return $modules;
	}

	/**
	 * Returns the numerical ID for the module with the specified key.
	 */
	public function getModuleId( string $key ): int {
		$ids = $this->getModuleIds();
		if ( isset( $ids[$key] ) ) {
			return $ids[$key];
		}

		throw new InvalidArgumentException( "Module $key does not seem to exist" );
	}

	/**
	 * @return array<string,int> Maps module names to ids
	 */
	public function getModuleIds(): array {
		$this->moduleIds ??= $this->getModuleIdsFromDatabase( false );

		$missing = array_diff(
			array_keys( $this->modules ),
			array_keys( $this->moduleIds )
		);

		if ( $missing ) {
			$insert = $this->dbProvider
				->getPrimaryDatabase( 'virtual-oathauth' )
				->newInsertQueryBuilder()
				->insertInto( 'oathauth_types' )
				->caller( __METHOD__ );

			foreach ( $missing as $name ) {
				$insert->row( [ 'oat_name' => $name ] );
			}

			$insert->execute();
			$this->moduleIds = $this->getModuleIdsFromDatabase( true );
		}

		return $this->moduleIds;
	}

	/**
	 * @return array<string,int> Maps module names to ids
	 */
	private function getModuleIdsFromDatabase( bool $fromPrimary ): array {
		$ids = [];

		if ( $fromPrimary ) {
			$dbr = $this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' );
		} else {
			$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-oathauth' );
		}

		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'oat_id', 'oat_name' ] )
			->from( 'oathauth_types' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $rows as $row ) {
			$ids[$row->oat_name] = (int)$row->oat_id;
		}

		return $ids;
	}

}
