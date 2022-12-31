<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'OATHAuthModuleRegistry' => static function ( MediaWikiServices $services ) {
		return new OATHAuthModuleRegistry();
	},
	'OATHUserRepository' => static function ( MediaWikiServices $services ) {
		return new OATHUserRepository(
			new ServiceOptions(
				OATHUserRepository::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
			),
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
