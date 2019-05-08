<?php

namespace MediaWiki\Extension\WebAuthn;

use GuzzleHttp\Psr7\ServerRequest;
use WebRequest;

/**
 * Purpose of this class is to convert MW WebRequest to
 * instance of ServerRequest, required by WebAuthn-lib
 * It does not provide full-fledged ServerRequest, just the
 * functionality we actually need
 */
class Request extends ServerRequest {
	public static function newFromWebRequest( WebRequest $request ) {
		return new static(
			$request->getMethod(),
			$request->getFullRequestURL()
		);
	}
}
