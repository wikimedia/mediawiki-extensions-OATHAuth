<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ObjectCache\HashBagOStuff;

return [
	'OATHAuthLogger' => static function ( MediaWikiServices $services ): OATHAuthLogger {
		return new OATHAuthLogger(
			$services->getExtensionRegistry(),
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' )
		);
	},
	'OATHAuthModuleRegistry' => static function ( MediaWikiServices $services ): OATHAuthModuleRegistry {
		return new OATHAuthModuleRegistry(
			$services->getDBLoadBalancerFactory(),
			$services->getObjectFactory(),
			ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' ),
		);
	},
	'OATHUserRepository' => static function ( MediaWikiServices $services ): OATHUserRepository {
		return new OATHUserRepository(
			$services->getDBLoadBalancerFactory(),
			new HashBagOStuff( [
				'maxKey' => 5
			] ),
			$services->getService( 'OATHAuthModuleRegistry' ),
			$services->getCentralIdLookupFactory(),
			LoggerFactory::getInstance( 'authentication' )
		);
	},
	'OATHAuth.EncryptionHelper' => static function ( MediaWikiServices $services ): EncryptionHelper {
		return new EncryptionHelper(
			new ServiceOptions(
				EncryptionHelper::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
			),
		);
	},
	'WebAuthnAuthenticator' => static function ( MediaWikiServices $services ): WebAuthnAuthenticator {
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = $services->getService( 'OATHAuthModuleRegistry' );
		/** @var OATHUserRepository $userRepo */
		$userRepo = $services->getService( 'OATHUserRepository' );
		/** @var OATHAuthLogger $oathLogger */
		$oathLogger = $services->getService( 'OATHAuthLogger' );

		/** @var WebAuthn $webAuthn */
		$webAuthn = $moduleRegistry->getModuleByKey( WebAuthn::MODULE_ID );
		/** @var RecoveryCodes $recovery */
		$recovery = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		return new WebAuthnAuthenticator(
			$userRepo,
			$webAuthn,
			$recovery,
			$oathLogger,
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' ),
			$services->getAuthManager(),
			$services->getUrlUtils(),
			$services->getUserFactory(),
		);
	}
];
