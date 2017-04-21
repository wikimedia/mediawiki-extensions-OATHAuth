<?php

/**
 * Proxy page that redirects to the proper OATH special page
 */
class SpecialOATH extends ProxySpecialPage {
	/**
	 * If the user already has OATH enabled, show them a page to disable
	 * If the user has OATH disabled, show them a page to enable
	 *
	 * @return SpecialOATHDisable|SpecialOATHEnable
	 */
	protected function getTargetPage() {
		$repo = OATHAuthHooks::getOATHUserRepository();

		$user = $repo->findByUser( $this->getUser() );

		if ( $user->getKey() === null ) {
			return new SpecialOATHEnable( $repo, $user );
		} else {
			return new SpecialOATHDisable( $repo, $user );
		}
	}

	protected function getGroupName() {
		return 'oath';
	}
}
