<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Hook;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\ElevatedSecurityAuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\WebAuthnAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\HTMLField\NoJsInfoField;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Hook\ReadPrivateUserRequirementsConditionHook;
use MediaWiki\User\Hook\UserRequirementsConditionHook;
use MediaWiki\User\UserIdentity;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use Wikimedia\Message\ListParam;
use Wikimedia\Message\ListType;

class HookHandler implements
	AuthChangeFormFieldsHook,
	BeforePageDisplayHook,
	GetPreferencesHook,
	ReadPrivateUserRequirementsConditionHook,
	UserRequirementsConditionHook
{
	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly PermissionManager $permissionManager,
		private readonly Config $config,
	) {
	}

	/** @inheritDoc */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( isset( $fieldInfo['OATHToken'] ) ) {
			$formDescriptor['OATHToken'] += [
				'cssClass' => 'loginText',
				'id' => 'wpOATHToken',
				'size' => 20,
				'dir' => 'ltr',
				'autofocus' => true,
				'persistent' => false,
				'autocomplete' => 'one-time-code',
				'spellcheck' => false,
				'help-message' => 'oathauth-auth-token-help-ui',
			];
		}

		if ( isset( $fieldInfo['RecoveryCode'] ) ) {
			$formDescriptor['RecoveryCode'] += [
				'dir' => 'ltr',
				'autofocus' => true,
				'persistent' => false,
				'autocomplete' => 'off',
				'spellcheck' => false,
				'help-message' => 'oathauth-auth-recovery-code-help',
			];
		}

		if ( isset( $fieldInfo['newModule'] ) ) {
			// HACK: Hide the newModule <select>, but keep it in form, otherwise HTMLForm won't
			// understand the button weirdness below. There's no great way for us to inject CSS, so
			// abuse a CSS class from core that has display: none; on it.
			// TODO: Make this multi-button thing a real HTMLForm field (T404664)
			$formDescriptor['newModule']['cssclass'] = 'emptyPortlet';
			if ( isset( $formDescriptor['OATHToken'] ) ) {
				// Don't make the TOTP token field required. Otherwise, the "Switch to XYZ" submit
				// buttons can't be used without filling in this field
				$formDescriptor['OATHToken']['required'] = false;
			}
			// Check the weight of the form submit button to make sure other authentication
			// options are placed below it
			$loginButtonWeight = $formDescriptor['loginattempt']['weight'] ?? 100;

			$availableModules = $fieldInfo['newModule']['options'];
			// Remove the empty option for not switching first
			unset( $availableModules[''] );

			// Reorder 2FA types according to SecondaryAuthenticationProvider Module Priority
			$orderedModules = [];
			foreach ( SecondaryAuthenticationProvider::MODULE_PRIORITY as $moduleName ) {
				if ( isset( $availableModules[$moduleName] ) ) {
					$orderedModules[$moduleName] = $availableModules[$moduleName];
					unset( $availableModules[$moduleName] );
				}
			}
			// Append any remaining modules that weren’t in the priority list
			$availableModules = $orderedModules + $availableModules;

			$extraWeight = 1;
			foreach ( $availableModules as $moduleName => $ignored ) {
				// Add a switch button for each alternative module, all with name="newModule"
				// Whichever button is clicked will submit the form, with newModule set to its value
				$buttonMessage = $this->moduleRegistry->getModuleByKey( $moduleName )->getLoginSwitchButtonMessage();
				$formDescriptor["newModule_$moduleName"] = [
					'type' => 'submit',
					'name' => 'newModule',
					'default' => $moduleName,
					'buttonlabel' => $buttonMessage->text(),
					// Make sure these buttons appear after the loginattempt button
					'weight' => $loginButtonWeight + $extraWeight,
					'flags' => [],
				];
				$extraWeight++;
			}
		}

		$webauthnReq = AuthenticationRequest::getRequestByClass( $requests, WebAuthnAuthenticationRequest::class );
		// Display a message about needing JavaScript for WebAuthn, but don't display it if we're on
		// the initial login page (the WebAuthnAuthenticationRequest there is for passwordless login)
		if ( $webauthnReq && !isset( $fieldInfo['username'] ) ) {
			$formDescriptor['webauthn-nojs'] = [
				'class' => NoJsInfoField::class,
				'weight' => -50,
			];
		}

		$reauthReq = AuthenticationRequest::getRequestByClass( $requests,
			ElevatedSecurityAuthenticationRequest::class );
		if ( $reauthReq ) {
			// If this is a reauth and we're offering a 2FA method directly, remove the password field
			if ( $webauthnReq || isset( $fieldInfo['OATHToken'] ) ) {
				unset( $formDescriptor['password'] );
			}
			// Also remove the "Log in" button if there is a WebAuthn request: in that case the
			// passkey or security key button will be primary
			if ( $webauthnReq ) {
				unset( $formDescriptor['loginattempt'] );
			}
		}

		if ( $this->config->get( 'OATHPasswordlessLogin' ) ) {
			if ( isset( $fieldInfo['username'] ) && isset( $fieldInfo['credential'] ) ) {
				$formDescriptor['username']['autocomplete'] = 'username webauthn';

				// HACK autofocus the username even when it's prepopulated
				$formDescriptor['username']['autofocus'] = true;
				if ( isset( $formDescriptor['password']['autofocus'] ) ) {
					unset( $formDescriptor['password']['autofocus'] );
				}
			}

			if ( isset( $fieldInfo['passwordlessButton'] ) && !$reauthReq ) {
				// Make the "Log in with passkey" button a non-primary, non-submit button, make it
				// progressive, and put it below the login button
				$formDescriptor['passwordlessButton']['type'] = 'button';
				$formDescriptor['passwordlessButton']['flags'] = [ 'progressive' ];
				$formDescriptor['passwordlessButton']['weight'] = 110;
			}
		}

		return true;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$oathUser = $this->userRepo->findByUser( $user );

		// If there is no existing module for the user, and the user is not allowed to enable it,
		// we have nothing to show.
		if (
			!$oathUser->isTwoFactorAuthEnabled() &&
			!$this->permissionManager->userHasRight( $user, 'oathauth-enable' )
		) {
			return true;
		}

		$modules = array_unique( array_map(
			static fn ( AuthKey $key ) => $key->getModule(),
			$oathUser->getKeys(),
		) );
		$moduleNames = array_map(
			fn ( string $moduleId ) => $this->moduleRegistry
				->getModuleByKey( $moduleId )
				->getDisplayName(),
			$modules
		);

		if ( count( $moduleNames ) > 1 ) {
			$moduleLabel = wfMessage( 'rawmessage' )
				->params( new ListParam( ListType::AND, $moduleNames ) );
		} elseif ( $moduleNames ) {
			$moduleLabel = $moduleNames[0];
		} else {
			$moduleLabel = wfMessage( 'oathauth-ui-no-module' );
		}

		$manageButton = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => wfMessage( 'oathauth-ui-manage' )->text()
		] );

		$currentModuleLabel = new LabelWidget( [
			'label' => $moduleLabel->text(),
		] );

		$control = new HorizontalLayout( [
			'items' => [
				$currentModuleLabel,
				$manageButton
			]
		] );

		$preferences['oathauth-module'] = [
			'type' => 'info',
			'raw' => true,
			'default' => (string)$control,
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/accountsecurity',
		];

		return true;
	}

	/**
	 * Callback that generates the contents of the virtual data.json file in the ext.oath.manage
	 * ResourceLoader module.
	 */
	public static function getOathManageModuleData( Context $context ): array {
		return [
			'passkeyDialogTextHtml' => $context->msg( 'oathauth-passkey-dialog-text' )->parseAsBlock()
		];
	}

	/** @inheritDoc */
	public function onUserRequirementsCondition(
		string|int $type,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest,
		?bool &$result
	): void {
		if ( $type !== APCOND_OATH_HAS2FA ) {
			return;
		}
		$result = $this->userRepo->userHas2FAEnabled( $user );
	}

	/** @inheritDoc */
	public function onReadPrivateUserRequirementsCondition(
		UserIdentity $performer,
		UserIdentity $target,
		array $conditions
	): void {
		if ( in_array( APCOND_OATH_HAS2FA, $conditions ) ) {
			$this->oathLogger->logImplicitVerification( $performer, $target );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$this->config->get( 'OATHPasswordlessLogin' ) &&
			$out->getTitle()->isSpecial( 'Userlogin' )
		) {
			$out->addModules( 'ext.webauthn.passwordlessLogin' );
			$out->addModuleStyles( 'ext.webauthn.passwordlessLogin.styles' );
		}
	}
}
