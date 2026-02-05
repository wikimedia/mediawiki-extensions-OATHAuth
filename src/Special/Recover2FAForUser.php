<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Mail\MailAddress;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class Recover2FAForUser extends FormSpecialPage {

	private readonly int $codesCount;
	private ?UserIdentity $targetUser = null;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly UserFactory $userFactory,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly LinkRenderer $linkRenderer,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly IEmailer $emailer,
		private readonly UserOptionsLookup $userOptionsLookup,
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
		$user = $this->getUserByName( $this->getRequest()->getText( 'user' ) );
		$oathUser = $user ? $this->userRepo->findByUser( $user ) : null;
		$showEmailField = $user && $this->getUserEmail( $user ) === null && $oathUser?->isTwoFactorAuthEnabled();

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
		$user = $this->getUserByName( $formData['user'] );
		if ( !$user ) {
			return Status::newFatal( 'oathauth-user-not-found' );
		}
		$this->targetUser = $user;

		$oathUser = $this->userRepo->findByUser( $user );
		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			return Status::newFatal( 'oathauth-recover-fail-no-2fa' );
		}

		$userEmail = $this->getUserEmail( $user );
		if ( $userEmail === null ) {
			// Attempt to use the one provided in the form
			$userEmail = $formData['email'];
			if ( !Sanitizer::validateEmail( $userEmail ) ) {
				return Status::newFatal( 'oathauth-recover-fail-email-required' );
			}
		}

		/** @var RecoveryCodes $recoveryCodesModule */
		$recoveryCodesModule = $this->moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodes $recoveryCodesModule';

		$key = $recoveryCodesModule->ensureExistence( $oathUser );
		$newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys( $this->codesCount );
		$this->userRepo->updateKey( $oathUser, $key );

		$emailStatus = $this->sendEmailWithRecoveryCodes( $userEmail, $newRecoveryCodes, $user );
		if ( !$emailStatus->isOK() ) {
			return $emailStatus;
		}

		$this->oathLogger->logOATHRecovery( $this->getUser(), $user, $formData['reason'], $this->codesCount );
		return Status::newGood();
	}

	public function onSuccess() {
		$targetUserName = $this->targetUser->getName();
		$targetUserLink = $this->linkRenderer->makeUserLink( $this->targetUser, $this->getContext() );

		$successMsg = $this->msg( 'oathauth-recoveredoath' )
			->params( $this->codesCount, $targetUserName )
			->rawParams( $targetUserLink );
		$this->getOutput()->addWikiMsg( $successMsg );
		$this->getOutput()->returnToMain();
	}

	private function getUserEmail( User $user ): ?string {
		$email = $user->getEmail();
		if ( $email !== '' ) {
			return $email;
		}
		return null;
	}

	private function getUserByName( string $username ): ?User {
		$user = $this->userFactory->newFromName( $username );
		// T393253 - Check the username is valid, but don't check if it exists on the local wiki.
		// Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $username ) === 0 ) {
			return null;
		}
		return $user;
	}

	private function sendEmailWithRecoveryCodes(
		string $emailAddress,
		array $recoveryCodes,
		User $targetUser
	): Status {
		// PasswordSender is used across MediaWiki for different purposes, that's why we use it here as well.
		$passwordSender = $this->getConfig()->get( MainConfigNames::PasswordSender );
		$sender = new MailAddress(
			$passwordSender,
			$this->msg( 'emailsender' )->inContentLanguage()->text()
		);
		$to = new MailAddress( $emailAddress );

		$recoveryCodesText = implode( "\n", $recoveryCodes );

		if ( $targetUser->isRegistered() ) {
			$userLanguage = $this->userOptionsLookup->getOption( $targetUser, 'language' );
		} else {
			$userLanguage = $this->getContext()->getLanguage()->getCode();
		}

		$siteAdminContact = trim(
			$this->msg( 'oathauth-recover-email-text-site-admin-contact' )
				->inContentLanguage()
				->text()
		);
		if ( $siteAdminContact ) {
			$siteAdminContact = '<' . $siteAdminContact . '>';
			$contactAdminLine = $this->msg( 'oathauth-recover-email-text-please-contact-with-address' )
				->inLanguage( $userLanguage )
				->params( $targetUser->getName(), $siteAdminContact )
				->text();
		} else {
			$contactAdminLine = $this->msg( 'oathauth-recover-email-text-please-contact' )
				->inLanguage( $userLanguage )
				->params( $targetUser->getName() )
				->text();
		}

		$now = ConvertibleTimestamp::now();

		$subject = $this->msg( 'oathauth-recover-email-title' )
			->inLanguage( $userLanguage )
			->params( count( $recoveryCodes ) )
			->text();
		$body = $this->msg( 'oathauth-recover-email-text' )
			->inLanguage( $userLanguage )
			->params( $targetUser->getName(), count( $recoveryCodes ), $recoveryCodesText, $contactAdminLine )
			->dateTimeParams( $now )
			->dateParams( $now )
			->timeParams( $now )
			->text();

		return Status::wrap( $this->emailer->send( $to, $sender, $subject, $body ) );
	}
}
