<?php

/**
 * Special page to display key information to the user
 *
 * @file
 * @ingroup Extensions
 */
class SpecialOATHDisable extends FormSpecialPage {
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
		parent::__construct( 'OATH', '', false );
		$this->OATHRepository = $repository;
		$this->OATHUser = $user;
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegend( false );
		$form->getOutput()->setPagetitle( $this->msg( 'oathauth-disable' ) );
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
		return array(
			'token' => array(
				'type' => 'text',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
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
		if ( !$this->OATHUser->getKey()->verifyToken( $formData['token'], $this->OATHUser ) ) {
			return array( 'oathauth-failedtovalidateoauth' );
		}

		$this->OATHUser->setKey( null );
		$this->OATHRepository->remove( $this->OATHUser );

		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
		$this->getOutput()->returnToMain();
	}
}
