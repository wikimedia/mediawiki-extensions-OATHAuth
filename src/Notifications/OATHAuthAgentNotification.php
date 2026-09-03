<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Notification\AgentAware;
use MediaWiki\Notification\Notification;
use MediaWiki\User\UserIdentity;

/**
 * A Notification about a change to a user's two-factor authentication setup, with no
 * associated page.
 */
class OATHAuthAgentNotification extends Notification implements AgentAware {

	public function __construct(
		string $type,
		private readonly UserIdentity $agent,
		array $extra,
	) {
		parent::__construct( $type, $extra );
	}

	public function getAgent(): UserIdentity {
		return $this->agent;
	}

}
