<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Enforce2FA;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\RestrictedUserGroupCheckerFactory;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

class Mandatory2FAChecker {

	public function __construct(
		private readonly UserRequirementsConditionCheckerWith2FAAssumption $userRequirementsChecker,
		private readonly RestrictedUserGroupCheckerFactory $restrictedUserGroupCheckerFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ExtensionRegistry $extensionRegistry
	) {
	}

	/**
	 * For a given user, returns a list of their groups that require 2FA. This function checks the requirement
	 * in the context of particular user, as some other group conditions may apply.
	 *
	 * Only explicit groups are checked.
	 * @param UserIdentity $user The user to check
	 * @return list<string> List of groups the user is a member of that require 2FA.
	 */
	public function getGroupsRequiring2FA( UserIdentity $user ): array {
		$ugm = $this->userGroupManagerFactory->getUserGroupManager( $user->getWikiId() );
		$checker = $this->restrictedUserGroupCheckerFactory->getRestrictedUserGroupChecker( $user->getWikiId() );

		$groups = $ugm->getUserGroups( $user );
		$groupsRequiring2FA = [];
		foreach ( $groups as $group ) {
			if ( !$checker->isGroupRestricted( $group ) ) {
				continue;
			}
			$restrictions = $checker->getGroupRestrictions( $group );
			if ( $this->isUserRequiredToHave2FAToMeetConditions( $user, $restrictions->getMemberConditions() ) ) {
				$groupsRequiring2FA[] = $group;
			}
		}
		return $groupsRequiring2FA;
	}

	/**
	 * For every wiki on the wiki farm, returns a list of groups the user is a member of that require 2FA.
	 * This function uses the group requirements as they are specified on the remote wiki.
	 *
	 * If CentralAuth is not installed, this will just return the result for the local wiki.
	 * @param UserIdentity $localUser
	 * @return array<string, list<string>> An associative array where the keys are wiki IDs and the values are groups.
	 *     A given key is present in the array only if the list of groups for that wiki is non-empty. Therefore,
	 *     it's safe to check for the resulting array being non-empty to determine if the user has to have 2FA enabled
	 *     on any wiki on the farm.
	 */
	public function getGroupsRequiring2FAAcrossWikiFarm( UserIdentity $localUser ): array {
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$userName = $localUser->getName();
			$centralUser = CentralAuthUser::getInstanceByName( $userName );
			$attachments = $centralUser->queryAttached();

			// Only wikis where the user has any groups are of interest to us
			$attachments = array_filter(
				$attachments,
				static fn ( $attachment ) => count( $attachment['groupMemberships'] ) > 0
			);
			$identities = [];
			foreach ( $attachments as $attachment ) {
				$wikiId = $attachment['wiki'];
				if ( WikiMap::isCurrentWikiId( $wikiId ) ) {
					$wikiId = UserIdentity::LOCAL;
				}
				$identities[] = UserIdentityValue::newRegistered( $attachment['id'], $userName, $wikiId );
			}
		} else {
			$identities = [ $localUser ];
		}

		$groupsRequiring2FA = [];
		foreach ( $identities as $identity ) {
			$groupsForWiki = $this->getGroupsRequiring2FA( $identity );
			if ( !$groupsForWiki ) {
				continue;
			}
			$wikiId = $identity->getWikiId();
			if ( $wikiId === UserIdentity::LOCAL ) {
				$wikiId = WikiMap::getCurrentWikiId();
			}
			$groupsRequiring2FA[$wikiId] = $groupsForWiki;
		}

		return $groupsRequiring2FA;
	}

	/**
	 * Checks whether the user is required to keep 2FA enabled to meet the given conditions.
	 * @param UserIdentity $user
	 * @param array $conditions The conditions for a group member, to check against the user.
	 * @return bool True when the conditions are met if the user has 2FA enabled and not met without 2FA,
	 *   false otherwise
	 */
	private function isUserRequiredToHave2FAToMeetConditions( UserIdentity $user, array $conditions ): bool {
		$conditionsUsed = $this->userRequirementsChecker->extractConditions( $conditions );
		if ( !in_array( APCOND_OATH_HAS2FA, $conditionsUsed ) ) {
			// If the condition doesn't even use 2FA state, it's not relevant for mandatory 2FA.
			return false;
		}

		$this->userRequirementsChecker->setAssumed2FAState( true );
		$resultWith2FA = $this->userRequirementsChecker->recursivelyCheckCondition( $conditions, $user );

		$this->userRequirementsChecker->setAssumed2FAState( false );
		$resultWithout2FA = $this->userRequirementsChecker->recursivelyCheckCondition( $conditions, $user );

		return $resultWith2FA && !$resultWithout2FA;
	}
}
