<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MainConfigNames;

trait UserWith2FATrait {

	/**
	 * // phpcs:disable Generic.Files.LineLength.TooLong
	 * @return array{OATHUserRepository, \MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry, \MediaWiki\Extension\OATHAuth\OATHUser, \MediaWiki\User\User}
	 */
	private function setupConfig(): array {
		// Ensure to use local because CentralAuth may exist in CI
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
		] );

		$user = $this->getTestSysop()->getUser();
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		$userRepository = $services->getUserRepository();
		$moduleRegistry = $services->getModuleRegistry();

		$oathUser = $userRepository->findByUser( $user );

		return [ $userRepository, $moduleRegistry, $oathUser, $user ];
	}

	/**
	 * @return array{OATHUserRepository, \MediaWiki\User\User, TOTPKey, RecoveryCodeKeys, string[]}
	 */
	private function setupUserWith2FA(): array {
		[ $userRepository, $moduleRegistry, $oathUser, $user ] = $this->setupConfig();

		$totpKey = $userRepository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( TOTP::MODULE_NAME ),
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$recoveryCodes = new RecoveryCodeKeys( null, null, null, [] );
		$recoveryCodes->regenerateRecoveryCodeKeys();

		$recoveryKey = $userRepository->createKey(
			$oathUser,
			$moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME ),
			$recoveryCodes->jsonSerialize(),
			'127.0.0.1'
		);

		return [ $userRepository, $user, $totpKey, $recoveryKey, $recoveryCodes->getRecoveryCodeKeys() ];
	}
}
