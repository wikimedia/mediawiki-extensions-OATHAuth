<?php

namespace MediaWiki\Extension\OATHAuth\Special;

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

class OATHManage extends SpecialPage {
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
	 * @param string|null $subPage
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();
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
		$canDisable = $this->getUser()->isAllowed( 'oathauth-enable' );

		if ( !$canEnable && !$canDisable ) {
			$this->displayRestrictionError();
		}

		$hasEnabled = $this->authUser->getModule() instanceof IModule;
		if ( $hasEnabled && !$canEnable ) {
			$this->displayRestrictionError();
		}
	}

	protected function addEnabledModule() {
		$module = $this->authUser->getModule();
		if ( $module !== null ) {
			$this->enabledModule = $module->getName();
			$this->addHeading(
				wfMessage( 'oathauth-ui-enabled-module' )
			);
			$this->addModule( $module, true );
		}
	}

	protected function addNotEnabledModules() {
		$this->addHeading( wfMessage( 'oathauth-ui-not-enabled-modules' ) );
		foreach ( $this->auth->getAllModules() as $key => $module ) {
			if ( $this->enabledModule && $key === $this->enabledModule ) {
				continue;
			}
			$this->addModule( $module, false );
		}
	}

	private function addHeading( Message $message ) {
		$this->getOutput()->addHTML( Html::element( 'h2', [], $message->text() ) );
	}

	private function addModule( IModule $module, $enabled ) {
		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		$button = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'OATH' )->getLinkURL( [
				'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText(),
				'module' => $module->getName()
			] ),
			'label' => $enabled ?
				wfMessage( 'oathauth-disable-generic' )->text() :
				wfMessage( 'oathauth-enable-generic' )->text()
		] );
		$control = new HorizontalLayout( [
			'items' => [
				$label,
				$button
			]
		] );
		$this->getOutput()->addHTML( (string)$control );
	}
}
