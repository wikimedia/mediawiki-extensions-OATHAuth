<?php

/**
 * Special page to display key information to the user
 *
 * @ingroup Extensions
 */
class SpecialOATHEnable extends FormSpecialPage {
	/** @var OATHUserRepository */
	private $OATHRepository;

	/** @var OATHUser */
	private $OATHUser;

	/**
	 * Initialize the OATH user based on the current local User object in the context
	 *
	 * @param OATHUserRepository $repository
	 * @param OATHUser $user
	 */
	public function __construct( OATHUserRepository $repository, OATHUser $user ) {
		parent::__construct( 'OATH', 'oathauth-enable', false );

		$this->OATHRepository = $repository;
		$this->OATHUser = $user;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegend( false );
		$form->getOutput()->setPagetitle( $this->msg( 'oathauth-enable' ) );
		$form->getOutput()->addModules( 'ext.oathauth' );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'vform';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * Require users to be logged in
	 *
	 * @param User $user
	 *
	 * @return bool|void
	 */
	protected function checkExecutePermissions( User $user ) {
		parent::checkExecutePermissions( $user );

		$this->requireLogin();
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );

		if ( $key === null ) {
			$key = OATHAuthKey::newFromRandom();
			$this->getRequest()->setSessionData( 'oathauth_key', $key );
		}

		$secret = $key->getSecret();

		$this->getOutput()->addHTML( ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.loader.using', array(
				array( 'ext.oathauth' ),
				new XmlJsCode(
					'function () {'
					. '$("#qrcode").qrcode("otpauth://totp/'
					. rawurlencode( $this->OATHUser->getAccount() )
					. '?secret=' . $secret . '");'
					. '}'
				)
			) )
		) );

		return array(
			'app' => array(
				'type' => 'info',
				'default' => $this->msg( 'oathauth-step1-test' )->escaped(),
				'raw' => true,
				'section' => 'step1',
			),
			'qrcode' => array(
				'type' => 'info',
				'default' => '<div id="qrcode"></div>',
				'raw' => true,
				'section' => 'step2',
			),
			'manual' => array(
				'type' => 'info',
				'label-message' => 'oathauth-step2alt',
				'default' =>
					'<strong>' . $this->msg( 'oathauth-account' )->escaped() . '</strong><br/>'
					. $this->OATHUser->getAccount() . '<br/><br/>'
					. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong><br/>'
					. $key->getSecret() . '<br/>',
				'raw' => true,
				'section' => 'step2',
			),
			'scratchtokens' => array(
				'type' => 'info',
				'default' =>
					$this->msg( 'openstackmanager-scratchtokens' )
					. $this->createResourceList( $key->getScratchTokens() ),
				'raw' => true,
				'section' => 'step3',
			),
			'token' => array(
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
				'section' => 'step4',
			),
			'returnto' => array(
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returnto' ),
				'name' => 'returnto',
			),
			'returntoquery' => array(
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returntoquery' ),
				'name' => 'returntoquery',
			)
		);
	}

	/**
	 * @param array $formData
	 *
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		/** @var OATHAuthKey $key */
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );

		if ( !$key->verifyToken( $formData['token'], $this->OATHUser ) ) {
			return array( 'oathauth-failedtovalidateoauth' );
		}

		$this->getRequest()->setSessionData( 'oathauth_key', null );
		$this->OATHUser->setKey( $key );
		$this->OATHRepository->persist( $this->OATHUser );

		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
		$this->getOutput()->returnToMain();
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
}
