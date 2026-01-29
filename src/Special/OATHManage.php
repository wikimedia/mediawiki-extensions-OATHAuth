<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Special;

use ErrorPageError;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\OATHAuth\HTMLForm\DisableForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserGroupManager;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use Wikimedia\Codex\Utility\Codex;

/**
 * Initializes a page to manage available 2FA modules
 */
class OATHManage extends SpecialPage {
	use RecoveryCodesTrait;

	public const ACTION_ENABLE = 'enable';
	public const ACTION_DISABLE = 'disable';
	public const ACTION_DELETE = 'delete';

	protected OATHUser $oathUser;

	/**
	 * @var string
	 */
	protected $action;

	protected ?IModule $requestedModule;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly AuthManager $authManager,
		private readonly UserGroupManager $userGroupManager,
	) {
		// messages used: oathmanage (display "name" on Special:SpecialPages),
		// right-oathauth-enable, action-oathauth-enable
		parent::__construct( 'OATHManage', 'oathauth-enable' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'login';
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'accountsecurity' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->oathUser = $this->userRepo->findByUser( $this->getUser() );

		$this->getOutput()->enableOOUI();
		$this->getOutput()->disallowUserJs();
		$this->setAction();
		$this->setModule();

		parent::execute( $subPage );

		if ( $this->action === self::ACTION_DELETE ) {
			$this->showDeleteWarning();
			return;
		} elseif ( $this->requestedModule instanceof IModule ) {
			// Performing an action on a requested module
			$this->clearPage();
			$this->addModuleHTML( $this->requestedModule );
			return;
		}

		$this->displayNewUI();

		// recovery codes
		if ( $this->hasSpecialModules() ) {
			$this->addSpecialModulesHTML();
		}
	}

	/**
	 * @throws PermissionsError
	 * @throws UserNotLoggedIn
	 */
	public function checkPermissions() {
		$this->requireNamedUser();

		if ( !$this->oathUser->getCentralId() ) {
			throw new ErrorPageError(
				'oathauth-enable',
				'oathauth-must-be-central',
				[ $this->getUser()->getName() ]
			);
		}

		$canEnable = $this->getUser()->isAllowed( 'oathauth-enable' );

		if ( $this->action === static::ACTION_ENABLE && !$canEnable ) {
			$this->displayRestrictionError();
		}

		if ( !$canEnable && !$this->oathUser->isTwoFactorAuthEnabled() ) {
			// No enabled module and cannot enable - nothing to do
			$this->displayRestrictionError();
		}
	}

	private function setAction(): void {
		$this->action = $this->getRequest()->getVal( 'action', '' );
	}

	private function setModule(): void {
		$moduleKey = $this->getRequest()->getVal( 'module', '' );
		$this->requestedModule = ( $moduleKey && $this->moduleRegistry->moduleExists( $moduleKey ) )
			? $this->moduleRegistry->getModuleByKey( $moduleKey )
			: null;
	}

	/**
	 * Get the name, description, and timestamp to display for a given key.
	 * @param AuthKey $key
	 * @return array{name:string, description?:string, timestamp:?string}
	 */
	private function getKeyNameAndDescription( AuthKey $key ): array {
		$keyName = $key->getFriendlyName();
		$moduleName = $this->moduleRegistry->getModuleByKey( $key->getModule() )->getDisplayName()->text();
		$createdTimestamp = null;
		$timestamp = $key->getCreatedTimestamp();

		if ( $timestamp !== null ) {
			$createdTimestamp = $this->msg(
				'oathauth-created-at',
				Message::dateParam( $timestamp )
			)->text();
		}

		// Use the key if it has a non-empty name and set the description to the module name
		if ( $keyName !== null && trim( $keyName ) !== '' ) {
			return [
				'name' => $keyName,
				'description' => $moduleName,
				'timestamp' => $createdTimestamp
			];
		}

		// If the key has no name, use the module name as the name and send the timestamp
		return [
			'name' => $moduleName,
			'timestamp' => $createdTimestamp
		];
	}

	private function buildKeyAccordion( AuthKey $key ): string {
		$codex = new Codex();
		$keyData = $this->getKeyNameAndDescription( $key );
		$keyAccordion = $codex->accordion();

		$keyAccordion->setTitle( $keyData['name'] );

		$accordionDescription = $keyData['timestamp'] ?? $keyData['description'] ?? null;
		if ( $accordionDescription !== null ) {
			$keyAccordion->setDescription( $accordionDescription );
		}

		$keyAccordion
			->setContentHtml( $codex->htmlSnippet()->setContent(
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__method-actions'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'module', $key->getModule() ) .
					Html::hidden( 'keyId', $key->getId() ) .
					Html::hidden( 'warn', '1' ) .
					// TODO implement rename (T401775)
					$codex->button()
						->setLabel( $this->msg( 'oathauth-authenticator-delete' )->text() )
						->setAction( 'destructive' )
						->setWeight( 'primary' )
						->setType( 'submit' )
						->setAttributes( [ 'name' => 'action', 'value' => self::ACTION_DELETE ] )
						->build()
						->getHtml()
				)
			)->build() );
		return $keyAccordion->build()->getHtml();
	}

	private function displayNewUI(): void {
		$this->getOutput()->addModuleStyles( 'ext.oath.manage.styles' );
		$this->getOutput()->addModules( 'ext.oath.manage' );
		$codex = new Codex();

		// Show the delete success message, if applicable
		$deletedKeyName = $this->getRequest()->getVal( 'deletesuccess' );
		if ( $deletedKeyName !== null ) {
			$this->getOutput()->addHTML( Html::successBox(
				$this->msg( 'oathauth-delete-success', $deletedKeyName )->parse()
			) );
		}

		// Add the success message for newly enabled key
		$addedKeyName = $this->getRequest()->getVal( 'addsuccess' );
		if ( $addedKeyName !== null ) {
			$this->getOutput()->addHTML(
				Html::successBox(
					$this->msg( 'oathauth-enable-success', $addedKeyName )->parse()
				)
			);
		}

		// Password section
		if ( $this->authManager->allowsAuthenticationDataChange(
			new PasswordAuthenticationRequest(), false )->isGood()
		) {
			$this->getOutput()->addHTML(
				Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-password' ],
					Html::element( 'h3', [], $this->msg( 'oathauth-password-header' )->text() ) .
					Html::rawElement( 'form', [
							'action' => wfScript(),
							'class' => 'mw-special-OATHManage-password__form'
						],
						Html::hidden( 'title', self::getTitleFor( 'ChangePassword' )->getPrefixedDBkey() ) .
						Html::hidden( 'returnto', $this->getPageTitle()->getPrefixedDBkey() ) .
						Html::element( 'p',
							[ 'class' => 'mw-special-OATHManage-password__label' ],
							$this->msg( 'oathauth-password-label' )->text()
						) .
						$codex->button()
							->setLabel( $this->msg( 'oathauth-password-action' )->text() )
							->setType( 'submit' )
							->build()
							->getHtml()
					)
				)
			);
		}

		// 2FA section
		$keyAccordions = '';
		$keyPlaceholder = '';
		$authmethodsClasses = [
			'mw-special-OATHManage-authmethods'
		];
		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			if ( $key->supportsPasswordlessLogin() ) {
				// Keys that support passwordless login are displayed in the passkeys section instead
				continue;
			}

			$keyAccordions .= $this->buildKeyAccordion( $key );
		}
		if ( $keyAccordions === '' ) {
			// User has no keys, display the placeholder message instead
			$keyPlaceholder = Html::element( 'p',
				[ 'class' => 'mw-special-OATHManage-authmethods__placeholder' ],
				$this->msg( 'oathauth-authenticator-placeholder' )->text()
			);
			$authmethodsClasses[] = 'mw-special-OATHManage-authmethods--no-keys';
		}

		$moduleButtons = '';
		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			$labelMessage = $module->getAddKeyMessage();
			if ( !$labelMessage ) {
				continue;
			}
			$moduleButtons .= $codex
				->button()
				->setLabel( $labelMessage->text() )
				->setType( 'submit' )
				->setAttributes( [ 'name' => 'module', 'value' => $module->getName() ] )
				->build()
				->getHtml();
		}

		$authMethodsSection = Html::rawElement( 'div', [ 'class' => $authmethodsClasses ],
			Html::element( 'h3', [], $this->msg( 'oathauth-authenticator-header' )->text() ) .
			$keyAccordions .
			Html::rawElement( 'form', [
					'action' => wfScript(),
					'class' => 'mw-special-OATHManage-authmethods__addform'
				],
				Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
				Html::hidden( 'action', 'enable' ) .
				$keyPlaceholder .
				$moduleButtons
			)
		);

		// Passkeys section
		$passkeyAccordions = '';
		$passkeyPlaceholder = '';
		$passkeyClasses = [ 'mw-special-OATHManage-passkeys' ];
		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			if ( !$key->supportsPasswordlessLogin() ) {
				// Regular 2FA keys are displayed in the 2FA section below
				continue;
			}
			$passkeyAccordions .= $this->buildKeyAccordion( $key );
		}
		if ( $passkeyAccordions === '' ) {
			$passkeyPlaceholder = Html::element( 'p',
				[ 'class' => 'mw-special-OATHManage-passkeys__placeholder' ],
				$this->msg( 'oathauth-passkeys-placeholder' )->text()
			);
			// Display an additional message if the user can't add passkeys
			if ( $keyAccordions === '' ) {
				$passkeyPlaceholder .= Html::element( 'p',
					[ 'class' => 'mw-special-OATHManage-passkeys__placeholder' ],
					 $this->msg( 'oathauth-passkeys-no2fa' )->text()
				);
			}
			$passkeyClasses[] = 'mw-special-OATHManage-passkeys--no-keys';
		}
		// Only display the "Add passkey" button if the user can add passkeys
		$passkeyAddButton = $keyAccordions === '' ? '' : $codex->button()
			->setLabel( $this->msg( 'oathauth-passkeys-add' )->text() )
			->setAttributes( [ 'class' => 'mw-special-OATHManage-passkeys__addbutton' ] )
			->build()
			->getHtml();
		$passkeySection = Html::rawElement( 'div', [ 'class' => $passkeyClasses ],
			Html::element( 'h3', [], $this->msg( 'oathauth-passkeys-header' )->text() ) .
			$passkeyAccordions .
			Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-authmethods__addform' ],
				$passkeyPlaceholder .
				$passkeyAddButton
			)
		);
		if ( $passkeyAddButton ) {
			// TODO this should just be a dependency of ext.oath.manage, but it can't be because
			// OATHAuth can't depend on WebAuthn directly. This should be resolved by merging
			// the two extensions (T303495)
			$this->getOutput()->addModules( 'ext.webauthn.Registrator' );
		}

		// If 2FA is enabled then put passkeys first, otherwise put 2FA first
		if ( $keyAccordions === '' ) {
			$this->getOutput()->addHTML( $authMethodsSection . $passkeySection );
		} else {
			$this->getOutput()->addHTML( $passkeySection . $authMethodsSection );
		}
	}

	private function addModuleHTML( IModule $module ): void {
		if ( $this->isModuleRequested( $module ) ) {
			$this->addCustomContent( $module );
			return;
		}

		$panel = $this->getGenericContent( $module );
		if ( $this->isModuleEnabled( $module ) ) {
			$this->addCustomContent( $module, $panel );
		}

		$this->getOutput()->addHTML( (string)$panel );
	}

	/**
	 * Get the panel with generic content for a module
	 */
	private function getGenericContent( IModule $module ): PanelLayout {
		$modulePanel = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'padded' => true
		] );
		$headerLayout = new HorizontalLayout();

		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		if ( $this->shouldShowGenericButtons() ) {
			$enabled = $this->isModuleEnabled( $module );
			$urlParams = [
				'action' => $enabled ? static::ACTION_DISABLE : static::ACTION_ENABLE,
				'module' => $module->getName(),
			];
			$button = new ButtonWidget( [
				'label' => $this
					->msg( $enabled ? 'oathauth-disable-generic' : 'oathauth-enable-generic' )
					->text(),
				'href' => $this->getOutput()->getTitle()->getLocalURL( $urlParams )
			] );
			$headerLayout->addItems( [ $button ] );
		}
		$headerLayout->addItems( [ $label ] );

		$modulePanel->appendContent( $headerLayout );
		$modulePanel->appendContent( new HtmlSnippet(
			$module->getDescriptionMessage()->parseAsBlock()
		) );
		return $modulePanel;
	}

	/**
	 * Check max keys for a user and return true if max is exceeded
	 * @return bool
	 */
	private function exceedsKeyLimit(): bool {
		return count( $this->oathUser->getNonSpecialKeys() ) >= $this->getConfig()->get( 'OATHMaxKeysPerUser' );
	}

	private function addCustomContent( IModule $module, ?PanelLayout $panel = null ): void {
		if ( $this->action === self::ACTION_ENABLE && $this->exceedsKeyLimit() ) {
			throw new ErrorPageError(
				'oathauth-max-keys-exceeded',
				'oathauth-max-keys-exceeded-message',
				[ Message::numParam( $this->getConfig()->get( 'OATHMaxKeysPerUser' ) ) ]
			);
		}

		if ( $this->action === self::ACTION_DISABLE ) {
			$form = new DisableForm( $this->oathUser, $this->userRepo, $module, $this->getContext(),
				$this->moduleRegistry );
		} else {
			$form = $module->getManageForm(
				$this->action,
				$this->oathUser,
				$this->userRepo,
				$this->getContext(),
				$this->moduleRegistry
			);
		}
		if ( $form === null || !$this->isValidFormType( $form ) ) {
			return;
		}
		$form->setTitle( $this->getOutput()->getTitle() );
		$this->ensureRequiredFormFields( $form, $module );
		$form->setSubmitCallback( [ $form, 'onSubmit' ] );
		if ( $form->show( $panel ) ) {
			$form->onSuccess();

			// Only redirect for enabling a new key
			if ( $this->action === self::ACTION_ENABLE ) {
				$addedKeyName = $module->getDisplayName()->text();
				$this->getOutput()->redirect(
					$this->getPageTitle()->getLocalURL( [
						'addsuccess' => $addedKeyName
					] )
				);
				// Stop further rendering
				return;
			}
		}
	}

	private function shouldShowGenericButtons(): bool {
		return !$this->requestedModule instanceof IModule || !$this->isGenericAction();
	}

	private function isModuleRequested( ?IModule $module ): bool {
		return (
			$this->requestedModule instanceof IModule
			&& $module instanceof IModule
			&& $this->requestedModule->getName() === $module->getName()
		);
	}

	private function isModuleEnabled( IModule $module ): bool {
		return (bool)$this->oathUser->getKeysForModule( $module->getName() );
	}

	/**
	 * Verifies if the module can be enabled
	 */
	private function isModuleAvailable( IModule $module ): bool {
		return $module->getManageForm(
			static::ACTION_ENABLE,
			$this->oathUser,
			$this->userRepo,
			$this->getContext(),
			$this->moduleRegistry
		) !== null;
	}

	/**
	 * Verifies if the given form instance fulfills the required conditions
	 */
	private function isValidFormType( mixed $form ): bool {
		if ( !( $form instanceof HTMLForm ) ) {
			return false;
		}
		$implements = class_implements( $form );
		if ( !isset( $implements[IManageForm::class] ) ) {
			return false;
		}

		return true;
	}

	private function ensureRequiredFormFields( IManageForm $form, IModule $module ): void {
		if ( !$form->hasField( 'module' ) ) {
			$form->addHiddenField( 'module', $module->getName() );
		}
		if ( !$form->hasField( 'action' ) ) {
			$form->addHiddenField( 'action', $this->action );
		}
	}

	/**
	 * When performing an action on a module (like enable/disable),
	 * the page should contain only the form for that action.
	 */
	private function clearPage(): void {
		if ( $this->isGenericAction() ) {
			$displayName = $this->requestedModule->getDisplayName();
			$pageTitleMessage = $this->action === self::ACTION_DISABLE ?
				$this->msg( 'oathauth-disable-page-title', $displayName ) :
				$this->msg( 'oathauth-enable-page-title', $displayName );
			$this->getOutput()->setPageTitleMsg( $pageTitleMessage );
		}

		$this->getOutput()->clearHTML();
		$this->getOutput()->addBacklinkSubtitle( $this->getOutput()->getTitle() );
	}

	/**
	 * The enable and disable actions are generic, and all modules must
	 * implement them (except special modules) while all other actions are module-specific.
	 */
	private function isGenericAction(): bool {
		return in_array( $this->action, [ static::ACTION_ENABLE, static::ACTION_DISABLE ] );
	}

	/**
	 * Returns special modules, which do not follow the constraints of standard modules.
	 * @return IModule[]
	 */
	private function getSpecialModules(): array {
		$modules = [];
		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			if ( $this->isModuleAvailable( $module ) && $module->isSpecial() ) {
				$modules[] = $module;
			}
		}
		return $modules;
	}

	/**
	 * Checks local groups to see what groups a user is in
	 * If any of the local groups are required, then the user is privileged
	 */
	private function isPrivilegedUser(): bool {
		$requiredGroups = $this->getConfig()->get( 'OATHRequiredForGroups' );
		if ( count( $requiredGroups ) === 0 ) {
			return false;
		}
		$userGroups = $this->userGroupManager->getUserGroups( $this->oathUser->getUser() );
		$a = array_intersect( $userGroups, $requiredGroups );
		return count( $a ) > 0;
	}

	/**
	 * Show the delete key warning/confirmation form using HTMLForm.
	 */
	private function showDeleteWarning(): void {
		$keyId = $this->getRequest()->getInt( 'keyId' );
		$keyToDelete = $this->oathUser->getKeyById( $keyId );
		if ( !$keyToDelete ) {
			throw new ErrorPageError(
				'oathauth-disable',
				'oathauth-remove-nosuchkey'
			);
		}

		$keyName = $this->getKeyNameAndDescription( $keyToDelete )['name'];
		$remainingKeys = array_filter(
			$this->oathUser->getNonSpecialKeys(),
			static fn ( $key ) => $key->getId() !== $keyId && !$key->supportsPasswordlessLogin()
		);
		$lastKey = count( $remainingKeys ) === 0;

		$this->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-delete-warning-header', $keyName ) );
		$this->getOutput()->addModuleStyles( 'ext.oath.manage.styles' );

		$formDescriptor = [];
		$warningDescription = $this->msg( 'oathauth-delete-warning' )->parse();

		if ( $lastKey ) {
			$formDescriptor['warning'] = [
				'type' => 'info',
				'raw' => true,
				'default' => Html::warningBox( $this->msg( 'oathauth-delete-warning-final' )->parse() ),
			];
			if ( $this->isPrivilegedUser() ) {
				$warningDescription = $this->msg( 'oathauth-delete-warning-final-privileged-user' )->parse();
			}
		}

		$formDescriptor['warning-description'] = [
			'type' => 'info',
			'raw' => true,
			'default' => $warningDescription,
		];

		if ( $lastKey ) {
			$formDescriptor['remove-confirm-box'] = [
				'type' => 'text',
				'label-message' => 'oathauth-delete-confirm-box',
				'required' => true,
				'validation-callback' => function ( $value ) {
					$expectedText = $this->msg( 'oathauth-authenticator-delete-text' )->text();
					return $value !== $expectedText
						? $this->msg( 'oathauth-delete-wrong-confirm-message' )->text()
						: true;
				},
			];
		}

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setTitle( $this->getPageTitle() );

		$form->addHiddenField( 'action', self::ACTION_DELETE );
		$form->addHiddenField( 'module', $keyToDelete->getModule() );
		$form->addHiddenField( 'keyId', (string)$keyId );

		$form->setSubmitDestructive();
		$form->setSubmitTextMsg( 'oathauth-authenticator-delete' );
		$form->showCancel();
		$form->setCancelTarget( $this->getPageTitle() );
		$form->setWrapperLegend( false );

		$form->setSubmitCallback( function ( $formData ) use ( $keyToDelete, $keyName, $lastKey ) {
			$this->userRepo->removeKey(
				$this->oathUser,
				$keyToDelete,
				$this->getRequest()->getIP(),
				true
			);

			if ( $lastKey ) {
				$this->userRepo->removeAll(
				$this->oathUser,
				$this->getRequest()->getIP(),
				true
				);
			}

			$this->getOutput()->redirect( $this->getPageTitle()->getFullURL( [
				'deletesuccess' => $keyName
			] ) );

			return true;
		} );

		$this->getOutput()->addHTML( Html::openElement( 'div',
			[ 'class' => 'mw-special-OATHManage-delete-warning' ]
		) );

		$form->show();
		$this->getOutput()->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Adds HTML for all available special modules
	 */
	private function addSpecialModulesHTML(): void {
		if ( !$this->oathUser->getKeys() ) {
			return;
		}
		foreach ( $this->getSpecialModules() as $module ) {
			$this->addSpecialModuleHTML( $module );
		}
	}

	/**
	 * Adds special module HTML content
	 *
	 * Since special modules can vary in a number of ways from standard modules,
	 * there isn't much benefit to further abstracting/genericizing display logic
	 */
	private function addSpecialModuleHTML( IModule $module ): void {
		// only one special module type is currently supported
		if ( $module instanceof RecoveryCodes ) {
			$this->getRecoveryCodesHTML( $module );
		}
	}

	private function getRecoveryCodesHTML( RecoveryCodes $module ): void {
		$key = $module->ensureExistence( $this->oathUser );

		$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
		$this->getOutput()->addModules( 'ext.oath.recovery' );
		$codex = new Codex();
		$placeholderMessage = '';

		$this->setOutputJsConfigVars(
			array_map(
				[ $this, 'tokenFormatterFunction' ],
				$key->getRecoveryCodeKeys()
			)
		);

		// TODO: use outlined Accordions once these are available in Codex
		$keyAccordion = $codex->accordion()
			->setTitle( $module->getDisplayName()->text() );
		$keyAccordion->setDescription(
			$this->msg( 'oathauth-recoverycodes' )->text()
		);
		$keyAccordion
			->setContentHtml( $codex->htmlSnippet()->setContent(
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__method-actions'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'module', $key->getModule() ) .
					Html::hidden( 'keyId', $key->getId() ) .
					$this->createRecoveryCodesCopyButton() .
					$this->createRecoveryCodesDownloadLink(
						$key->getRecoveryCodeKeys()
					) .
					$codex->button()
						->setLabel( $this->msg(
							'oathauth-recoverycodes-create-label',
							$this->getConfig()->get( 'OATHRecoveryCodesCount' )
						) )
						->setType( 'submit' )
						->setAttributes( [ 'name' => 'action', 'value' => 'create-' . $module->getName() ] )
						->build()
						->getHtml()
				)
			)->build() );

		$authmethodsClasses = [
			'mw-special-OATHManage-authmethods'
		];
		if ( !$this->oathUser->getKeys() ) {
			$authmethodsClasses[] = 'mw-special-OATHManage-authmethods--no-keys';
		}

		$this->getOutput()->addHTML(
			Html::rawElement( 'div', [ 'class' => $authmethodsClasses ],
				Html::element( 'h3', [], $this->msg( 'oathauth-' . $module->getName() . '-header' )->text() ) .
				$keyAccordion->build()->getHTML() .
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__addform'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'action', 'enable' ) .
					$placeholderMessage
				)
			)
		);
	}

	private function hasSpecialModules(): bool {
		return $this->getSpecialModules() !== [];
	}
}
