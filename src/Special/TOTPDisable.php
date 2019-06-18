<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use FormSpecialPage;
use HTMLForm;
use Message;

/**
 * Special page to display key information to the user
 *
 * @ingroup Extensions
 */
class TOTPDisable extends FormSpecialPage {
	/** @var OATHUserRepository */
	private $OATHRepository;

	/** @var OATHUser */
	private $OATHUser;

	/**
	 * Initialize the OATH user based on the current local User object in the context
	 *
	 * @param OATHUserRepository $repository
	 * @param OATHUser $user
	 */
	public function __construct( OATHUserRepository $repository, OATHUser $user ) {
		parent::__construct( 'OATH', '', false );
		$this->OATHRepository = $repository;
		$this->OATHUser = $user;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegend( false );
		$form->getOutput()->setPageTitle( $this->msg( 'oathauth-disable' ) );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * Requires user to be logged in
	 *
	 * @param \User $user
	 * @throws \UserBlockedError
	 * @throws \UserNotLoggedIn
	 */
	protected function checkExecutePermissions( \User $user ) {
		parent::checkExecutePermissions( $user );

		$this->requireLogin();
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
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
			],
			'returnto' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returnto' ),
				'name' => 'returnto',
			],
			'returntoquery' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returntoquery' ),
				'name' => 'returntoquery',
			]
		];
	}

	/**
	 * @param array $formData
	 * @return array|bool|\Status|string
	 * @throws \MWException
	 */
	public function onSubmit( array $formData ) {
		// Don't increase pingLimiter, just check for limit exceeded.
		if ( $this->OATHUser->getUser()->pingLimiter( 'badoath', 0 ) ) {
			// Arbitrary duration given here
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} rate limited while disabling 2FA from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-throttled', Message::durationParam( 60 ) ];
		}

		$key = $this->OATHUser->getKey();
		if ( $key instanceof TOTPKey ) {
			if ( !$key->verify( $formData['token'], $this->OATHUser ) ) {
				LoggerFactory::getInstance( 'authentication' )->info(
					'OATHAuth {user} failed to provide a correct token while disabling 2FA from {clientip}', [
						'user' => $this->getUser()->getName(),
						'clientip' => $this->getRequest()->getIP(),
					]
				);
				return [ 'oathauth-failedtovalidateoath' ];
			}
		}

		$this->OATHUser->setKey( null );
		$this->OATHRepository->remove( $this->OATHUser, $this->getRequest()->getIP() );

		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
		$this->getOutput()->returnToMain();
	}
}
