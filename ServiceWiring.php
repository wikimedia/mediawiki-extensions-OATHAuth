<?php

use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'OATHAuthModuleRegistry' => static function ( MediaWikiServices $services ) {
		return new OATHAuthModuleRegistry(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' ),
		);
	},
	'OATHUserRepository' => static function ( MediaWikiServices $services ) {
		return new OATHUserRepository(
			$services->getDBLoadBalancerFactory(),
			new HashBagOStuff( [
				'maxKey' => 5
			] ),
			$services->getService( 'OATHAuthModuleRegistry' ),
			$services->getCentralIdLookupFactory(),
			LoggerFactory::getInstance( 'authentication' )
		);
	}
];
