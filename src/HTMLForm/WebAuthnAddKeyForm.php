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
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\HTMLField\AddKeyLayout;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\Json\FormatJson;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use UnexpectedValueException;

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
		IContextSource $context
	) {
		parent::__construct( $oathUser, $oathRepo, $module, $context );

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

		if ( $this->getConfig()->get( 'OATHAllowMultipleModules' ) ) {
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
			$html .= $this->generateRecoveryCodesContent( $recCodeKeysForContent, true );
		}

		return $html;
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
		$authenticator = Authenticator::factory( $this->getUser(), $this->getRequest() );
		$registrationResult = $authenticator->continueRegistration( $credential );
		if ( $registrationResult->isGood() ) {

			if ( $this->getConfig()->get( 'OATHAllowMultipleModules' ) ) {
				// handle new recovery codes
				$moduleDbKeys = $this->oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );

				// only create recovery code module entry if this is the first 2fa key a user is creating
				if ( count( $moduleDbKeys ) > RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
					throw new UnexpectedValueException(
						$this->msg( 'oathauth-recoverycodes-too-many-instances' )->escaped()
					);
				} elseif ( count( $moduleDbKeys ) < RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
					$keyData = $this->getKeyDataInSession( 'RecoveryCodeKeys' );
					$recCodeKeys = RecoveryCodeKeys::newFromArray( $keyData );
					$this->setKeyDataInSessionToNull( 'RecoveryCodeKeys' );
					$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
					$this->oathRepo->createKey(
						$this->oathUser,
						$moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME ),
						$recCodeKeys->jsonSerialize(),
						$this->getRequest()->getIP()
					);
				}
			}

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
			]
		];
	}
}
