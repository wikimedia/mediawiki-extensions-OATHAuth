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

use InvalidArgumentException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Config\ConfigException;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn as WebAuthnModule;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class provides an endpoint for all WebAuthn actions.
 */
class WebAuthn extends ApiBase {

	private const ACTION_GET_AUTH_INFO = 'getAuthInfo';
	private const ACTION_GET_REGISTER_INFO = 'getRegisterInfo';

	/**
	 * @throws ApiUsageException
	 */
	public function execute() {
		$func = $this->getParameter( 'func' );

		$this->checkPermissions( $func );
		$this->checkModule();

		switch ( $func ) {
			case self::ACTION_GET_REGISTER_INFO:
				$result = $this->getRegisterInfo();
				break;

			case self::ACTION_GET_AUTH_INFO:
				$result = $this->getAuthInfo();
				break;

			default:
				throw new InvalidArgumentException();
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'func' => [
				ParamValidator::PARAM_TYPE => array_keys( $this->getRegisteredFunctions() ),
				ParamValidator::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'getAuthInfo' => 'apihelp-webauthn-paramvalue-func-getauthinfo',
					'getRegisterInfo' => 'apihelp-webauthn-paramvalue-func-getregisterinfo',
				],
			],
		];
	}

	/**
	 * Array of all functions that are allowed to be called.
	 * Each key must have the appropriate configuration that
	 * defines user requirements for the action.
	 *
	 * @return array
	 */
	protected function getRegisteredFunctions() {
		return [
			static::ACTION_GET_AUTH_INFO => [
				'permissions' => [],
				'mustBeLoggedIn' => false,
			],
			static::ACTION_GET_REGISTER_INFO => [
				'permissions' => [ 'oathauth-enable' ],
				'mustBeLoggedIn' => true,
			],
		];
	}

	/**
	 * @param string $func
	 * @throws ApiUsageException
	 */
	protected function checkPermissions( string $func ): void {
		$registered = $this->getRegisteredFunctions();
		$functionConfig = $registered[$func];

		$mustBeLoggedIn = $functionConfig['mustBeLoggedIn'];
		if ( $mustBeLoggedIn === true ) {
			$user = $this->getUser();
			if ( !$user->isNamed() ) {
				$this->dieWithError( [ 'apierror-mustbeloggedin', $this->msg( 'action-oathauth-enable' ) ] );
			}
		}

		$funcPermissions = $functionConfig['permissions'];
		if ( $funcPermissions ) {
			$this->checkUserRightsAny( $funcPermissions );
		}
	}

	/**
	 * @throws ApiUsageException
	 */
	protected function checkModule() {
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = MediaWikiServices::getInstance()->getService( 'OATHAuthModuleRegistry' );
		$module = $moduleRegistry->getModuleByKey( 'webauthn' );
		if ( !( $module instanceof WebAuthnModule ) ) {
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
