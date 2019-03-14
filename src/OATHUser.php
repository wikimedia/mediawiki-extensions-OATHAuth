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

namespace MediaWiki\Extension\OATHAuth;

use User;

/**
 * Class representing a user from OATH's perspective
 *
 * @ingroup Extensions
 */
class OATHUser {
	/** @var User */
	private $user;

	/** @var IAuthKey|null */
	private $key;

	/**
	 * @var IModule
	 */
	private $module;

	/**
	 * Constructor. Can't be called directly. Use OATHUserRepository::findByUser instead.
	 * @param User $user
	 * @param IAuthKey|null $key
	 */
	public function __construct( User $user, IAuthKey $key = null ) {
		$this->user = $user;
		$this->key = $key;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return String
	 */
	public function getIssuer() {
		global $wgSitename, $wgOATHAuthAccountPrefix;

		if ( $wgOATHAuthAccountPrefix !== false ) {
			return $wgOATHAuthAccountPrefix;
		}
		return $wgSitename;
	}

	/**
	 * @return String
	 */
	public function getAccount() {
		return $this->user->getName();
	}

	/**
	 * Get the key associated with this user.
	 *
	 * @return IAuthKey|null
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Set the key associated with this user.
	 *
	 * @param IAuthKey|null $key
	 */
	public function setKey( $key = null ) {
		$this->key = $key;
	}

	/**
	 * Gets the module instance associated with this user
	 *
	 * @return IModule
	 */
	public function getModule() {
		return $this->module;
	}

	/**
	 * Sets the module instance associated with this user
	 *
	 * @param IModule|null $module
	 */
	public function setModule( IModule $module = null ) {
		$this->module = $module;
	}

	/**
	 * Disables current (if any) auth method
	 */
	public function disable() {
		$this->key = null;
		$this->module = null;
	}
}
