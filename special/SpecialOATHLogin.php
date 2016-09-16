<?php

/**
 * Special page to log users into two factor authentication
 */
class SpecialOATHLogin extends FormSpecialPage {
	/** @var OATHUser|null */
	private $OATHUser;

	/** @var LoginForm */
	private $loginForm;

	/**
	 * @var string|null The token submitted by the user
	 */
	private $token = null;

	/**
	 * Initialize the OATH user based on the current local User object in the context
	 *
	 * @param OATHUser $oathuser
	 * @param WebRequest $oldRequest
	 */
	public function __construct( OATHUser $oathuser, WebRequest $oldRequest ) {
		Hooks::register( 'AbortLogin', $this );
		parent::__construct( 'OATH', '', false );

		$this->OATHUser = $oathuser;
		$this->loginForm = new LoginForm( $oldRequest );
		$this->loginForm->setContext( $this->getContext() );
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegend( false );
		$form->getOutput()->setPageTitle( $this->msg( 'oathauth-login' ) );
	}

	/**
	 * @return string
	 */
	public function getDisplayFormat() {
		return 'vform';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		return [
			'token' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
				'required' => true
			],
			'returnto' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returnto' ),
				'name' => 'returnto',
			],
			'returntoquery' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returntoquery' ),
				'name' => 'returntoquery',
			]
		];
	}

	/**
	 * Stub function: the only purpose of this form is to add more data into
	 * the login form
	 *
	 * @param array $formData
	 *
	 * @return true
	 */
	public function onSubmit( array $formData ) {
		$this->getRequest()->setSessionData( 'oath_login', null );
		$this->getRequest()->setSessionData( 'oath_uid', null );
		$this->token = $formData['token'];

		return true;
	}

	public function onSuccess() {
		$this->loginForm->execute( $this->par );
	}

	/**
	 * @param User $user
	 * @param $password
	 * @param $abort
	 * @param $errorMsg
	 *
	 * @return bool
	 */
	public function onAbortLogin( User $user, $password, &$abort, &$errorMsg ) {
		$result = $this->OATHUser
			->getKey()
			->verifyToken( $this->getRequest()->getVal( 'token' ), $this->OATHUser );

		if ( $result ) {
			return true;
		} else {
			$abort = LoginForm::WRONG_PASS;

			return false;
		}
	}
}
