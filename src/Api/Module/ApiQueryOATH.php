<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Api\Module;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to check if a user has OATH authentication enabled.
 *
 * Usage requires the 'oathauth-verify-user' grant.
 *
 * Use of this API is security-sensitive and should not be granted lightly.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryOATH extends ApiQueryBase {
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly OATHUserRepository $oathUserRepository,
	) {
		parent::__construct( $query, $moduleName, 'oath' );
	}

	public function execute() {
		// messages used: right-oathauth-verify-user, action-oathauth-verify-user
		$this->checkUserRightsAny( [ 'oathauth-verify-user' ] );

		$params = $this->extractRequestParams();

		if ( $params['user'] === null ) {
			$user = $this->getUser();
		} else {
			$user = MediaWikiServices::getInstance()->getUserFactory()
				->newFromName( $params['user'] );
			if ( $user === null ) {
				$this->dieWithError( 'noname' );
			}
		}

		$result = $this->getResult();
		$data = [
			ApiResult::META_BC_BOOLS => [ 'enabled' ],
			'enabled' => false,
		];

		if ( $user->isNamed() ) {
			$authUser = $this->oathUserRepository->findByUser( $user );
			$data['enabled'] = $authUser->isTwoFactorAuthEnabled();

			// messages used: logentry-oath-verify, log-action-oath-verify
			$logEntry = new ManualLogEntry( 'oath', 'verify' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $user->getUserPage() );
			$logEntry->setComment( $params['reason'] );
			$logEntry->insert();
		}
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/** @inheritDoc */
	public function getCacheMode( $params ) {
		return 'private';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=oath&reason=Test'
				=> 'apihelp-query+oath-example-1',
			'action=query&meta=oath&oathuser=Example&oathreason=Test'
				=> 'apihelp-query+oath-example-2',
		];
	}
}
