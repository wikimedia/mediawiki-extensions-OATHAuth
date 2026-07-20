<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ElevatedSecurityAuthenticationRequest;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\OATHAuth\Auth\ReauthPrimaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\TwoFactorModuleSelectAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\Session\Token;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Auth\ReauthPrimaryAuthenticationProvider
 */
class ReauthPrimaryAuthenticationProviderTest extends MediaWikiIntegrationTestCase {

	private User $user;
	private SecondaryAuthenticationProvider&MockObject $secondaryProvider;
	private AuthManager&MockObject $authManager;

	/**
	 * @param string[] $enabledModules
	 * @param string[] $passwordlessModules Modules in $enabledModules whose key supports
	 *   passwordless login
	 * @param bool $passwordlessLogin Value of $wgOATHPasswordlessLogin
	 */
	private function getProvider(
		array $enabledModules,
		array $passwordlessModules = [],
		bool $passwordlessLogin = false
	): ReauthPrimaryAuthenticationProvider {
		$this->user = $this->createNoOpMock( User::class, [ 'getName', 'getId', 'isAnon' ] );
		$this->user->method( 'getName' )->willReturn( 'TestUser' );
		$this->user->method( 'getId' )->willReturn( 1 );
		$this->user->method( 'isAnon' )->willReturn( false );

		$token = $this->createNoOpMock( Token::class, [ 'toString' ] );
		$token->method( 'toString' )->willReturn( 'token' );
		$session = $this->createNoOpMock( Session::class, [ 'getUser', 'getToken', 'persist' ] );
		$session->method( 'getUser' )->willReturn( $this->user );
		$session->method( 'getToken' )->willReturn( $token );
		$request = $this->createNoOpMock( WebRequest::class, [ 'getSession' ] );
		$request->method( 'getSession' )->willReturn( $session );

		$keys = array_map( function ( string $moduleName ) use ( $passwordlessModules ) {
			$key = $this->getMockBuilder( AuthKey::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'getModule', 'supportsPasswordlessLogin', 'verify', 'jsonSerialize' ] )
				->getMock();
			$key->method( 'getModule' )->willReturn( $moduleName );
			$key->method( 'supportsPasswordlessLogin' )
				->willReturn( in_array( $moduleName, $passwordlessModules, true ) );
			return $key;
		}, $enabledModules );
		$oathUser = $this->createNoOpMock(
			OATHUser::class,
			[ 'getKeys', 'isTwoFactorAuthEnabled', 'userHasNonSpecialEnabledKeys' ]
		);
		$oathUser->method( 'getKeys' )->willReturn( $keys );
		$oathUser->method( 'isTwoFactorAuthEnabled' )->willReturn( $enabledModules !== [] );
		$oathUser->method( 'userHasNonSpecialEnabledKeys' )
			->willReturn( array_diff( $enabledModules, [ 'recoverycodes' ] ) !== [] );
		$userRepository = $this->createNoOpMock( OATHUserRepository::class, [ 'findByUser' ] );
		$userRepository->method( 'findByUser' )->with( $this->user )->willReturn( $oathUser );

		$moduleRegistry = $this->createNoOpMock( OATHAuthModuleRegistry::class, [ 'getModuleByKey' ] );
		$moduleRegistry->method( 'getModuleByKey' )->willReturnCallback( function ( string $name ) {
			$module = $this->createNoOpAbstractMock( IModule::class, [ 'getName', 'getDisplayName' ] );
			$module->method( 'getName' )->willReturn( $name );
			$module->method( 'getDisplayName' )->willReturn( $this->getMockMessage( 'mock-name-' . $name ) );
			return $module;
		} );

		$this->secondaryProvider = $this->createNoOpMock(
			SecondaryAuthenticationProvider::class,
			[ 'beginSecondaryAuthentication', 'continueSecondaryAuthentication' ]
		);

		$this->authManager = $this->createNoOpMock(
			AuthManager::class,
			[ 'getRequest', 'getAuthenticationProvider', 'setAuthenticationSessionData' ]
		);
		$this->authManager->method( 'getRequest' )->willReturn( $request );
		$this->authManager->method( 'getAuthenticationProvider' )
			->with( SecondaryAuthenticationProvider::class )
			->willReturn( $this->secondaryProvider );

