<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use DummySessionProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ElevatedSecurityAuthenticationRequest;
use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Auth\ReauthPrimaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\TOTPAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Auth\TwoFactorModuleSelectAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MainConfigNames;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Runs a real AuthManager with a password provider that sorts before
 * ReauthPrimaryAuthenticationProvider, then the OATHAuth secondary provider.
 *
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider
 * @covers \MediaWiki\Extension\OATHAuth\Auth\ReauthPrimaryAuthenticationProvider
 * @covers \MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesSecondaryAuthenticationProvider
 */
class ModuleSwitchSecondFactorTest extends MediaWikiIntegrationTestCase {

	private OATHUserRepository $repo;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::SessionProviders => [ [ 'class' => DummySessionProvider::class ] ],
			// CentralAuth, when loaded, must not change the provider list set below.
			'CentralAuthEnableSul3' => false,
			'OATHAuthEnforce2FAForAll' => false,
			'OATHPasswordlessLogin' => false,
			MainConfigNames::RateLimits => [],
			MainConfigNames::AuthManagerConfig => [
				'preauth' => [],
				'primaryauth' => [
					'LocalPassword' => [
						'class' => LocalPasswordPrimaryAuthenticationProvider::class,
						'services' => [ 'DBLoadBalancerFactory' ],
						'args' => [ [ 'authoritative' => true ] ],
						'sort' => 10,
					],
					'ReauthPrimaryAuthenticationProvider' => [
						'class' => ReauthPrimaryAuthenticationProvider::class,
						'services' => [ 'OATHAuth.UserRepository', 'OATHAuth.ModuleRegistry' ],
						'sort' => 40,
					],
				],
				'secondaryauth' => [
					'OATHSecondaryAuthenticationProvider' => [
						'class' => SecondaryAuthenticationProvider::class,
						'services' => [
							'OATHAuth.Logger', 'OATHAuth.ModuleRegistry', 'OATHAuth.UserRepository',
							'HookContainer', 'UserNameUtils',
						],
						'sort' => 50,
					],
				],
			],
		] );

		// Keep the 2FA keys in the test database, with the local user ID as the central ID.
		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )->willReturn( $this->getDb() );
		$dbProvider->method( 'getReplicaDatabase' )->willReturn( $this->getDb() );
		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'centralIdFromLocalUser' )->willReturnCallback( static fn ( $u ) => $u->getId() );
		$lookupFactory = $this->createMock( CentralIdLookupFactory::class );
		$lookupFactory->method( 'getLookup' )->willReturn( $lookup );
		$this->repo = new OATHUserRepository(
			$dbProvider,
			new EmptyBagOStuff(),
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$lookupFactory,
			new NullLogger(),
			$this->createMock( Mandatory2FAChecker::class ),
		);
		$this->setService( 'OATHAuth.UserRepository', $this->repo );
		// Hooks and log writers of other extensions are not under test.
		$this->clearHook( 'AuthManagerLoginAuthenticateAudit' );
		$services = $this->getServiceContainer();
		if ( $services->hasService( 'CheckUserInsert' ) ) {
			$this->setService( 'CheckUserInsert',
				$this->createMock( get_class( $services->getService( 'CheckUserInsert' ) ) ) );
		}
	}

	/**
	 * @return array{0:User,1:string,2:string} User with TOTP and recovery codes, password, recovery code
	 */
	private function makeUserWith2FA(): array {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$registry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$oathUser = $this->repo->findByUser( $user );
		$this->repo->createKey( $oathUser, $registry->getModuleByKey( TOTP::MODULE_NAME ),
			TOTPKey::newFromRandom()->jsonSerialize(), '127.0.0.1' );
		/** @var RecoveryCodes $recoveryModule */
		$recoveryModule = $registry->getModuleByKey( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodes $recoveryModule';
		/** @var RecoveryCodeKeys $recoveryKeys */
		$recoveryKeys = $recoveryModule->ensureExistence( $oathUser );
		'@phan-var RecoveryCodeKeys $recoveryKeys';
		return [ $user, $testUser->getPassword(), $recoveryKeys->getRecoveryCodeKeys()[0] ];
	}

	private function getAuthManager(): AuthManager {
		return $this->getServiceContainer()->getAuthManager();
	}

	private function setSessionUser( User $user ): void {
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
	}

	private function getSessionUserName(): string {
		return RequestContext::getMain()->getRequest()->getSession()->getUser()->getName();
	}

	private function newPasswordRequest( User $user, string $password ): PasswordAuthenticationRequest {
		$req = new PasswordAuthenticationRequest();
		$req->action = AuthManager::ACTION_LOGIN;
		$req->username = $user->getName();
		$req->password = $password;
		return $req;
	}

	/**
	 * Ask for the reauthentication form of a security level, as Special:UserLogin?force= and
	 * action=query&meta=authmanagerinfo&amireauthenticate= do.
	 * @return AuthenticationRequest[]
	 */
	private function requestReauthForm( string $level ): array {
		return $this->getAuthManager()->getAuthenticationRequests(
			AuthManager::ACTION_LOGIN, null, [ 'securityLevel' => $level ] );
	}

	private function newElevatedSecurityRequest( string $level ): ElevatedSecurityAuthenticationRequest {
		/** @var ElevatedSecurityAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$this->requestReauthForm( $level ), ElevatedSecurityAuthenticationRequest::class );
		$this->assertNotNull( $req );
		$req->loadFromSubmission( [
			'elevatedSecurityToken' => $req->getFieldInfo()['elevatedSecurityToken']['value'],
		] );
		return $req;
	}

	private function newSwitchRequest(
		AuthenticationResponse $response, string $newModule
	): TwoFactorModuleSelectAuthenticationRequest {
		/** @var TwoFactorModuleSelectAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$response->neededRequests, TwoFactorModuleSelectAuthenticationRequest::class );
		$this->assertNotNull( $req );
		$this->assertArrayHasKey( $newModule, $req->allowedModules );
		$req = clone $req;
		$req->newModule = $newModule;
		return $req;
	}

	public function testLoginWithModuleSwitchStillNeedsSecondFactor() {
		[ $user, $password ] = $this->makeUserWith2FA();
		$otherUser = $this->getMutableTestUser()->getUser();
		$this->setSessionUser( $otherUser );
		$authManager = $this->getAuthManager();

		$response = $authManager->beginAuthentication(
			[ $this->newPasswordRequest( $user, $password ) ], 'https://example.org/' );
		$this->assertSame( AuthenticationResponse::UI, $response->status );
		$switchRequest = $this->newSwitchRequest( $response, RecoveryCodes::MODULE_NAME );

		$this->requestReauthForm( 'ChangeEmail' );
		$response = $authManager->continueAuthentication( [ $switchRequest ] );

		$this->assertNotSame( AuthenticationResponse::PASS, $response->status );
		$this->assertSame( $otherUser->getName(), $this->getSessionUserName() );
	}

	public function testSwitchToModuleThatAbstainsFails() {
		[ $user, $password ] = $this->makeUserWith2FA();
		$otherUser = $this->getMutableTestUser()->getUser();
		$this->setSessionUser( $otherUser );
		$authManager = $this->getAuthManager();

		$response = $authManager->beginAuthentication(
			[ $this->newPasswordRequest( $user, $password ) ], 'https://example.org/' );
		$this->assertSame( AuthenticationResponse::UI, $response->status );
		$switchRequest = $this->newSwitchRequest( $response, RecoveryCodes::MODULE_NAME );

		// Force a restricted level, however it got there.
		$elevatedRequest = $this->newElevatedSecurityRequest( 'ChangeEmail' );
		$response = $authManager->continueAuthentication( [ $elevatedRequest, $switchRequest ] );

		$this->assertSame( AuthenticationResponse::FAIL, $response->status );
		$this->assertSame( 'oathauth-invalidrequest', $response->message->getKey() );
		$this->assertSame( $otherUser->getName(), $this->getSessionUserName() );
	}

	public function testReauthWithModuleSwitchStillNeedsSecondFactor() {
		[ $user, $password ] = $this->makeUserWith2FA();
		$this->setSessionUser( $user );
		$authManager = $this->getAuthManager();

		$response = $authManager->beginAuthentication(
			[ $this->newElevatedSecurityRequest( 'OATHManage' ), $this->newPasswordRequest( $user, $password ) ],
			'https://example.org/'
		);
		$this->assertSame( AuthenticationResponse::UI, $response->status );
		$switchRequest = $this->newSwitchRequest( $response, RecoveryCodes::MODULE_NAME );

		$this->requestReauthForm( 'ChangeEmail' );
		$response = $authManager->continueAuthentication( [ $switchRequest ] );

		$this->assertNotSame( AuthenticationResponse::PASS, $response->status );
		$this->assertNotSame( AuthManager::SEC_OK, $authManager->securitySensitiveOperationStatus( 'OATHManage' ) );
	}

	public function testLoginWithRecoveryCodeAfterModuleSwitch() {
		[ $user, $password, $recoveryCode ] = $this->makeUserWith2FA();
		$this->setSessionUser( $this->getMutableTestUser()->getUser() );
		$authManager = $this->getAuthManager();

		$response = $authManager->beginAuthentication(
			[ $this->newPasswordRequest( $user, $password ) ], 'https://example.org/' );
		$this->assertSame( AuthenticationResponse::UI, $response->status );

		$response = $authManager->continueAuthentication(
			[ $this->newSwitchRequest( $response, RecoveryCodes::MODULE_NAME ) ] );
		$this->assertSame( AuthenticationResponse::UI, $response->status );
		$this->assertNotNull( AuthenticationRequest::getRequestByClass(
			$response->neededRequests, RecoveryCodesAuthenticationRequest::class ) );

		$codeRequest = new RecoveryCodesAuthenticationRequest();
		$codeRequest->RecoveryCode = $recoveryCode;
		$selectRequest = AuthenticationRequest::getRequestByClass(
			$response->neededRequests, TwoFactorModuleSelectAuthenticationRequest::class );
		$response = $authManager->continueAuthentication( [ $codeRequest, $selectRequest ] );

		$this->assertSame( AuthenticationResponse::PASS, $response->status );
		$this->assertSame( $user->getName(), $this->getSessionUserName() );
	}

	public function testOATHManageReauthWithRecoveryCode() {
		[ $user, $password, $recoveryCode ] = $this->makeUserWith2FA();
		$this->setSessionUser( $user );
		$authManager = $this->getAuthManager();

		$response = $authManager->beginAuthentication(
			[ $this->newElevatedSecurityRequest( 'OATHManage' ), $this->newPasswordRequest( $user, $password ) ],
			'https://example.org/'
		);
		$this->assertSame( AuthenticationResponse::UI, $response->status );

		$codeRequest = new TOTPAuthenticationRequest();
		$codeRequest->OATHToken = $recoveryCode;
		$response = $authManager->continueAuthentication( [ $codeRequest ] );

		$this->assertSame( AuthenticationResponse::PASS, $response->status );
		$this->assertSame( AuthManager::SEC_OK, $authManager->securitySensitiveOperationStatus( 'OATHManage' ) );
	}
}
