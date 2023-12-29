<?php

namespace MediaWiki\Extension\WebAuthn\Config;

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;

class WebAuthnConfig extends MultiConfig {
	public function __construct() {
		parent::__construct( [
			new GlobalVarConfig( 'wgWebAuthn_' ),
			new HashConfig( [
				// maximal number of keys user can have registered
				'maxKeysPerUser' => 5
			] )
		] );
	}
}
