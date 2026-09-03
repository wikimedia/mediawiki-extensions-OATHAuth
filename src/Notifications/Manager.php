<?php
declare( strict_types=1 );
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Notification\Notification;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Notification\Types\WikiNotification;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;

/**
 * Manages logic for configuring and sending out notifications via the core NotificationService
 */
class Manager {

	/**
	 * Send a notification that a 2FA factor has been disabled
	 *
	 * @param OATHUser $oUser
	 * @param bool $self Whether they disabled it themselves
	 */
	public static function notifyDisabled( OATHUser $oUser, bool $self ): void {
		$user = $oUser->getUser();
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-disable
			new WikiNotification( 'oathauth-disable', SpecialPage::getTitleFor( 'Preferences' ), $user, [
				'self' => $self,
				'activeDevices' => count( $oUser->getNonSpecialKeys() ),
			] ),
			new RecipientSet( $user )
		);
	}

	/**
	 * Send a notification that 2FA (first factor) has been enabled
	 */
	public static function notifyEnabled( OATHUser $oUser ): void {
		$user = $oUser->getUser();
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-enable
			new WikiNotification( 'oathauth-enable', SpecialPage::getTitleFor( 'Preferences' ), $user, [
				'activeDevices' => count( $oUser->getNonSpecialKeys() ),
			] ),
			new RecipientSet( $user )
		);
	}

	/**
	 * Send a notification that the user's recovery codes have been regenerated
	 */
	public static function notifyRecoveryCodesRegenerated( OATHUser $oUser ): void {
		$user = $oUser->getUser();
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-recoverycodes-regenerated
			new WikiNotification(
				'oathauth-recoverycodes-regenerated',
				SpecialPage::getTitleFor( 'OATHManage' ),
				$user
			),
			new RecipientSet( $user )
		);
	}

	/**
	 * Send a notification that the user has $tokenCount recovery tokens left
	 */
	public static function notifyRecoveryTokensRemaining(
		OATHUser $oUser, int $tokenCount, int $generatedCount
	): void {
		$user = $oUser->getUser();
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-recoverycodes-count
			new OATHAuthAgentNotification( 'oathauth-recoverycodes-count', $user, [
				'codeCount' => $tokenCount,
				'generatedCount' => $generatedCount,
			] ),
			new RecipientSet( $user )
		);
	}

	/**
	 * Sends a notification that the user had additional temporary recovery tokens generated for them
	 */
	public static function notifyTemporaryRecoveryTokensGeneratedForUser(
		UserIdentity $targetUser,
		int $tokenCount
	): void {
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-recoverycodes-generated-for-user
			new Notification( 'oathauth-recoverycodes-generated-for-user', [
				'codeCount' => $tokenCount
			] ),
			new RecipientSet( $targetUser )
		);
	}

	/**
	 * Sends a notification that the user needs to enable 2FA on this wiki
	 */
	public static function notify2FARequiredForUser(
		UserIdentity $targetUser,
		string $date,
	): void {
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			// message used: notification-header-oathauth-twofactor-required
			new WikiNotification(
				'oathauth-twofactor-required',
				SpecialPage::getTitleFor( 'OATHManage' ),
				$targetUser,
				[ 'dateRequired' => $date ]
			),
			new RecipientSet( $targetUser )
		);
	}
}
