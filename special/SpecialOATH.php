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

		$this->OATHUser = OATHUser::newFromUser( $this->getUser() );
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
		} elseif ( $action == "validate" ) {
			$this->validate();
		} elseif ( $action == "reset" ) {
			$this->reset();
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
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$form = new HTMLForm(
			$info,
			$this->getContext(),
			'oathauth-verify'
		);
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		$this->displaySecret();

		return true;
	}

	/**
	 * @param $reset bool
	 */
	private function displaySecret( $reset = false ) {
		$this->getOutput()->addModules( 'ext.oathauth' );
		if ( $reset ) {
			$secret = $this->OATHUser->getSecretReset();
		} else {
			$secret = $this->OATHUser->getSecret();
		}
		$out = '<strong>' . $this->msg( 'oathauth-account' )->escaped() . '</strong> '
			. $this->OATHUser->getAccount() . '<br/>'
			. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong> '
			. $secret . '<br/>'
			. '<br/>'
			. '<div id="qrcode"></div>';

		$this->getOutput()->addHTML( ResourceLoader::makeInlineScript(
			'jQuery("#qrcode").qrcode("otpauth://totp/'
			. $this->OATHUser->getAccount()
			. '?secret=' . $secret . '")'
		) );

		$this->getOutput()->addHTML( $out );
		$this->getOutput()->addWikiMsg( 'openstackmanager-scratchtokens' );
		if ( $reset ) {
			$this->getOutput()->addHTML(
				$this->createResourceList( $this->OATHUser->getScratchTokensReset() ) );
		} else {
			$this->getOutput()->addHTML(
				$this->createResourceList( $this->OATHUser->getScratchTokens() ) );
		}
	}

	/**
	 * @return bool
	 */
	private function validate() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'oathauth-enable' ) );
		$mode = $this->getRequest()->getVal( 'mode' );
		$returnto = $this->getRequest()->getVal( 'returnto' );

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
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$form = new HTMLForm(
			$info,
			$this->getContext(),
			'oathauth-verify'
		);
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		return true;
	}

	/**
	 * @return bool
	 */
	private function reset() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'oathauth-reset' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$info['token'] = array(
			'type' => 'text',
			'label-message' => 'oathauth-currenttoken',
			'name' => 'token',
		);
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'reset',
			'name' => 'action',
		);
		$form = new HTMLForm(
			$info,
			$this->getContext(),
			'oathauth-reset'
		);
		$form->setSubmitID( 'oauth-form-disablesubmit' );
		$form->setSubmitCallback( array( $this, 'tryResetSubmit' ) );
		$form->show();
		return true;
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

		$out = '';
		if ( $result ) {
			$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
			if ( $formData['returnto'] ) {
				$out = '<br />';
				$title = Title::newFromText( $formData['returnto'] );
				$out .= Linker::link( $title, $this->msg( 'oathauth-backtopreferences' )->escaped() );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';

			if ( $reset ) {
				$out .= Linker::link(
					$this->getPageTitle(),
					$this->msg( 'oathauth-reattemptreset' )->escaped(),
					array(),
					array(
						'action' => 'enable',
						'mode' => 'reset',
						'returnto' => $formData['returnto']
					)
				);
			} else {
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
		}

		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param $formData array
	 * @return bool
	 */
	public function tryDisableSubmit( $formData ) {
		$verify = $this->OATHUser->verifyToken( $formData['token'] );
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

		$result = $this->OATHUser->disable();
		if ( $result ) {
			$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
			if ( $formData['returnto'] ) {
				$out = '<br />';
				$title = Title::newFromText( $formData['returnto'] );
				$out .= Linker::link( $title, $this->msg( 'oathauth-backtopreferences' )->escaped() );
				$this->getOutput()->addHTML( $out );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtodisableoauth' );
			$out = '<br />';

			$out .= Linker::link(
				$this->getPageTitle(),
				$this->msg( 'oathauth-reattemptdisable' )->escaped(),
				array(
					'action' => 'disable',
					'returnto' => $formData['returnto'],
				)
			);
			$this->getOutput()->addHTML( $out );
		}
		return true;
	}

	/**
	 * @param $formData array
	 * @return bool
	 */
	public function tryResetSubmit( $formData ) {
		$verify = $this->OATHUser->verifyToken( $formData['token'] );
		if ( !$verify ) {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtovalidateoauth' );
			$out = '<br />';
			$out .= Linker::link(
				$this->getPageTitle(),
				$this->msg( 'oathauth-reattemptreset' )->escaped(),
				array(),
				array(
					'action' => 'reset',
					'returnto' => $formData['returnto']
				)
			);

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
		$info['returnto'] = array(
			'type' => 'hidden',
			'default' => $formData['returnto'],
			'name' => 'returnto',
		);
		$info['action'] = array(
			'type' => 'hidden',
			'default' => 'validate',
			'name' => 'action',
		);
		$myContext = new DerivativeContext( $this->getContext() );
		$myRequest = new DerivativeRequest( $this->getRequest(),
			array(
				'action' => 'validate',
				'mode' => 'reset',
				'token' => '',
				'returnto' => $formData['returnto']
			), false );
		$myContext->setRequest( $myRequest );
		$form = new HTMLForm( $info, $myContext );
		$form->setSubmitID( 'oathauth-validate-submit' );
		$form->setSubmitCallback( array( $this, 'tryValidateSubmit' ) );
		$form->show();

		$result = $this->OATHUser->setReset();
		if ( $result ) {
			$this->displaySecret( true );
		} else {
			$this->getOutput()->addWikiMsg( 'oathauth-failedtoresetoath' );
			$out = '<br />';
			$out .= Linker::link(
				$this->getPageTitle(),
				$this->msg( 'oathauth-reattemptreset' )->escaped(),
				array(),
				array(
					'action' => 'reset',
					'returnto' => $formData['returnto']
				)
			);
			$this->getOutput()->addHTML( $out );
		}

		return true;
	}

	protected function getGroupName() {
		return 'oath';
	}
}
