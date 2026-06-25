<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\HTMLField;

use MediaWiki\HTMLForm\HTMLFormField;
use OOUI\ActionFieldLayout;
use OOUI\ButtonWidget;
use OOUI\HtmlSnippet;
use OOUI\TextInputWidget;

class AddKeyLayout extends HTMLFormField {

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		return (string)new ActionFieldLayout( $this->makeInputWidget(), $this->makeButtonWidget() );
	}

	/** @inheritDoc */
	public function getOOUI( $value ) {
		$label = $this->getLabel();
		return new ActionFieldLayout( $this->makeInputWidget(), $this->makeButtonWidget(), [
			'label' => $label !== '' ? new HtmlSnippet( $label ) : null,
			'align' => 'top',
		] );
	}

	private function makeInputWidget(): TextInputWidget {
		return new TextInputWidget( [
			'id' => 'key_name',
			'name' => 'key_name',
			'required' => false,
			'infusable' => true,
			'autofocus' => true,
		] );
	}

	private function makeButtonWidget(): ButtonWidget {
		// We initiate the button disabled, to avoid user interacting
		// with the form, until the client-side script is loaded and ready
		return new ButtonWidget( [
			'flags' => [ 'primary', 'progressive' ],
			'label' => wfMessage( 'oathauth-webauthn-ui-add-key' )->plain(),
			'disabled' => true,
			'id' => 'button_add_key',
			'infusable' => true,
		] );
	}
}
