<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLField\AddKeyLayout;
use MediaWiki\Extension\OATHAuth\HTMLField\NoJsInfoField;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;

class WebAuthnAddKeyForm extends OATHAuthOOUIHTMLForm {

	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/** @var bool */
	protected $panelPadded = false;

	/** @var bool */
	protected $panelFramed = false;

	/** @inheritDoc */
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

	/** @inheritDoc */
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

	/** @inheritDoc */
	public function onSuccess() {
		// Not used - redirect is handled client-side after API call
	}

	/**
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		// Registration is handled client-side via API (action=webauthn&func=register)
		return [ 'webauthn-javascript-required' ];
	}

	/** @inheritDoc */
	protected function getDescriptors() {
		return [
			'nojs' => [
				'class' => NoJsInfoField::class,
				'section' => 'oathauth-webauthn-add-key-section-name',
			],
			'name-layout' => [
				'label-message' => 'oathauth-webauthn-ui-key-register-help',
				'class' => AddKeyLayout::class,
				'raw' => true,
				'section' => 'oathauth-webauthn-add-key-section-name'
			],
		];
	}
}
