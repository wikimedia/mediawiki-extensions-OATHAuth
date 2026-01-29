<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth;

use Exception;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Context\IContextSource;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

/**
 * A helper class to facilitate writing to the logs. It primarily sends information to the 'oath' on-wiki log,
 * but it also records CheckUser data (if present) and ordinary (off-wiki) logs.
 */
class OATHAuthLogger {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly IContextSource $context,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Creates a new log entry to resemble an implicit verification of a user's 2FA enrollment,
	 * when checking if user is eligible for being a member of some groups.
	 */
	public function logImplicitVerification( UserIdentity $performer, UserIdentity $target ): void {
		$targetPage = PageReferenceValue::localReference( NS_USER, $target->getName() );
		$comment = Message::newFromKey( 'oathauth-verify-automatic-comment' )
			->inContentLanguage()
			->text();

		// messages used: logentry-oath-verify, log-action-oath-verify
		$logEntry = new ManualLogEntry( 'oath', 'verify' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $targetPage );
		$logEntry->setComment( $comment );
		$logId = $logEntry->insert();

		if ( $this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			/** @var CheckUserInsert $checkUserInsert */
			$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
			$checkUserInsert->updateCheckUserData( $logEntry->getRecentChange( $logId ) );
		}

		try {
			$request = $this->context->getRequest();
			$clientIP = $request->getIP();
		} catch ( Exception ) {
			// Let's log with unknown IP, it's not a serious condition, and it's better to have any
			// logs around 2FA than not
			$clientIP = 'unknown IP';
		}

		$this->logger->info(
			'OATHAuth status implicitly checked for {usertarget} by {user} from {clientip}', [
				'user' => $performer->getName(),
				'usertarget' => $target,
				'clientip' => $clientIP,
			]
		);
	}
}
