<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use OOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\OATHUser;
use Message;

class TOTPDisableForm extends OOUIHTMLForm implements IManageForm {
	/**
	 * @var OATHUser
	 */
	protected $oathUser;

	/**
	 * @var OATHUserRepository
	 */
	protected $oathRepo;

	/**
	 * @var IModule
	 */
	protected $module;

	/**
	 * Initialize the form
	 *
	 * @param OATHUser $oathUser
	 * @param OATHUserRepository $oathRepo
	 * @param IModule $module
	 */
	public function __construct( OATHUser $oathUser, OATHUserRepository $oathRepo, IModule $module ) {
		$this->oathUser = $oathUser;
		$this->oathRepo = $oathRepo;
		$this->module = $module;

		parent::__construct( $this->getDescriptors(), null, "oathauth" );
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
	}

	protected function getDescriptors() {
		return [
			'token' => [
				'type' => 'text',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
				'required' => true,
				'autofocus' => true,
				'dir' => 'ltr',
				'autocomplete' => false,
				'spellcheck' => false,
			]
		];
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 * @throws \MWException
	 */
	public function onSubmit( array $formData ) {
		// Don't increase pingLimiter, just check for limit exceeded.
		if ( $this->oathUser->getUser()->pingLimiter( 'badoath', 0 ) ) {
			// Arbitrary duration given here
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} rate limited while disabling 2FA from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-throttled', Message::durationParam( 60 ) ];
		}

		$key = $this->oathUser->getKey();
		if ( $key instanceof TOTPKey ) {
			if ( !$key->verify( $formData['token'], $this->oathUser ) ) {
				LoggerFactory::getInstance( 'authentication' )->info(
					'OATHAuth {user} failed to provide a correct token while disabling 2FA from {clientip}', [
						'user' => $this->getUser()->getName(),
						'clientip' => $this->getRequest()->getIP(),
					]
				);
				return [ 'oathauth-failedtovalidateoath' ];
			}
		}

		$this->oathUser->setKey( null );
		$this->oathRepo->remove( $this->oathUser, $this->getRequest()->getIP() );

		return true;
	}
}
