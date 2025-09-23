<?php

namespace MediaWiki\Extension\WebAuthn\HTMLForm;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\HTMLField\AddKeyLayout;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\Json\FormatJson;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;

class WebAuthnAddKeyForm extends OATHAuthOOUIHTMLForm {

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
		$this->getOutput()->addModules( 'ext.webauthn.register' );
		return parent::getHTML( $submitResult );
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
