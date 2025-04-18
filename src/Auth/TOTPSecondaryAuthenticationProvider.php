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

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;
use MediaWiki\User\User;

/**
 * AuthManager secondary authentication provider for TOTP second-factor authentication.
 *
 * After a successful primary authentication, requests a time-based one-time password
 * (typically generated by a mobile app such as Google Authenticator) from the user.
 *
 * @see AuthManager
 * @see https://en.wikipedia.org/wiki/Time-based_One-time_Password_Algorithm
 */
class TOTPSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {
	private TOTP $module;
	private OATHUserRepository $userRepository;

	public function __construct( TOTP $module, OATHUserRepository $userRepository ) {
		$this->module = $module;
		$this->userRepository = $userRepository;
	}

	/**
	 * @param string $action
	 * @param array $options
	 *
	 * @return array
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		// don't ask for anything initially, so the second factor is on a separate screen
		return [];
	}

	/**
	 * If the user has enabled two-factor authentication, request a second factor.
	 *
	 * @param User $user
	 * @param array $reqs
	 *
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$authUser = $this->userRepository->findByUser( $user );

		if ( !$this->module->isEnabled( $authUser ) ) {
			return AuthenticationResponse::newAbstain();
		}

		return AuthenticationResponse::newUI(
			[ new TOTPAuthenticationRequest() ],
			wfMessage( 'oathauth-auth-ui' ),
		);
	}

	/**
	 * Verify the second factor.
	 * @inheritDoc
	 */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		/** @var TOTPAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, TOTPAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newUI( [ new TOTPAuthenticationRequest() ],
				wfMessage( 'oathauth-login-failed' ), 'error' );
		}

		// Don't increase pingLimiter, just check for limit exceeded.
		if ( $user->pingLimiter( 'badoath', 0 ) ) {
			return AuthenticationResponse::newUI(
				[ new TOTPAuthenticationRequest() ],
				new Message(
					'oathauth-throttled',
					// Arbitrary duration given here
					[ Message::durationParam( 60 ) ]
				), 'error' );
		}

		$authUser = $this->userRepository->findByUser( $user );
		$token = $request->OATHToken;

		if ( $this->module->verify( $authUser, [ 'token' => $token ] ) ) {
			return AuthenticationResponse::newPass();
		}

		// Increase rate limit counter for failed request
		$user->pingLimiter( 'badoath' );

		$this->logger->info( 'OATHAuth user {user} failed OTP token/recovery code from {clientip}', [
			'user'     => $user->getName(),
			'clientip' => $user->getRequest()->getIP(),
		] );

		return AuthenticationResponse::newUI(
			[ new TOTPAuthenticationRequest() ],
			wfMessage( 'oathauth-login-failed' ),
			'error'
		);
	}

	/**
	 * @param User $user
	 * @param User $creator
	 * @param array $reqs
	 *
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
