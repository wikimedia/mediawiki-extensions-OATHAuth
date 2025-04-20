<?php

namespace MediaWiki\Extension\WebAuthn\HTMLForm;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\WebAuthn\Authenticator;
use MediaWiki\Extension\WebAuthn\HTMLField\NoJsInfoField;
use MediaWiki\Extension\WebAuthn\Module\WebAuthn;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;

class WebAuthnDisableForm extends OATHAuthOOUIHTMLForm {

	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;

	/**
	 * @var OATHUser
	 */
	protected $oathUser;

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

		$this->setId( 'disable-webauthn-form' );
		$this->suppressDefaultSubmit();
	}

	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		if ( $this->wasSubmitted() === false ) {
			$this->getOutput()->addModules( 'ext.webauthn.disable' );
			return parent::getHTML( $submitResult );
		}
		return '';
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
	 * @throws MWException
	 */
	public function onSubmit( array $formData ) {
		if ( !isset( $formData['credential'] ) ) {
			return [ 'oathauth-failedtovalidateoath' ];
		}

		if ( !$this->authenticate( $formData['credential'] ) ) {
			return [ 'oathauth-failedtovalidateoath' ];
		}
		return true;
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		return [
			'nojs' => [
				'class' => NoJsInfoField::class,
				'section' => 'webauthn-disable-section-name',
			],
			'info' => [
				'type' => 'info',
				'default' => wfMessage( 'webauthn-ui-disable-prompt' )->plain(),
				'section' => 'webauthn-disable-section-name'
			],
			'credential' => [
				'name' => 'credential',
				'type' => 'hidden'
			]
		];
	}

	/**
	 * @param string $credential
	 * @return bool
	 */
	private function authenticate( string $credential ): bool {
		$authenticator = Authenticator::factory( $this->getUser(), $this->getRequest() );
		if ( !$authenticator->isEnabled() ) {
			return false;
		}
		$authenticationResult = $authenticator->continueAuthentication( [
			'credential' => $credential
		] );
		if ( $authenticationResult->isGood() ) {
			$this->oathRepo->removeAllOfType( $this->oathUser, WebAuthn::MODULE_ID,
				$this->getRequest()->getIP(), true );
			return true;
		}
		return false;
	}
}
