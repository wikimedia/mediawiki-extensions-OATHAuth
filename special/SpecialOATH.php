<?php

/**
 * Special page to display key information to the user
 *
 * @file
 * @ingroup Extensions
 */

class SpecialOATH extends SpecialPage {

	var $OATHUser;

	function __construct() {
		parent::__construct( 'OATH' );

		$this->OATHUser = OATHUser::newFromUser( $this->getUser() );
	}

	function execute( $par ) {
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action == "enable" ) {
			$this->enable();
		} elseif ( $action == "validate" ) {
			$this->validate();
		} elseif ( $action == "reset" ) {
			$this->reset();
		} elseif ( $action == "disable" ) {
			$this->disable();
		} else {
			$this->displayInfo();
		}
	}

	/**
	 * @return bool
	 */
	function enable() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'oathauth-enable' ) );

		if ( !$this->OATHUser->isEnabled() ) {
			$result = $this->OATHUser->enable();
			if ( !$result ) {
				$this->getOutput()->addWikiMsg( 'oathauth-failedtoenableoauth' );
				return true;
			}
		} elseif ( $this->OATHUser->isEnabled() && $this->OATHUser->isValidated() ) {
			$this->getOutput()->addWikiMsg( 'oathauth-alreadyenabled' );
			return true;
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
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$form = new HTMLForm( $info, 'oathauth-verify' );
		$form->setTitle( SpecialPage::getTitleFor( 'OATH' ) );
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		$this->displaySecret();

		return true;
	}

	/**
	 * @param $reset bool
	 */
	function displaySecret( $reset = false ) {
		$this->getOutput()->addModules( 'ext.oathauth' );
		if ( $reset ) {
			$secret = $this->OATHUser->getSecretReset();
		} else {
			$secret = $this->OATHUser->getSecret();
		}
		$out = '<strong>' . wfMsgHtml( 'oathauth-account' ) . '</strong> ' . $this->OATHUser->getAccount() . '<br/>';
		$out .= '<strong>' . wfMsgHtml( 'oathauth-secret' ) . '</strong> ' . $secret . '<br/>';
		$out .= '<br/>';
		$out .= '<div id="qrcode"></div>';
		$this->getOutput()->addInlineScript( 'jQuery("#qrcode").qrcode("otpauth://totp/' . $this->OATHUser->getAccount() . '?secret=' . $secret . '")' );

		$this->getOutput()->addHTML( $out );
		$this->getOutput()->addWikiMsg( 'openstackmanager-scratchtokens' );
		if ( $reset ) {
			$this->getOutput()->addHTML( $this->createResourceList( $this->OATHUser->getScratchTokensReset() ) );
		} else {
			$this->getOutput()->addHTML( $this->createResourceList( $this->OATHUser->getScratchTokens() ) );
		}
	}

	/**
	 * @return bool
	 */
	function validate() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'oathauth-enable' ) );
		$mode = $this->getRequest()->getVal( 'mode' );

		$info['token'] = array(
			'type' => 'text',
			'default' => '',
			'label-message' => 'oathauth-token',
			'name' => 'token',
		);
		$info['mode'] = array(
			'type' => 'hidden',
			'default' => $mode,
			'name' => 'mode',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$form = new HTMLForm( $info, 'oathauth-verify' );
		$form->setTitle( SpecialPage::getTitleFor( 'OATH' ) );
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function reset() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'oathauth-reset' ) );

		$info['token'] = array(
			'type' => 'text',
			'label-message' => 'oathauth-currenttoken',
			'name' => 'token',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'reset',
			'name' => 'action',
		);
		$form = new HTMLForm( $info, 'oathauth-reset' );
		$form->setTitle( SpecialPage::getTitleFor( 'OATH' ) );
		$form->setSubmitID( 'oauth-form-disablesubmit' );
		$form->setSubmitCallback( array( $this, 'tryResetSubmit' ) );
		$form->show();
		return true;
	}

	/**
	 * @return bool
	 */
	function disable() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'oathauth-disable' ) );

		$info['token'] = array(
			'type' => 'text',
			'label-message' => 'oathauth-token',
			'name' => 'token',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'disable',
			'name' => 'action',
		);
		$form = new HTMLForm( $info, 'oathauth-disable' );
		$form->setTitle( SpecialPage::getTitleFor( 'OATH' ) );
		$form->setSubmitID( 'oauth-form-disablesubmit' );
		$form->setSubmitCallback( array( $this, 'tryDisableSubmit' ) );
		$form->show();
		return true;
	}

	/**
	 * @return void
	 */
	function displayInfo() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'oathauth-displayoathinfo' ) );

		$resources = array();
		if ( $this->OATHUser->isEnabled() && $this->OATHUser->isValidated() ) {
			array_push( $resources, Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-disable' ), array(), array( 'action' => 'disable' ) ) );
			array_push( $resources, Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reset' ), array(), array( 'action' => 'reset' ) ) );
		} else {
			array_push( $resources, Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-enable' ), array(), array( 'action' => 'enable' ) ) );
		}
		$this->getOutput()->addHTML( $this->createResourceList( $resources ) );
	}

	/**
	 * @param $resources array
	 * @return string
	 */
	function createResourceList( $resources ) {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', array(), $resource );
		}
		return Html::rawElement( 'ul', array(), $resourceList );
	}

	/**
	 * @param $formData array
	 * @param $entryPoint string
	 * @return bool
	 */
	function tryValidateSubmit( $formData, $entryPoint = 'internal' ) {
		$mode = $formData['mode'];
		if ( $mode == "reset" ) {
			$reset = true;
		} else {
			$reset = false;
		}
		$verify = $this->OATHUser->verifyToken( $formData['token'], $reset );
		if ( $verify ) {
			if ( $reset ) {
				$result = $this->OATHUser->reset();
			} else {
				$result = $this->OATHUser->validate();
			}
		} else {
			$result = false;
		}
		if ( $result ) {
			$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
			$out = Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-backtodisplay' ) );
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';

			if ( $reset ) {
				$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reattemptreset' ), array(), array( 'action' => 'enable', 'mode' => 'reset' ) );
			} else {
				$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reattemptenable' ), array(), array( 'action' => 'enable' ) );
			}
		}
		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param $formData array
	 * @param $entryPoint string
	 * @return bool
	 */
	function tryDisableSubmit( $formData, $entryPoint = 'internal' ) {
		$result = $this->OATHUser->disable( $formData['token'] );
		if ( $result ) {
			$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
			$out = '<br />';
			$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-backtodisplay' ) );
			$this->getOutput()->addHTML( $out );
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtodisableoauth' );
			$out = '<br />';

			$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reattemptdisable' ), array( 'action' => 'disable' ) );
			$this->getOutput()->addHTML( $out );
		}
		return true;
	}

	/**
	 * @param $formData array
	 * @param $entryPoint string
	 * @return bool
	 */
	function tryResetSubmit( $formData, $entryPoint = 'internal' ) {
		$verify = $this->OATHUser->verifyToken( $formData['token'] );
		if ( !$verify ) {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';
			$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reattemptreset' ), array(), array( 'action' => 'reset' ) );
			$this->getOutput()->addHTML( $out );
			return true;
		}

		$this->getOutput()->addWikiMsg( 'oathauth-donotdeleteoldsecret' );
		$info['token'] = array(
			'type' => 'text',
			'default' => '',
			'label-message' => 'oathauth-newtoken',
			'name' => 'token',
		);
		$info['mode'] = array(
			'type' => 'hidden',
			'default' => 'reset',
			'name' => 'mode',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$myContext = new DerivativeContext( $this->getContext() );
		$myRequest = new DerivativeRequest( $this->getRequest(), array( 'action' => 'validate', 'mode' => 'reset', 'token' => '' ), false );
		$myContext->setRequest( $myRequest );
		$form = new HTMLForm( $info, $myContext );
		$form->setTitle( SpecialPage::getTitleFor( 'OATH' ) );
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		$result = $this->OATHUser->setReset();
		if ( $result ) {
			$this->displaySecret( true );
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtoresetoath' );
			$out = '<br />';
			$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'oathauth-reattemptreset' ), array(), array( 'action' => 'reset' ) );
			$this->getOutput()->addHTML( $out );
		}
		return true;
	}

}
