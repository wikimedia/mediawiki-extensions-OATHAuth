<?php
declare( strict_types=1 );
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Hook;

class RegistrationCallback {

	public static function onRegistration(): void {
		global $wgOATHAuthEnforce2FAForAll, $wgRestrictedGroups;

		// Following the pattern in TorBlock, this is a string
		define( 'APCOND_OATH_HAS2FA', 'oath.has_2fa' );

		if ( $wgOATHAuthEnforce2FAForAll ) {
			// If 2FA is enforced for all users, require 2FA for the 'user' group
			if ( isset( $wgRestrictedGroups['user']['memberConditions'] ) ) {
				$wgRestrictedGroups['user']['memberConditions'] = [
					'&',
					APCOND_OATH_HAS2FA,
					$wgRestrictedGroups['user']['memberConditions']
				];
			} else {
				$wgRestrictedGroups['user']['memberConditions'] = [ APCOND_OATH_HAS2FA ];
			}
		}
	}
}
