<?php

namespace MediaWiki\Extension\WebAuthn\HTMLField;

use HTMLFormField;
use OOUI\ButtonInputWidget;
use OOUI\Exception;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;

class RegisteredKeyLayout extends HTMLFormField {

	/**
	 * @param array $value
	 * @return HorizontalLayout
	 * @throws Exception
	 */
	public function getInputHTML( $value ) {
		$nameValue = $value['name'];
		$signCountValue = $value['signCount'];

		$name = new LabelWidget( [
			'label' => new HtmlSnippet( '<b>' . $nameValue . '</b>' )
		] );
		$signCount = new LabelWidget( [
			'label' => wfMessage( 'webauthn-ui-signcount-label', $signCountValue )->text()
		] );
		$removeButton = new ButtonInputWidget( [
			'framed' => false,
			'flags' => [ 'primary', 'progressive' ],
			'label' => wfMessage( 'webuathn-ui-remove-key' )->plain(),
			'classes' => [ 'removeButton' ],
			'disabled' => true,
			'value' => $nameValue,
			'infusable' => true
		] );

		return new HorizontalLayout( [
			'classes' => [ 'webauthn-key-layout' ],
			'items' => [
				$name, $signCount, $removeButton
			]
		] );
	}
}
