<?php

/**
 * Special page to display key information to the user
 *
 * @file
 * @ingroup Extensions
 */

class SpecialOATH extends UnlistedSpecialPage {
	/** @var OATHUser|null */
	private $OATHUser;

	/**
	 * Initialize the OATH user based on the current local User object in the context
	 */
	public function __construct() {
		parent::__construct( 'OATH' );

		$this->OATHRepository = new OATHUserRepository( wfGetLB() );
		$this->OATHUser = $this->OATHRepository->findByUser( $this->getUser() );
	}

	/**
	 * Perform the correct form based on the action
	 *
	 * @param null|string $par Sub-page
	 */
	public function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->setHeaders();
			$this->getOutput()->setPagetitle( $this->msg( 'oathauth-notloggedin' ) );
			$this->getOutput()->addWikiMsg( 'oathauth-mustbeloggedin' );
			return;
		}

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action == "enable" ) {
			$this->enable();
		} elseif ( $action == "disable" ) {
			$this->disable();
		}
	}

	/**
	 * @return bool
	 */
	private function enable() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'oathauth-enable' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		if ( $this->OATHUser->getKey() ) {
			$this->getOutput()->addWikiMsg( 'oathauth-alreadyenabled' );

			return true;
		}

		if ( null === $this->getRequest()->getSessionData( 'oathauth_key' ) ) {
			$this->getRequest()->setSessionData( 'oathauth_key', OATHAuthKey::newFromRandom() );
		}

		$info['token'] = array(
			'type' => 'text',
			'default' => '',
			'label-message' => 'oathauth-token',
			'name' => 'token',
		);
		$info['mode'] = array(
			'type' => 'hidden',
			'default' => 'enable',
			'name' => 'mode',
		);
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'enable',
			'name' => 'action',
		);
		$form = new HTMLForm(
			$info,
			$this->getContext(),
			'oathauth-verify'
		);
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		if ( !$form->show() ) {
			$this->displaySecret();
		}

		return true;
	}

	private function displaySecret() {
		$this->getOutput()->addModules( 'ext.oathauth' );

		/** @var OATHAuthKey $key */
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );
		$secret = $key->getSecret();

		$out = '<strong>' . $this->msg( 'oathauth-account' )->escaped() . '</strong> '
			. $this->OATHUser->getAccount() . '<br/>'
			. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong> '
			. $secret . '<br/>'
			. '<br/>'
			. '<div id="qrcode"></div>';

		$this->getOutput()->addHTML( ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.loader.using', array(
				array( 'ext.oathauth' ),
				new XmlJsCode(
					'function () {'
						. '$("#qrcode").qrcode("otpauth://totp/'
						. $this->OATHUser->getAccount()
						. '?secret=' . $secret. '");'
					. '}'
				)
			) )
		) );

		$this->getOutput()->addHTML( $out );
		$this->getOutput()->addWikiMsg( 'openstackmanager-scratchtokens' );
		$this->getOutput()->addHTML(
			$this->createResourceList( $key->getScratchTokens() ) );
	}

	/**
	 * @return bool
	 */
	private function disable() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'oathauth-disable' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$info['token'] = array(
			'type' => 'text',
			'label-message' => 'oathauth-token',
			'name' => 'token',
		);
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'disable',
			'name' => 'action',
		);
		$form = new HTMLForm(
			$info,
			$this->getContext(),
			'oathauth-disable'
		);
		$form->setSubmitID( 'oauth-form-disablesubmit' );
		$form->setSubmitCallback( array( $this, 'tryDisableSubmit' ) );
		$form->show();
		return true;
	}

	/**
	 * @param $resources array
	 * @return string
	 */
	private function createResourceList( $resources ) {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', array(), $resource );
		}
		return Html::rawElement( 'ul', array(), $resourceList );
	}

	/**
	 * @param $formData array
	 * @return bool
	 */
	public function tryValidateSubmit( $formData ) {
		/** @var OATHAuthKey $key */
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );

		$verify = $key->verifyToken( $formData['token'], $this->OATHUser );
		$out = '';
		if ( $verify ) {
			$this->OATHUser->setKey( $key );
			$this->OATHRepository->persist( $this->OATHUser );
			$this->getRequest()->setSessionData( 'oathauth_key', null );

			$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
			if ( $formData['returnto'] ) {
				$out = '<br />';
				$title = Title::newFromText( $formData['returnto'] );
				$out .= Linker::link( $title, $this->msg( 'oathauth-backtopreferences' )->escaped() );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';

			$out .= Linker::link(
				$this->getPageTitle(),
				$this->msg( 'oathauth-reattemptenable' )->escaped(),
				array(),
				array(
					'action' => 'enable',
					'returnto' => $formData['returnto']
				)
			);
		}

		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param $formData array
	 * @return bool
	 */
	public function tryDisableSubmit( $formData ) {
		$verify = $this->OATHUser->getKey()->verifyToken( $formData['token'], $this->OATHUser );
		if ( !$verify ) {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';
			$out .= Linker::link(
				$this->getPageTitle(),
				$this->msg( 'oathauth-reattemptdisable' )->escaped(),
				array(),
				array( 'action' => 'disable' )
			);
			$this->getOutput()->addHTML( $out );
			return true;
		}

		$this->OATHRepository->remove( $this->OATHUser );

		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
		if ( $formData['returnto'] ) {
			$out = '<br />';
			$title = Title::newFromText( $formData['returnto'] );
			$out .= Linker::link( $title, $this->msg( 'oathauth-backtopreferences' )->escaped() );
			$this->getOutput()->addHTML( $out );
		}

		return true;
	}

	protected function getGroupName() {
		return 'oath';
	}
}
