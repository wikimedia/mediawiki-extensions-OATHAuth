<?php

namespace MediaWiki\Extension\WebAuthn\HTMLForm;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\HTMLField\AddKeyLayout;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\Json\FormatJson;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;

class WebAuthnAddKeyForm extends OATHAuthOOUIHTMLForm {

	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/** @var bool */
	protected $panelPadded = false;

	/** @var bool */
	protected $panelFramed = false;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		OATHUser $oathUser,
		OATHUserRepository $oathRepo,
		IModule $module,
		IContextSource $context,
		OATHAuthModuleRegistry $registry
	) {
		parent::__construct( $oathUser, $oathRepo, $module, $context, $registry );

		$this->setId( 'webauthn-add-key-form' );
		$this->suppressDefaultSubmit();
	}

	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$html = parent::getHTML( $submitResult );

		$this->getOutput()->addModules( 'ext.webauthn.register' );

		$moduleDbKeys = $this->oathUser->getKeysForModule( $this->module->getName() );

		$recCodeKeys = [];
		if ( array_key_exists( 0, $moduleDbKeys ) ) {
			$objRecoveryCodeKeys = array_shift( $moduleDbKeys );
			if ( $objRecoveryCodeKeys instanceof RecoveryCodeKeys ) {
				$recCodeKeys = $objRecoveryCodeKeys->getRecoveryCodeKeys();
			}
		}
		$recCodeKeysForDisplay = $this->setKeyDataInSession(
			'RecoveryCodeKeys',
			[ 'recoverycodekeys' => $recCodeKeys ]
		);
		$recCodeKeysForContent = $this->getRecoveryCodesForDisplay( $recCodeKeysForDisplay );
		$this->getOutput()->addModules( 'ext.oath.recovery' );
		$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
		$this->setOutputJsConfigVars( $recCodeKeysForContent );

		return $html . $this->generateRecoveryCodesContent( $recCodeKeysForContent, true );
	}

	/**
	 * Add content to output when the operation was successful
	 */
	public function onSuccess() {
		$this->getOutput()->redirect(
			SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL()
		);
	}

	/**
	 * @return array|bool
	 * @throws ConfigException
	 */
	public function onSubmit( array $formData ) {
		if ( !isset( $formData['credential'] ) ) {
			return [ 'oathauth-failedtovalidateoath' ];
		}
		$credential = $formData['credential'];
		$credential = FormatJson::decode( $credential );

		$authenticator = Authenticator::factory( $this->getUser(), $this->getRequest(), $formData['passkeyMode'] );
		$registrationResult = $authenticator->continueRegistration( $credential );
		if ( $registrationResult->isGood() ) {

			// Create recovery codes if needed, using the same codes that we displayed to the user
			$recoveryCodesModule = $this->moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );
			'@phan-var RecoveryCodes $recoveryCodesModule';
			$recoveryCodesModule->ensureExistence( $this->oathUser, $this->getKeyDataInSession( 'RecoveryCodeKeys' ) );

			$this->setKeyDataInSessionToNull( 'RecoveryCodeKeys' );

			return true;
		}

		return [ $registrationResult->getMessage() ];
	}

	/** @inheritDoc */
	protected function getDescriptors() {
		return [
			'nojs' => [
				'class' => NoJsInfoField::class,
				'section' => 'webauthn-add-key-section-name',
			],
			'name-layout' => [
				'label-message' => 'webauthn-ui-key-register-help',
				'class' => AddKeyLayout::class,
				'raw' => true,
				'section' => 'webauthn-add-key-section-name'
			],
			'credential' => [
				'name' => 'credential',
				'type' => 'hidden',
				'value' => ''
			],
			'passkeyMode' => [
				'name' => 'passkeyMode',
				'type' => 'hidden',
				'value' => ''
			]
		];
	}
}
