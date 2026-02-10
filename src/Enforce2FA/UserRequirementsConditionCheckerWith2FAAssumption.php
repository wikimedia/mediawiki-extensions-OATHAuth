<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Enforce2FA;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserRequirementsConditionChecker;

class UserRequirementsConditionCheckerWith2FAAssumption extends UserRequirementsConditionChecker {

	/**
	 * @var bool|null Assumed 2FA state for the user. If null, uses the actual 2FA state. If true/false, treats
	 * the user as having 2FA enabled/disabled respectively.
	 */
	private ?bool $assumed2FAState = null;

	/** @inheritDoc */
	protected function checkCondition( array $cond, UserIdentity $user ): ?bool {
		if ( $cond[0] === APCOND_OATH_HAS2FA && $this->assumed2FAState !== null ) {
			return $this->assumed2FAState;
		}

		return parent::checkCondition( $cond, $user );
	}

	/**
	 * Set an assumed 2FA state for the user. If set, this will be used instead of the actual 2FA state when
	 * checking conditions.
	 *
	 * @param bool|null $state Assumed 2FA state, or null to use the actual 2FA state
	 */
	public function setAssumed2FAState( ?bool $state ): void {
		$this->assumed2FAState = $state;
	}
}
