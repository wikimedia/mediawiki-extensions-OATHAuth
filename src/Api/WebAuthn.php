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

namespace MediaWiki\Extension\WebAuthn\Api;

use ApiBase;
use ApiUsageException;
use ConfigException;
use FormatJson;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn as WebAuthnModule;
use MediaWiki\MediaWikiServices;
use MWException;

/**
 * This class provides an endpoint for all WebAuthn actions.
 */
class WebAuthn extends ApiBase {
	private const ACTION_GET_REGISTER_INFO = 'getRegisterInfo';
	private const ACTION_REGISTER = 'register';
	private const ACTION_GET_AUTH_INFO = 'getAuthInfo';
	private const ACTION_AUTHENTICATE = 'authenticate';

	/**
	 * @throws ApiUsageException
	 */
	public function execute() {
		$func = $this->getFunction();
		$this->verifyFunction( $func );

		$this->checkPermissions( $func );
		$data = $this->getData();
		$this->checkModule();

		$funcRes = call_user_func_array( [ $this, $func ], [ $data ] );

		$this->getResult()->addValue( null, $this->getModuleName(), $funcRes );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'func' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'data' => [
				ApiBase::PARAM_TYPE => 'string',
			]
		];
	}

	/**
	 * @return mixed
	 * @throws ApiUsageException
	 */
	protected function getFunction() {
		return $this->getParameter( 'func' );
	}

	/**
	 * @param string $func
	 * @return string
	 * @throws ApiUsageException
	 */
	protected function verifyFunction( $func ) {
		$registered = $this->getRegisteredFunctions();
		if ( !isset( $registered[$func] ) ) {
			$this->dieWithError( 'oathauth-apierror-func-value-not-registered' );
		}
		$config = $registered[$func];
		if ( isset( $config['mustBePosted'] ) && $config['mustBePosted'] === true ) {
			if ( !$this->getRequest()->wasPosted() ) {
				$this->dieWithError( 'apierror-mustbeposted' );
			}
		}
		return $func;
	}

	/**
	 * @return array
	 * @throws ApiUsageException
	 */
	protected function getData() {
		$params = $this->extractRequestParams();
		if ( isset( $params['data'] ) ) {
			$decoded = FormatJson::decode( $params['data'] );
			if ( $decoded !== null ) {
				return $decoded;
			}
		}
		return [];
	}

	/**
	 * Array of all functions that are allowed to be called
	 * Each key must have appropriate configuration that
	 * defines user requirements for the action
	 *
	 * @return array
	 */
	protected function getRegisteredFunctions() {
		return [
			static::ACTION_GET_REGISTER_INFO => [
				'permissions' => [ 'oathauth-enable' ],
				'mustBePosted' => false,
				'mustBeLoggedIn' => true
			],
			static::ACTION_REGISTER => [
				'permissions' => [ 'oathauth-enable' ],
				'mustBePosted' => false,
				'mustBeLoggedIn' => true
			],
			static::ACTION_GET_AUTH_INFO => [
				'permissions' => [],
				'mustBePosted' => false,
				'mustBeLoggedIn' => false
			],
			static::ACTION_AUTHENTICATE => [
				'permissions' => [],
				'mustBePosted' => true,
				'mustBeLoggedIn' => false
			]
		];
	}

	/**
	 * @param string $func
	 * @return array
	 */
	protected function getFunctionPermissions( $func ) {
		$registered = $this->getRegisteredFunctions();
		$functionConfig = $registered[$func];
		if ( isset( $functionConfig['permissions'] ) ) {
			return $functionConfig['permissions'];
		}
		return [];
	}

	/**
	 * @param string $func
	 * @throws ApiUsageException
	 */
	protected function checkPermissions( $func ) {
		$funcPermissions = $this->getFunctionPermissions( $func );
		if ( empty( $funcPermissions ) ) {
			return;
		}
		$mustBeLoggedIn = $funcPermissions['mustBeLoggedIn'] ?? false;
		if ( $mustBeLoggedIn === true ) {
			$user = $this->getUser();
			if ( !$user->isLoggedIn() ) {
				$this->dieWithError( 'apierror-mustbeloggedin' );
			}
		}
		$this->checkUserRightsAny( $funcPermissions );
	}

	/**
	 * @throws ApiUsageException
	 */
	protected function checkModule() {
		/** @var OATHAuth $oath */
		$oath = MediaWikiServices::getInstance()->getService( 'OATHAuth' );
		$module = $oath->getModuleByKey( 'webauthn' );
		if ( $module === null || !( $module instanceof WebAuthnModule ) ) {
			$this->dieWithError( 'apierror-webauthn-module-missing' );
		}
	}

	/**
	 * @return array
	 * @throws ApiUsageException
	 * @throws ConfigException
	 * @throws MWException
	 */
	protected function getAuthInfo() {
		$authenticator = Authenticator::factory( $this->getUser(), $this->getRequest() );
		$canAuthenticate = $authenticator->canAuthenticate();
		if ( !$canAuthenticate->isGood() ) {
			$this->dieWithError( $canAuthenticate->getMessage() );
		}
		$startAuthResult = $authenticator->startAuthentication();
		if ( $startAuthResult->isGood() ) {
			return [
				'auth_info' => $startAuthResult->getValue()['json']
			];
		}
		$this->dieWithError( $startAuthResult->getMessage() );
	}

	/**
	 * @return array
	 * @throws ApiUsageException
	 * @throws ConfigException
	 * @throws MWException
	 */
	protected function getRegisterInfo() {
		$authenticator = Authenticator::factory( $this->getUser(), $this->getRequest() );
		$canRegister = $authenticator->canRegister();
		if ( !$canRegister->isGood() ) {
			$this->dieWithError( $canRegister->getMessage() );
		}
		$startRegResult = $authenticator->startRegistration();
		if ( $startRegResult->isGood() ) {
			return [
				'register_info' => $startRegResult->getValue()['json']
			];
		}
		$this->dieWithError( $startRegResult->getMessage() );
	}
}
