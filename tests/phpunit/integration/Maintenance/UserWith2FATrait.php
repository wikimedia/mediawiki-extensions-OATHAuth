<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;

trait UserWith2FATrait {

	private function setupUserWith2FA(): array {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$repository = $services->getUserRepository();
		$moduleRegistry = $services->getModuleRegistry();

		$oathUser = $repository->findByUser( $user );

		$repository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( TOTP::MODULE_NAME ),
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$recoveryCodes = new RecoveryCodeKeys( null, null, null, [] );
		$recoveryCodes->regenerateRecoveryCodeKeys();

		$repository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME ),
			$recoveryCodes->jsonSerialize(),
			'127.0.0.1'
		);

		return [ $repository, $user, $recoveryCodes->getRecoveryCodeKeys() ];
	}
}
