<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use UnexpectedValueException;

class Recover2FAForUser extends FormSpecialPage {

	private readonly int $codesCount;
	/** @var list<string>|null New recovery codes will be stored here after successful generation */
	private ?array $newRecoveryCodes = null;
	private ?UserIdentity $targetUser = null;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly UserFactory $userFactory,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly LinkRenderer $linkRenderer,
	) {
		// messages used: recover2faforuser (display "name" on Special:SpecialPages),
		// right-oathauth-recover-for-user, action-oathauth-recover-for-user
		parent::__construct( 'Recover2FAForUser', 'oathauth-recover-for-user' );

		$this->codesCount = $this->getConfig()->get( 'OATHRecoveryCodesCount' );
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

		$legendMsg = $this->msg( 'oathauth-recover-for-user-legend', $this->codesCount );
		$introMsg = $this->msg( 'oathauth-recover-intro' )
			->params( $this->codesCount )
			->numParams( $this->codesCount )
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
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ) {
		$user = $this->userFactory->newFromName( $formData['user'] );
		// T393253 - Check the username is valid, but don't check if it exists on the local wiki.
		// Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $formData['user'] ) === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}
		$this->targetUser = $user;

		$oathUser = $this->userRepo->findByUser( $user );

		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			return [ 'oathauth-recover-fail-no-2fa' ];
		}

		/** @var RecoveryCodes $recoveryCodesModule */
		$recoveryCodesModule = $this->moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodes $recoveryCodesModule';

		$key = $recoveryCodesModule->ensureExistence( $oathUser );
		$this->newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys( $this->codesCount );
		$this->userRepo->updateKey( $oathUser, $key );

		$this->oathLogger->logOATHRecovery( $this->getUser(), $user, $formData['reason'], $this->codesCount );

		return true;
	}

	public function onSuccess() {
		if ( $this->newRecoveryCodes === null ) {
			throw new UnexpectedValueException( 'New recovery codes have not been generated' );
		}

		$targetUserName = $this->targetUser->getName();
		$targetUserLink = $this->linkRenderer->makeUserLink( $this->targetUser, $this->getContext() );

		$codesListItems = '';
		foreach ( $this->newRecoveryCodes as $code ) {
			$codesListItems .= Html::rawElement( 'li', [], Html::element( 'code', [], $code ) );
		}
		$codesList = Html::rawElement( 'ul', [], $codesListItems );

		$successMsg = $this->msg( 'oathauth-recoveredoath' )
			->params( count( $this->newRecoveryCodes ), $targetUserName )
			->rawParams( $targetUserLink );
		$this->getOutput()->addWikiMsg( $successMsg );
		$this->getOutput()->addHTML( $codesList );
		$this->getOutput()->returnToMain();
	}
}
