<?php
declare( strict_types=1 );
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Message\Message;

/**
 * Represents the module switching UI. Will only be present when there is an alternative module to
 * switch to.
 * @author Taavi Väänänen
 */
class TwoFactorModuleSelectAuthenticationRequest extends AuthenticationRequest {

	/**
	 * The new module selected by the user (as in IModule::getName()). Initially null, returning
	 * a TwoFactorModuleSelectAuthenticationRequest with this field set means the 2FA check will
	 * start again with the new module (other returned requests will be ignored).
	 */
	public ?string $newModule = null;

	/**
	 * @param string $currentModule The module being rendered in the current UI (as in
	 *  {@link IModule::getName}).
	 * @param array<string,Message> $allowedModules The list of modules the user can switch to, in
	 *  an internal name => display name format (as in {@link IModule::getName} /
	 *  {@link IModule::getDisplayName}). Includes the current module.
	 */
	public function __construct(
		public string $currentModule = '',
		public array $allowedModules = [],
	) {
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		$availableModules = array_diff_key( $this->allowedModules, [ $this->currentModule => true ] );
		$availableModules = [ '' => wfMessage( 'oathauth-auth-switch-module-keep' ) ] + $availableModules;

		return [
			'newModule' => [
				'type' => 'select',
				'options' => $availableModules,
				'value' => '',
				'optional' => true,
				'label' => wfMessage( 'oathauth-auth-switch-module-label' ),
				'help' => wfMessage( 'oathauth-auth-switch-module-help' ),
			],
		];
	}

	/** @inheritDoc */
	public function getMetadata() {
		return [
			'currentModule' => $this->currentModule,
			'allowedModules' => array_keys( $this->allowedModules ),
			'moduleDescriptions' => array_map( static fn ( Message $msg ) => $msg->text(), $this->allowedModules ),
		];
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		// Be nice to API users and don't require them to submit an empty string when not switching.
		// The default implementation would result in this object getting discarded when the
		// newModule field is not present at all, but we still need it to preserve currentModule
		// (via the session) so we know which 2FA module to call continueAuthentication() one.
		$data += [ 'newModule' => '' ];
		$ret = parent::loadFromSubmission( $data );
		$this->newModule = $this->newModule ?: null;
		return $ret;
	}

}
