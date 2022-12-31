<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'OATHAuth' => static function ( MediaWikiServices $services ) {
		return new OATHAuth(
			$services->getMainConfig(),
			$services->getDBLoadBalancerFactory()
		);
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
			$services->getService( 'OATHAuth' ),
			$services->getCentralIdLookupFactory(),
			LoggerFactory::getInstance( 'authentication' )
		);
	}
];
