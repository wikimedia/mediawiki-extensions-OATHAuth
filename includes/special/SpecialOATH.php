<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Special\OATHErrorPage;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\MediaWikiServices;

/**
 * Proxy page that redirects to the proper OATH special page
 */
class SpecialOATH extends ProxySpecialPage {
	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;

	/**
	 * @var OATHUser
	 */
	protected $authUser;

	/**
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function __construct() {
		parent::__construct();

		$this->userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$this->authUser = $this->userRepo->findByUser( $this->getUser() );
	}

	/**
	 * Get the currently enable module (if any) and let it
	 * handle it - return an appropriate SpecialPage
	 *
	 * @return SpecialPage
	 */
	protected function getTargetPage() {
		$module = $this->getModule();
		if ( $module === null ) {
			return new OATHErrorPage();
		}
		return $module->getTargetPage( $this->userRepo, $this->authUser );
	}

	/**
	 * @return IModule|null
	 */
	protected function getModule() {
		$module = $this->getModuleFromAuthUser();
		if ( $module instanceof IModule ) {
			return $module;
		}
		$module = $this->getModuleFromRequest();
		if ( $module instanceof IModule ) {
			return $module;
		}
		return null;
	}

	/**
	 * @return IModule|null
	 */
	protected function getModuleFromAuthUser() {
		return $this->authUser->getModule();
	}

	/**
	 * @return IModule|null
	 */
	protected function getModuleFromRequest() {
		$moduleKey = $this->getRequest()->getVal( 'module' );
		if ( $moduleKey ) {
			return MediaWikiServices::getInstance()->getService( 'OATHAuth' )->getModuleByKey( $moduleKey );
		}
	}

	protected function getGroupName() {
		return 'oath';
	}
}
