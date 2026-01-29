<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Logger\LoggerFactory;

/**
 * @property RecoveryCodes $module
 */
class RecoveryCodesStatusForm extends OATHAuthOOUIHTMLForm {
	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/** @inheritDoc */
	public function getHTML( $submitResult ) {
		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.oath.recovery.styles' );
		$out->addModules( 'ext.oath.recovery' );
		$out->setPageTitleMsg( $this->msg( 'oathauth-recoverycodes-header-create' ) );
		return parent::getHTML( $submitResult );
	}

	/** @inheritDoc */
	protected function getDescriptors() {
		if ( $this->oathUser->userHasNonSpecialEnabledKeys() ) {
			$submitMsg = $this->msg(
				'oathauth-recoverycodes-create-label',
				$this->getConfig()->get( 'OATHRecoveryCodesCount' )
			);
			$this->setSubmitTextMsg( $submitMsg );
			$this->setSubmitDestructive();
			$this->showCancel();
			$this->setCancelTarget( $this->getTitle() );
		} else {
			$this->suppressDefaultSubmit();
		}
		return [
			'warning' => [
				'type' => 'info',
				'default' => $this->msg( 'oathauth-recoverycodes-regenerate-warning',
					$this->getConfig()->get( 'OATHRecoveryCodesCount' ) )->parse(),
				'raw' => true,
			] ];
	}

	/**
	 * Add content to output when the operation was successful
	 */
	public function onSuccess() {
		$key = $this->module->ensureExistence( $this->oathUser );

		$recoveryCodes = $this->getRecoveryCodesForDisplay( $key );
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.oath.recovery.styles' );
		$output->addModules( 'ext.oath.recovery' );
		$output->addHtml(
			$this->generateRecoveryCodesContent( $recoveryCodes )
		);
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ) {
		$key = $this->module->ensureExistence( $this->oathUser );
		$key->regenerateRecoveryCodeKeys();
		$this->oathRepo->updateKey( $this->oathUser, $key );

		LoggerFactory::getInstance( 'authentication' )->info(
			"OATHAuth {user} generated new recovery codes from {clientip}", [
				'user' => $this->getUser()->getName(),
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}
}
