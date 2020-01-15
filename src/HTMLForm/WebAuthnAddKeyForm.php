<?php

namespace MediaWiki\Extension\WebAuthn\HTMLForm;

use ConfigException;
use FormatJson;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\HTMLField\AddKeyLayout;
use SpecialPage;

class WebAuthnAddKeyForm extends OATHAuthOOUIHTMLForm implements IManageForm {

	/**
	 * @var bool
	 */
	protected $panelPadded = false;

	/**
	 * @var bool
	 */
	protected $panelFramed = false;

	/**
	 * @inheritDoc
	 */
	public function __construct( OATHUser $oathUser, OATHUserRepository $oathRepo, IModule $module ) {
		parent::__construct( $oathUser, $oathRepo, $module );

		$this->setId( 'webauthn-add-key-form' );
		$this->suppressDefaultSubmit();
	}

	/**
	 * @param array|bool|\Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$this->getOutput()->addModules( 'ext.webauthn.register' );
		return parent::getHTML( $submitResult );
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$this->getOutput()->redirect(
			SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL()
		);
	}

	/**
	 * @param array $formData
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
			return true;
		} else {
			return [ $registrationResult->getMessage() ];
		}
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		return [
			'name-help' => [
				'type' => 'info',
				'default' => wfMessage( 'webauthn-ui-key-register-help' )->escaped(),
				'raw' => true,
				'section' => 'webauthn-add-key-section-name'
			],
			'name-layout' => [
				'type' => 'null',
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
