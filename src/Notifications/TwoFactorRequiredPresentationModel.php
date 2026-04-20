<?php

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Notification that the user is required to enable 2FA on this wiki
 */
class TwoFactorRequiredPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-twofactor-required-primary' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$link = $this->msg( 'oathauth-notifications-twofactor-required-helplink' )->inContentLanguage();
		$title = Title::newFromText( $link->plain() );
		if ( !$title ) {
			// Invalid title, skip
			return [];
		}
		return [ [
			'url' => $title->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-twofactor-required-help' )->text(),
			'icon' => 'help',
		] ];
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'notification-header-oathauth-twofactor-required' );
		$msg->dateParams( $this->event->getExtraParam( 'dateRequired' ) );
		return $msg;
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$msg = $this->getMessageWithAgent( 'notification-body-oathauth-twofactor-required' );
		$msg->dateParams( $this->event->getExtraParam( 'dateRequired' ) );
		return $msg;
	}
}
