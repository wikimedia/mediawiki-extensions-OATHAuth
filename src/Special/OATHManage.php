<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use Message;
use Html;
use SpecialPage;
use OOUI\LabelWidget;
use HTMLForm;

class OATHManage extends SpecialPage {
	const ACTION_ENABLE = 'enable';
	const ACTION_DISABLE = 'disable';

	/**
	 * @var OATHAuth
	 */
	protected $auth;
	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;
	/**
	 * @var OATHUser
	 */
	protected $authUser;
	/**
	 * @var string
	 */
	protected $enabledModule;
	/**
	 * @var string
	 */
	protected $action;
	/**
	 * @var string
	 */
	protected $requestedModule;

	/**
	 * Initializes a page to manage available 2FA modules
	 *
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function __construct() {
		parent::__construct( 'OATHManage' );

		$services = MediaWikiServices::getInstance();
		$this->auth = $services->getService( 'OATHAuth' );
		$this->userRepo = $services->getService( 'OATHUserRepository' );
		$this->authUser = $this->userRepo->findByUser( $this->getUser() );
	}

	/**
	 * @param null|string $subPage
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();
		$this->setAction();
		$this->setModule();
		$this->addEnabledModule();
		$this->addNotEnabledModules();
	}

	/**
	 * @throws \PermissionsError
	 * @throws \UserNotLoggedIn
	 */
	public function checkPermissions() {
		$this->requireLogin();

		$canEnable = $this->getUser()->isAllowed( 'oathauth-enable' );

		if ( $this->action && $this->action === static::ACTION_ENABLE && !$canEnable ) {
			$this->displayRestrictionError();
		}

		$hasEnabled = $this->authUser->getModule() instanceof IModule;
		if ( !$hasEnabled && !$canEnable ) {
			// No enabled module and cannot enable - nothing to do
			$this->displayRestrictionError();
		}
	}

	private function setAction() {
		$this->action = $this->getRequest()->getVal( 'action', '' );
	}

	private function setModule() {
		$this->requestedModule = $this->getRequest()->getVal( 'module', '' );
	}

	protected function addEnabledModule() {
		$module = $this->authUser->getModule();
		if ( $module !== null ) {
			if ( !$this->requestedModule ) {
				$this->requestedModule = $module->getName();
			}
			$this->enabledModule = $module->getName();
			$this->addHeading(
				wfMessage( 'oathauth-ui-enabled-module' )
			);
			$this->addModule( $module, true );
		}
	}

	protected function addNotEnabledModules() {
		$headerAdded = false;
		foreach ( $this->auth->getAllModules() as $key => $module ) {
			if ( $this->enabledModule && $key === $this->enabledModule ) {
				continue;
			}
			if ( !$headerAdded ) {
				// To avoid adding header for an empty section
				$this->addHeading( wfMessage( 'oathauth-ui-not-enabled-modules' ) );
				$headerAdded = true;
			}
			$this->addModule( $module, false );
		}
	}

	private function addHeading( Message $message ) {
		$this->getOutput()->addHTML( Html::element( 'h2', [], $message->text() ) );
	}

	private function addModule( IModule $module, $enabled ) {
		$this->addModuleGeneric( $module, $enabled );
		if ( $this->requestedModule === $module->getName() ) {
			$this->addModuleCustomForm( $module, $enabled );
		}
	}

	/**
	 * Add module name and generic controls
	 *
	 * @param IModule $module
	 * @param boolean $enabled
	 */
	private function addModuleGeneric( IModule $module, $enabled ) {
		$layout = new HorizontalLayout();
		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		$layout->addItems( [ $label ] );

		if ( $this->requestedModule !== $module->getName() || !$this->isGenericAction() ) {
			// Add a generic action button
			$button = new ButtonWidget( [
				'label' => $enabled ?
					wfMessage( 'oathauth-disable-generic' )->text() :
					wfMessage( 'oathauth-enable-generic' )->text(),
				'href' => $this->getOutput()->getTitle()->getLocalURL( [
					'action' => $enabled ? static::ACTION_DISABLE : static::ACTION_ENABLE,
					'module' => $module->getName()
				] )
			] );
			$layout->addItems( [ $button ] );
		}
		$this->getOutput()->addHTML( (string)$layout );
	}

	/**
	 * @param IModule $module
	 * @param bool $enabled
	 */
	private function addModuleCustomForm( IModule $module, $enabled ) {
		$form = $module->getManageForm( $this->action, $this->authUser, $this->userRepo );
		if ( $form === null || !$this->isValidFormType( $form ) ) {
			return;
		}
		if ( $this->isGenericAction() ) {
			$this->setUpGenericAction( $module, $enabled );
		}
		$form->setTitle( $this->getOutput()->getTitle() );
		$this->ensureRequiredFormFields( $form, $module );
		$form->setSubmitCallback( [ $form, 'onSubmit' ] );
		if ( $form->show() ) {
			$form->onSuccess();
		}
	}

	/**
	 * Generic actions (disable||enable) should not be handled in-line,
	 * so we clear the content to display only the form
	 *
	 * @param IModule $module
	 * @param bool $enabled
	 */
	protected function setUpGenericAction( IModule $module, $enabled ) {
		$pageTitle = $enabled ?
			wfMessage( 'oathauth-disable-page-title', $module->getDisplayName() )->text() :
			wfMessage( 'oathauth-enable-page-title', $module->getDisplayName() )->text();

		$this->getOutput()->clearHTML();
		$this->getOutput()->setPageTitle( $pageTitle );
		$this->getOutput()->addBacklinkSubtitle( $this->getOutput()->getTitle() );
	}

	/**
	 * Checks if given form instance fulfills required conditions
	 *
	 * @param mixed $form
	 * @return bool
	 */
	protected function isValidFormType( $form ) {
		if ( !( $form instanceof HTMLForm ) ) {
			return false;
		}
		$implements = class_implements( $form );
		if ( !isset( $implements[IManageForm::class] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is the requested action a generic one or module-specific
	 *
	 * @return bool
	 */
	protected function isGenericAction() {
		return $this->action &&
			in_array( $this->action, [ static::ACTION_DISABLE, static::ACTION_ENABLE ] );
	}

	/**
	 * @param IManageForm &$form
	 * @param IModule $module
	 */
	protected function ensureRequiredFormFields( IManageForm &$form, IModule $module ) {
		if ( !$form->hasField( 'module' ) ) {
			$form->addHiddenField( 'module', $module->getName() );
		}
		if ( !$form->hasField( 'action' ) ) {
			$form->addHiddenField( 'action', $this->action );
		}
	}
}
