<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\MediaWikiServices;
use SpecialPage;

class OATHErrorPage extends SpecialPage {
	/**
	 * Initialize the error page in case requested module cannot be displayed
	 */
	public function __construct() {
		// This is restricted, since there is no point in telling
		// the user to enable a module on preferences page without this permission
		parent::__construct( 'OATH', 'oathauth-enable' );
	}

	/**
	 * @param null|string $subPage
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		// Could be extended to be a generic error page
		// - if need arises
		$this->displayNoModule();
	}

	/**
	 * @throws \PermissionsError
	 * @throws \UserNotLoggedIn
	 */
	public function checkPermissions() {
		parent::checkPermissions();

		$this->requireLogin();
	}

	protected function displayNoModule() {
		$this->getOutput()->addWikiMsg( 'oathauth-ui-error-page-no-module' );

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$target = SpecialPage::getTitleFor( 'OATHManage' );
		$link = $linkRenderer->makeKnownLink( $target, $target->getText() );
		$this->getOutput()->addHTML( $link );
	}
}
