<?php

namespace MediaWiki\Extension\WebAuthn;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Extension\WebAuthn\Auth\WebAuthnAuthenticationRequest;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

class Hooks implements
	AuthChangeFormFieldsHook
{

	/** @inheritDoc */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$req = AuthenticationRequest::getRequestByClass( $requests, WebAuthnAuthenticationRequest::class );
		if ( $req ) {
			$formDescriptor['webauthn-nojs'] = [
				'class' => NoJsInfoField::class,
				'weight' => -50,
			];
		}
	}

}
