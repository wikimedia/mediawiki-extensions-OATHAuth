<?php

namespace MediaWiki\Extension\WebAuthn;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Extension\WebAuthn\Auth\WebAuthnAuthenticationRequest;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

class Hooks implements
	AuthChangeFormFieldsHook,
	ResourceLoaderGetConfigVarsHook
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

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgWebAuthnLimitPasskeysToRoaming'] = $config->get( 'WebAuthnLimitPasskeysToRoaming' );
	}
}
