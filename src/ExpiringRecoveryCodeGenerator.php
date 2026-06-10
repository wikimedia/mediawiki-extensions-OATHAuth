<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Mail\MailAddress;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use OutOfRangeException;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat as TS;

class ExpiringRecoveryCodeGenerator {

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		private readonly Config $config,
		private readonly IEmailer $emailer,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHUserRepository $userRepo,
		private readonly UserFactory $userFactory,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		$this->codesCount = $config->get( 'OATHRecoveryCodesCount' );
		$this->additionalCodeValidityDays = $config->get( 'OATHAdditionalRecoveryCodesValidityDays' );
		$this->initialCodeValidityDays = $config->get( 'OATHInitialRecoveryCodesValidityDays' );
	}

	private ?User $targetUser = null;

	private int $codesCount;
	private readonly int $additionalCodeValidityDays;
	private readonly int $initialCodeValidityDays;

	/**
	 * Used to create temporary recovery codes for a user who already has 2FA setup,
	 * to enable them to get back into their account.
	 *
	 * They will need a confirmed email on their account
	 */
	public function attemptToGenerateRecoveryCodes(
		User $performer,
		string $username,
		string $email,
		?string $reason = null,
		bool $logToWiki = true,
	): Status {
		$status = $this->validateUser( $username );
		if ( !$status->isOK() ) {
			return $status;
		}

		// The special page requires the performer to submit twice if the target user has no email. We count such cases
		// as two attempts for rate limiting. Otherwise, the special page could be gamed into either unlimited
		// 2FA verificator for email-less users or unlimited 2FA recovery for email-less users.
		// We could also use tokens for ensuring that resubmitting the page is counted once, but let's do it only
		// if needed.
		// Given that the page is normally not used frequently, we defer to the system administrator to set
		// appropriate limits to account for that behavior.
		if ( $performer->pingLimiter( 'recover-2fa' ) ) {
			// @codeCoverageIgnoreStart
			return Status::newFatal( 'oathauth-throttled' );
			// @codeCoverageIgnoreEnd
		}

		$oathUser = $this->userRepo->findByUser( $this->targetUser );
		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			// TODO: This is kinda orphaned if wrappers check for 2FA...
			return Status::newFatal( 'oathauth-recover-fail-no-2fa' );
		}

		$userEmail = $this->getUserEmail( $this->targetUser );
		if ( $userEmail === null ) {
			// Attempt to use the one provided instead as the user doesn't have an email set/confirmed (if necessary)
			$userEmail = $email;
			if ( !Sanitizer::validateEmail( $userEmail ) ) {
				return Status::newFatal( 'oathauth-recover-fail-email-required' );
			}
		}

		$expiryTimestamp = ConvertibleTimestamp::convert(
			TS::MW,
			(int)ConvertibleTimestamp::now( TS::UNIX ) + $this->additionalCodeValidityDays * 86_400
		);

		$status = $this->generateRecoveryCodes(
			oathUser: $oathUser,
			data: [ 'expiry' => $expiryTimestamp ],
		);

		if ( !$status->isOK() ) {
			return $status;
		}

		Manager::notifyTemporaryRecoveryTokensGeneratedForUser( $this->targetUser, $this->codesCount );
		$this->oathLogger->logOATHRecovery(
			$performer,
			$this->targetUser,
			$reason ?? '',
			$this->codesCount,
			$logToWiki,
		);

		$recoveryCodes = $status->getValue();

		$userLanguage = $this->getUserLanguage( $this->targetUser );
		$now = ConvertibleTimestamp::now();

		$subject = wfMessage( 'oathauth-recover-email-title' )
			->inLanguage( $userLanguage )
			->params( count( $recoveryCodes ) );
		$body = wfMessage( 'oathauth-recover-email-text' )
			->inLanguage( $userLanguage )
			->params(
				$this->targetUser->getName(),
				count( $recoveryCodes ),
				implode( "\n", $status->getValue() ),
				$this->getSiteAdminContact( $this->targetUser, $userLanguage ),
			)
			->dateTimeParams( $now )
			->dateParams( $now )
			->timeParams( $now )
			->dateParams( $expiryTimestamp );

		return $this->sendEmailWithRecoveryCodes(
			emailAddress: $userEmail,
			subject: $subject,
			body: $body,
		);
	}

	private function generateRecoveryCodes(
		OATHUser $oathUser,
		array $data = [],
	): Status {
		/** @var RecoveryCodes $recoveryCodesModule */
		$recoveryCodesModule = $this->moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodes $recoveryCodesModule';

		$key = $recoveryCodesModule->ensureExistence( $oathUser );
		try {
			$newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys(
				$this->codesCount,
				$data
			);
		} catch ( OutOfRangeException ) {
			// If there's no room for that many recovery codes, first invalidate all existing temporary codes
			// (which are likely not needed, since user asked for recovery again)
			$key->removeTemporaryCodes();
			$newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys(
				$this->codesCount,
				$data,
				// Don't throw this time, error handling is done below
				true
			);
			$this->codesCount = count( $newRecoveryCodes );

			// If no codes can be generated, that's a problem with the configuration
			if ( $this->codesCount === 0 ) {
				return Status::newFatal( 'oathauth-recover-fail-max-codes-reached' );
			}
		}
		$this->userRepo->updateKey( $oathUser, $key );
		return Status::newGood( $newRecoveryCodes );
	}

	/**
	 * Creates special, initial 2FA codes for a user who doesn't have 2FA setup yet.
	 */
	public function attemptToCreateInitial2FACodes(
		User $performer,
		string $username,
		string $email,
		bool $sendEmail = false,
	): Status {
		$status = $this->validateUser( $username );
		if ( !$status->isOK() ) {
			return $status;
		}

		$oathUser = $this->userRepo->findByUser( $this->targetUser );

		$expiryTimestamp = ConvertibleTimestamp::convert(
			TS::MW,
			(int)ConvertibleTimestamp::now( TS::UNIX ) + $this->initialCodeValidityDays * 86_400
		);

		$status = $this->generateRecoveryCodes(
			oathUser: $oathUser,
			data: [
				'initial' => true,
				'expiry' => $expiryTimestamp,
			],
		);

		if ( !$status->isOK() ) {
			return $status;
		}

		$this->oathLogger->logInitialRecovery( $performer, $this->targetUser );

		// Some routes, such as new user creation (by a third party) will be sending their own emails,
		// so we don't need to email them here
		if ( !$sendEmail ) {
			return $status;
		}

		$userLanguage = $this->getUserLanguage( $this->targetUser );

		$recoveryCodes = $status->getValue();

		$subject = wfMessage( 'oathauth-recover-initial-email-title' )
			->inLanguage( $userLanguage );
		$body = wfMessage( 'oathauth-recover-initial-email-text' )
			->inLanguage( $userLanguage )
			->params(
				count( $recoveryCodes ),
				implode( "\n", $recoveryCodes ),
				Message::dateParam( $expiryTimestamp ),
				$this->getSiteAdminContact( $this->targetUser, $userLanguage ),
			);

		$userEmail = $this->getUserEmail( $this->targetUser );
		if ( $userEmail === null ) {
			// Attempt to use the one provided instead as the user doesn't have an email set/confirmed (if necessary)
			$userEmail = $email;
			if ( !Sanitizer::validateEmail( $userEmail ) ) {
				return Status::newFatal( 'oathauth-recover-fail-email-required' );
			}
		}

		return $this->sendEmailWithRecoveryCodes(
			emailAddress: $userEmail,
			subject: $subject,
			body: $body,
		);
	}

	private function validateUser( string $username ): Status {
		$user = $this->getUserByName( $username );
		if ( !$user ) {
			return Status::newFatal( 'oathauth-user-not-found' );
		}
		$this->targetUser = $user;
		return Status::newGood();
	}

	/**
	 * @return string|null Non empty string if the user has an email address, null otherwise.
	 *  If $wgEmailAuthentication = true, email confirmation is respected.
	 */
	public function getUserEmail( User $user ): ?string {
		$emailAuth = $this->config->get( 'EmailAuthentication' );

		// Prefer email from CentralAuth if loaded
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralUser = CentralAuthUser::getInstanceByName( $user->getName() );

			$email = $centralUser->getEmail();
			if (
				( !$emailAuth && $email !== '' ) ||
				$centralUser->getEmailAuthenticationTimestamp()
			) {
				return $email;
			}
		}

		$email = $user->getEmail();
		if (
			( !$emailAuth && $email !== '' ) ||
			$user->isEmailConfirmed()
		) {
			return $email;
		}

		return null;
	}

	public function getUserByName( string $username ): ?User {
		$user = $this->userFactory->newFromName( $username );
		// T393253 - Check the username is valid, but don't check if it exists on the local wiki.
		// Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $username ) === 0 ) {
			return null;
		}
		return $user;
	}

	private function sendEmailWithRecoveryCodes(
		string $emailAddress,
		Message $subject,
		Message $body,
	): Status {
		// PasswordSender is used across MediaWiki for different purposes, that's why we use it here as well.
		$passwordSender = $this->config->get( MainConfigNames::PasswordSender );
		$sender = new MailAddress(
			$passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text()
		);

		return Status::wrap(
			$this->emailer->send(
				new MailAddress( $emailAddress ),
				$sender,
				$subject->text(),
				$body->text(),
			)
		);
	}

	private function getUserLanguage( User $targetUser ): string {
		if ( $targetUser->isRegistered() ) {
			return $this->userOptionsLookup->getOption( $targetUser, 'language' );
		} else {
			return RequestContext::getMain()->getLanguage()->getCode();
		}
	}

	private function getSiteAdminContact( User $targetUser, string $userLanguage ): string {
		$siteAdminContact = trim(
			wfMessage( 'oathauth-recover-email-text-site-admin-contact' )
				->inContentLanguage()
				->text()
		);
		if ( $siteAdminContact ) {
			$siteAdminContact = '<' . $siteAdminContact . '>';
			return wfMessage( 'oathauth-recover-email-text-please-contact-with-address' )
				->inLanguage( $userLanguage )
				->params( $targetUser->getName(), $siteAdminContact )
				->text();
		}
		return wfMessage( 'oathauth-recover-email-text-please-contact' )
			->inLanguage( $userLanguage )
			->params( $targetUser->getName() )
			->text();
	}

	public function getCodesCount(): int {
		return $this->codesCount;
	}

	public function getTargetUser(): ?UserIdentity {
		return $this->targetUser;
	}
}
