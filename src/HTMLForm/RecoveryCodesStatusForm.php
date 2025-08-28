<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use UnexpectedValueException;

class RecoveryCodesStatusForm extends OATHAuthOOUIHTMLForm {
	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.oath.recovery.styles' );
		$out->addModules( 'ext.oath.recovery' );
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
		} else {
			$this->suppressDefaultSubmit();
		}
		return [];
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$moduleDbKeys = $this->oathUser->getKeysForModule( $this->module->getName() );

		if ( count( $moduleDbKeys ) > RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( $this->msg( 'oathauth-recoverycodes-too-many-instances' )->escaped() );
		}

		if ( array_key_exists( 0, $moduleDbKeys ) ) {
			$recoveryCodes = $this->getRecoveryCodesForDisplay( array_shift( $moduleDbKeys ) );
			$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
			$this->getOutput()->addModules( 'ext.oath.recovery' );
			$this->setOutputJsConfigVars( $recoveryCodes );
			$this->getOutput()->addHtml(
				$this->generateRecoveryCodesContent( $recoveryCodes )
			);
		}
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		// use an existing recovery code, if one exists for a user, to regenerate them
		$oldRecoveryCodeKey = false;
		$keys = $this->oathUser->getKeysForModule( $this->module->getName() );
		if ( $keys ) {
			$objRecCodeKeys = array_shift( $keys );
			// @phan-suppress-next-line PhanUndeclaredMethod
			$oldRecoveryCodeKeys = $objRecCodeKeys->getRecoveryCodeKeys();
			$oldRecoveryCodeKey = array_shift( $oldRecoveryCodeKeys );
		}

		RecoveryCodeKeys::maybeCreateOrUpdateRecoveryCodeKeys( $this->oathUser, $oldRecoveryCodeKey );

		LoggerFactory::getInstance( 'authentication' )->info(
			"OATHAuth {user} regenerated and viewed their recovery codes from {clientip}", [
				'user' => $this->getUser()->getName(),
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}
}
