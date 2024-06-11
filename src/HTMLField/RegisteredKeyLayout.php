<?php

namespace MediaWiki\Extension\WebAuthn\HTMLField;

use MediaWiki\HTMLForm\HTMLFormField;
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

		$name = new LabelWidget( [
			'label' => new HtmlSnippet( '<b>' . $nameValue . '</b>' )
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
				$name, $removeButton
			]
		] );
	}
}
