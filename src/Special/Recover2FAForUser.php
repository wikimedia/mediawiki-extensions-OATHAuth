<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\OATHAuth\ExpiringRecoveryCodeGenerator;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;

class Recover2FAForUser extends FormSpecialPage {

	public function __construct(
		private readonly ExpiringRecoveryCodeGenerator $generator,
		private readonly LinkRenderer $linkRenderer,
		private readonly OATHUserRepository $userRepo,
	) {
		// messages used: recover2faforuser (display "name" on Special:SpecialPages)
		parent::__construct( 'Recover2FAForUser' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		// messages used: right-oathauth-recover-for-user, action-oathauth-recover-for-user
		return 'oathauth-recover-for-user';
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );

		$codesCount = $this->generator->getCodesCount();
		$legendMsg = $this->msg( 'oathauth-recover-for-user-legend', $codesCount );
		$introMsg = $this->msg( 'oathauth-recover-intro' )
			->params( $codesCount )
			->numParams( $codesCount )
			->parse();
		$form->setWrapperLegendMsg( $legendMsg );
		$form->setPreHtml( $introMsg );
		$form->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-recover-for-user' ) );
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function checkExecutePermissions( User $user ) {
		$this->requireNamedUser();

		parent::checkExecutePermissions( $user );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->getOutput()->disallowUserJs();
		parent::execute( $par );
	}

	/** @inheritDoc */
	protected function getFormFields() {
		$user = $this->generator->getUserByName( $this->getRequest()->getText( 'user' ) );
		$showEmailField = false;
		if ( $user ) {
			$userEmail = $this->generator->getUserEmail( $user );

			$showEmailField = $userEmail === null
				&& $this->userRepo->findByUser( $user )?->isTwoFactorAuthEnabled();
		}

		return [
			'user' => [
				'type' => 'user',
				'default' => '',
				'label-message' => 'oathauth-enteruser',
				'name' => 'user',
				'required' => true,
				'excludetemp' => true,
			],
			'reason' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-enterrecoverreason',
				'name' => 'reason',
				'required' => true,
			],
			'email' => [
				'type' => $showEmailField ? 'email' : 'hidden',
				'default' => '',
				'label-message' => 'oathauth-enterrecoveremail',
				'help-message' => 'oathauth-enterrecoveremail-help',
				'name' => 'email',
				'required' => false,
			],
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ): Status {
		return $this->generator->attemptToGenerateRecoveryCodes(
			$this->getUser(),
			$formData['user'],
			$formData['email'],
			$formData['reason']
		);
	}

	public function onSuccess() {
		$targetUser = $this->generator->getTargetUser();
		$targetUserName = $targetUser->getName();
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable; already proven not-null: line above not fatalling
		$targetUserLink = $this->linkRenderer->makeUserLink( $targetUser, $this->getContext() );

		$successMsg = $this->msg( 'oathauth-recoveredoath' )
			->params( $this->generator->getCodesCount(), $targetUserName )
			->rawParams( $targetUserLink );
		$this->getOutput()->addWikiMsg( $successMsg );
		$this->getOutput()->returnToMain();
	}
}
