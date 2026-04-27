<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use ManualLogEntry;
use MediaWiki\CheckUser\Hooks as CheckUserHooks;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MWException;
use UserBlockedError;
use UserNotLoggedIn;

class VerifyOATHForUser extends FormSpecialPage {

	private const OATHAUTH_IS_ENABLED = 'enabled';
	private const OATHAUTH_NOT_ENABLED = 'disabled';
	private OATHUserRepository $userRepo;
	private UserFactory $userFactory;
	private CentralIdLookup $centralIdLookup;

	/** @var string */
	private $enabledStatus;

	/** @var string */
	private $targetUser;

	/**
	 * @param OATHUserRepository $userRepo
	 * @param UserFactory $userFactory
	 * @param CentralIdLookup $centralIdLookup
	 */
	public function __construct( $userRepo, $userFactory, $centralIdLookup ) {
		// messages used: verifyoathforuser (display "name" on Special:SpecialPages),
		// right-oathauth-verify-user, action-oathauth-verify-user
		parent::__construct( 'VerifyOATHForUser', 'oathauth-verify-user' );
		$this->userRepo = $userRepo;
		$this->userFactory = $userFactory;
		$this->centralIdLookup = $centralIdLookup;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/**
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-verify-for-user' ) );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return true;
	}

	/**
	 * @param User $user
	 * @throws UserBlockedError
	 * @throws UserNotLoggedIn
	 */
	protected function checkExecutePermissions( User $user ) {
		$this->requireNamedUser();

		parent::checkExecutePermissions( $user );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->getOutput()->disallowUserJs();
		parent::execute( $par );
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		return [
			'user' => [
				'type' => 'user',
				'default' => '',
				'label-message' => 'oathauth-enteruser',
				'name' => 'user',
				'required' => true,
			],
			'reason' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-enterverifyreason',
				'name' => 'reason',
				'required' => true,
			],
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ) {
		$this->targetUser = $formData['user'];
		$user = $this->userFactory->newFromName( $this->targetUser );
		// T424117, same as T393253 - Check the username is valid, but don't check if
		// it exists on the local wiki. Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $formData['user'] ) === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}

		if ( $this->getUser()->pingLimiter( 'verify-2fa' ) ) {
			return Status::newFatal( 'oathauth-throttled' );
		}

		$oathUser = $this->userRepo->findByUser( $user );

		$this->enabledStatus = $oathUser->isTwoFactorAuthEnabled()
			? self::OATHAUTH_IS_ENABLED
			: self::OATHAUTH_NOT_ENABLED;

		// messages used: logentry-oath-verify, log-action-oath-verify
		$logEntry = new ManualLogEntry( 'oath', 'verify' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $formData['reason'] );
		$logEntry->insert();

		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			CheckUserHooks::updateCheckUserData( $logEntry->getRecentChange() );
		}

		LoggerFactory::getInstance( 'authentication' )->info(
			'OATHAuth status checked for {usertarget} by {user} from {clientip}', [
				'user' => $this->getUser()->getName(),
				'usertarget' => $this->targetUser,
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}

	/**
	 * @throws MWException
	 */
	public function onSuccess() {
		switch ( $this->enabledStatus ) {
			case self::OATHAUTH_IS_ENABLED:
				$msg = 'oathauth-verify-enabled';
				break;
			case self::OATHAUTH_NOT_ENABLED:
				$msg = 'oathauth-verify-disabled';
				break;
			default:
				throw new MWException(
					'Verification was successful but status is unknown'
				);
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );
		$out->addWikiMsg( $msg, $this->targetUser );
	}

}
