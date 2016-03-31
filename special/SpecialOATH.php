<?php

/**
 * Proxy page that redirects to the proper OATH special page
 */
class SpecialOATH extends ProxySpecialPage {
	/**
	 * If the user already has OATH enabled, show them a page to disable
	 * If the user has OATH disabled, show them a page to enable
	 *
	 * @return SpecialOATHDisable|SpecialOATHEnable|SpecialOATHLogin|SpecialPage
	 */
	protected function getTargetPage() {
		$repo = OATHAuthHooks::getOATHUserRepository();

		/** @var array $sessionUser */
		$loginInfo = $this->getRequest()->getSessionData( 'oath_login' );

		/** @var SpecialOATHDisable|SpecialOATHEnable|SpecialOATHLogin|SpecialPage $page */
		$page = null;
		if ( $this->getUser()->isAnon() && $loginInfo !== null ) {
			// User is anonymous, so they are logging in
			$loginInfo = OATHAuthUtils::decryptSessionData(
				$loginInfo,
				$this->getRequest()->getSessionData( 'oath_uid' )
			);
			$page = new SpecialOATHLogin(
				$repo->findByUser( User::newFromName( $loginInfo['wpName'] ) ),
				new DerivativeRequest(
					$this->getRequest(),
					$loginInfo,
					$this->getRequest()->wasPosted()
				)
			);
		} else {
			$user = $repo->findByUser( $this->getUser() );

			if ( $user->getKey() === null ) {
				$page = new SpecialOATHEnable( $repo, $user );
			} else {
				$page = new SpecialOATHDisable( $repo, $user );
			}
		}

		return $page;
	}

	protected function getGroupName() {
		return 'oath';
	}
}
