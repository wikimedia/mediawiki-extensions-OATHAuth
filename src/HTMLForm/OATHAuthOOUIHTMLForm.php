<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use OOUIHTMLForm;
use Psr\Log\LoggerInterface;

abstract class OATHAuthOOUIHTMLForm extends OOUIHTMLForm implements IManageForm {
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
	 * @var LoggerInterface
	 */
	protected $logger;

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
		$this->logger = $this->getLogger();

		parent::__construct( $this->getDescriptors(), null, "oathauth" );
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		return [];
	}

	/**
	 * @return LoggerInterface
	 */
	private function getLogger() {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 */
	abstract public function onSubmit( array $formData );

	abstract public function onSuccess();
}