		$provider = new ReauthPrimaryAuthenticationProvider( $userRepository, $moduleRegistry );
		$provider->init(
			new NullLogger(),
			$this->authManager,
			$this->createNoOpMock( HookContainer::class ),
			new HashConfig( [ 'OATHPasswordlessLogin' => $passwordlessLogin ] ),
			$this->createNoOpMock( UserNameUtils::class )
		);
		return $provider;
	}

	private function getElevatedSecurityRequest( string $securityLevel ): ElevatedSecurityAuthenticationRequest {
		$req = TestingAccessWrapper::construct( ElevatedSecurityAuthenticationRequest::class );
		$req->securityLevel = $securityLevel;
		return $req;
	}

	private function getSwitchRequest( string $newModule ): TwoFactorModuleSelectAuthenticationRequest {
		$req = new TwoFactorModuleSelectAuthenticationRequest( 'totp', [] );
		$req->newModule = $newModule;
		return $req;
	}

	public static function provideGetAuthenticationRequests_skip(): array {
		return [
			'not a login' => [ AuthManager::ACTION_CHANGE, [ 'securityLevel' => 'edit' ], [ 'totp' ] ],
			'not a reauth' => [ AuthManager::ACTION_LOGIN, [], [ 'totp' ] ],
			'no 2FA' => [ AuthManager::ACTION_LOGIN, [ 'securityLevel' => 'edit' ], [] ],
			'only recovery codes, restricted reauth' => [
				AuthManager::ACTION_LOGIN, [ 'securityLevel' => 'edit' ], [ 'recoverycodes' ],
			],
			'only recovery codes, OATHManage reauth' => [
				AuthManager::ACTION_LOGIN, [ 'securityLevel' => 'OATHManage' ], [ 'recoverycodes' ],
			],
		];
	}

	/**
	 * @dataProvider provideGetAuthenticationRequests_skip
	 */
	public function testGetAuthenticationRequests_skip(
		string $action, array $options, array $enabledModules
	): void {
		$provider = $this->getProvider( $enabledModules );
		$this->secondaryProvider->expects( $this->never() )->method( 'beginSecondaryAuthentication' );
		$this->assertSame( [], $provider->getAuthenticationRequests( $action, $options ) );
	}

	public static function provideSecurityLevels(): array {
		return [
			'restricted reauth (T428982)' => [ 'edit', [ 'webauthn', 'totp' ] ],
			'OATHManage reauth' => [ 'OATHManage', [ 'webauthn', 'totp', 'recoverycodes' ] ],
		];
	}

	/**
	 * @dataProvider provideSecurityLevels
	 */
	public function testGetAuthenticationRequests_passkey( string $securityLevel, array $expectedModules ): void {
		$provider = $this->getProvider( [ 'webauthn', 'totp', 'recoverycodes' ], [ 'webauthn' ], true );
		$this->secondaryProvider->expects( $this->never() )->method( 'beginSecondaryAuthentication' );

		$reqs = $provider->getAuthenticationRequests(
			AuthManager::ACTION_LOGIN, [ 'securityLevel' => $securityLevel ]
		);
		$this->assertCount( 1, $reqs );
		$this->assertInstanceOf( TwoFactorModuleSelectAuthenticationRequest::class, $reqs[0] );
		$this->assertSame( $expectedModules, array_keys( $reqs[0]->allowedModules ) );
	}

	/**
	 * @dataProvider provideSecurityLevels
	 */
	public function testGetAuthenticationRequests_noPasskey( string $securityLevel ): void {
		$provider = $this->getProvider( [ 'totp', 'recoverycodes' ] );
		$secondaryReqs = [ new TwoFactorModuleSelectAuthenticationRequest( 'totp', [] ) ];
		// SecondaryAuthenticationProvider must receive the security level, so that it can
		// decide whether to offer recovery codes
		$this->secondaryProvider->expects( $this->once() )->method( 'beginSecondaryAuthentication' )
			->willReturnCallback( function ( User $user, array $reqs ) use ( $securityLevel, $secondaryReqs ) {
				$this->assertSame( $this->user, $user );
				$this->assertCount( 1, $reqs );
				$this->assertInstanceOf( ElevatedSecurityAuthenticationRequest::class, $reqs[0] );
				$this->assertSame( $securityLevel, $reqs[0]->securityLevel );
				return AuthenticationResponse::newUI( $secondaryReqs, $this->getMockMessage( '2fa-started' ) );
			} );

		$this->assertSame(
			$secondaryReqs,
			$provider->getAuthenticationRequests( AuthManager::ACTION_LOGIN, [ 'securityLevel' => $securityLevel ] )
		);
	}

	public static function provideBeginPrimaryAuthentication_abstain(): array {
		return [
			'not a reauth' => [ [ 'totp' ], false ],
			'no 2FA' => [ [], true ],
			'only recovery codes' => [ [ 'recoverycodes' ], true ],
		];
	}

	/**
	 * @dataProvider provideBeginPrimaryAuthentication_abstain
	 */
	public function testBeginPrimaryAuthentication_abstain( array $enabledModules, bool $isReauth ): void {
		$provider = $this->getProvider( $enabledModules );
		$this->secondaryProvider->expects( $this->never() )->method( $this->anything() );
		$reqs = $isReauth ? [ $this->getElevatedSecurityRequest( 'edit' ) ] : [];
		$this->assertEquals( AuthenticationResponse::newAbstain(), $provider->beginPrimaryAuthentication( $reqs ) );
	}

	public function testContinuePrimaryAuthentication_switchToRecoveryCodes_restricted(): void {
		$provider = $this->getProvider( [ 'totp', 'recoverycodes' ] );
		$this->secondaryProvider->expects( $this->never() )->method( $this->anything() );

		$response = $provider->continuePrimaryAuthentication( [
			$this->getSwitchRequest( 'recoverycodes' ),
			$this->getElevatedSecurityRequest( 'edit' ),
		] );
		$this->assertSame( AuthenticationResponse::FAIL, $response->status );
		$this->assertSame( 'oathauth-invalidrequest', $response->message->getKey() );
	}

	public static function provideContinuePrimaryAuthentication_switch(): array {
		return [
			'switch to recovery codes, OATHManage reauth' => [ 'recoverycodes', 'OATHManage' ],
			'switch to webauthn, restricted reauth' => [ 'webauthn', 'edit' ],
		];
	}

	/**
	 * @dataProvider provideContinuePrimaryAuthentication_switch
	 */
	public function testContinuePrimaryAuthentication_switch( string $newModule, string $securityLevel ): void {
		$provider = $this->getProvider( [ 'webauthn', 'totp', 'recoverycodes' ] );
		$reqs = [
			$this->getSwitchRequest( $newModule ),
			$this->getElevatedSecurityRequest( $securityLevel ),
		];
		$expectedResponse = AuthenticationResponse::newUI(
			[ $this->getSwitchRequest( '' ) ], $this->getMockMessage( '2fa-started' )
		);
		$this->secondaryProvider->expects( $this->never() )->method( 'continueSecondaryAuthentication' );
		$this->secondaryProvider->expects( $this->once() )->method( 'beginSecondaryAuthentication' )
			->with( $this->user, $reqs )
			->willReturn( $expectedResponse );

		$this->assertSame( $expectedResponse, $provider->continuePrimaryAuthentication( $reqs ) );
	}

	public function testContinuePrimaryAuthentication_pass(): void {
		$provider = $this->getProvider( [ 'totp' ] );
		$reqs = [ $this->getElevatedSecurityRequest( 'edit' ) ];
		$this->secondaryProvider->expects( $this->once() )->method( 'continueSecondaryAuthentication' )
			->with( $this->user, $reqs )
			->willReturn( AuthenticationResponse::newPass() );
		$this->authManager->expects( $this->once() )->method( 'setAuthenticationSessionData' )
			->with( SecondaryAuthenticationProvider::SUCCESS_KEY, true );

		$response = $provider->continuePrimaryAuthentication( $reqs );
		$this->assertSame( AuthenticationResponse::PASS, $response->status );
		$this->assertSame( 'TestUser', $response->username );
	}

	public static function provideIsRestrictedReauth(): array {
		return [
			'not a reauth' => [ null, false ],
			'restricted reauth' => [ 'edit', true ],
			'OATHManage reauth' => [ 'OATHManage', false ],
		];
	}

	/**
	 * @dataProvider provideIsRestrictedReauth
	 */
	public function testIsRestrictedReauth( ?string $securityLevel, bool $expected ): void {
		$reqs = [ $this->getSwitchRequest( '' ) ];
		if ( $securityLevel !== null ) {
			$reqs[] = $this->getElevatedSecurityRequest( $securityLevel );
		}
		$this->assertSame( $expected, ReauthPrimaryAuthenticationProvider::isRestrictedReauth( $reqs ) );
	}
}
