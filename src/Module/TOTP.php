<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\Auth\TOTPSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPEnableForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;

class TOTP implements IModule {
	public const MODULE_NAME = "totp";

	/**
	 * @return TOTPKey[]
	 */
	public static function getTOTPKeys( OATHUser $user ): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $user->getKeysForModule( self::MODULE_NAME );
	}

	public function __construct(
		private readonly OATHUserRepository $userRepository,
	) {
	}

	/** @inheritDoc */
	public function getName() {
		return self::MODULE_NAME;
	}

	/** @inheritDoc */
	public function getDisplayName() {
		return wfMessage( 'oathauth-module-totp-label' );
	}

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function newKey( array $data ) {
		if ( !isset( $data['secret'] ) || !isset( $data['scratch_tokens'] ) ) {
			throw new MWException( 'oathauth-invalid-data-format' );
		}
		if ( is_string( $data['scratch_tokens' ] ) ) {
			$data['scratch_tokens'] = explode( ',', $data['scratch_tokens'] );
		}

		return TOTPKey::newFromArray( $data );
	}

	/**
	 * @return TOTPSecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider() {
		return new TOTPSecondaryAuthenticationProvider(
			$this,
			$this->userRepository
		);
	}

	/**
	 * @param OATHUser $user
	 * @param array $data
	 * @return bool
	 * @throws MWException
	 */
	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['token'] ) ) {
			return false;
		}

		foreach ( self::getTOTPKeys( $user ) as $key ) {
			if ( $key->verify( $data, $user ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this module currently enabled for the given user?
	 *
	 * @param OATHUser $user
	 * @return bool
	 */
	public function isEnabled( OATHUser $user ): bool {
		return (bool)self::getTOTPKeys( $user );
	}

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @param IContextSource $context
	 * @return IManageForm|null
	 */
	public function getManageForm(
		$action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context
	): ?IManageForm {
		$hasTOTPKey = $this->isEnabled( $user );
		$canEnable = !$hasTOTPKey || $context->getConfig()->get( 'OATHAllowMultipleModules' );
		if ( $action === OATHManage::ACTION_ENABLE && $canEnable ) {
			return new TOTPEnableForm( $user, $repo, $this, $context );
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescriptionMessage() {
		return wfMessage( 'oathauth-totp-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDisableWarningMessage() {
		return wfMessage( 'oathauth-totp-disable-warning' );
	}
}
