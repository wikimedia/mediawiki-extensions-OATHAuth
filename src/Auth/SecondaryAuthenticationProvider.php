<?php

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\MediaWikiServices;
use User;

class SecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {
	/**
	 * @param string $action
	 * @param array $options
	 *
	 * @return array
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/**
	 * @param User $user
	 * @param User $creator
	 * @param array|AuthenticationRequest[] $reqs
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
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
		$authUser = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' )
			->findByUser( $user );

		$module = $authUser->getModule();
		if ( $module === null ) {
			return AuthenticationResponse::newAbstain();
		}
		$moduleAuthProvider = $module->getSecondaryAuthProvider();
		return $moduleAuthProvider->beginSecondaryAuthentication( $user, $reqs );
	}

	/**
	 * Verify the second factor.
	 * @inheritDoc
	 */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		$authUser = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' )
			->findByUser( $user );

		$module = $authUser->getModule();
		$moduleAuthProvider = $module->getSecondaryAuthProvider();
		return $moduleAuthProvider->continueSecondaryAuthentication( $user, $reqs );
	}
}
