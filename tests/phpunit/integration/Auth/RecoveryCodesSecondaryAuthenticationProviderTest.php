<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ElevatedSecurityAuthenticationRequest;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesSecondaryAuthenticationProvider
 * @covers \MediaWiki\Extension\OATHAuth\Auth\ReauthPrimaryAuthenticationProvider::isRestrictedReauth
 */
class RecoveryCodesSecondaryAuthenticationProviderTest extends MediaWikiIntegrationTestCase {

	private function getElevatedSecurityRequest( string $securityLevel ): ElevatedSecurityAuthenticationRequest {
		$req = TestingAccessWrapper::construct( ElevatedSecurityAuthenticationRequest::class );
		$req->securityLevel = $securityLevel;
		return $req;
	}

	/**
	 * @param bool $hasOtherKeys Whether the user has 2FA keys other than recovery codes
	 * @param bool|null $verifyResult Return value of RecoveryCodes::verify(), or null if verify()
	 *   must not be called
	 */
	private function getProvider(
		bool $hasOtherKeys,
		?bool $verifyResult = null
	): RecoveryCodesSecondaryAuthenticationProvider {
		$oathUser = $this->createNoOpMock( OATHUser::class, [ 'userHasNonSpecialEnabledKeys' ] );
		$oathUser->method( 'userHasNonSpecialEnabledKeys' )->willReturn( $hasOtherKeys );
		$userRepository = $this->createNoOpMock( OATHUserRepository::class, [ 'findByUser' ] );
		$userRepository->method( 'findByUser' )->willReturn( $oathUser );

		$module = $this->createNoOpMock( RecoveryCodes::class, [ 'isEnabled', 'verify' ] );
		$module->method( 'isEnabled' )->willReturn( true );
		if ( $verifyResult === null ) {
			$module->expects( $this->never() )->method( 'verify' );
		} else {
			$module->expects( $this->once() )->method( 'verify' )
				->with( $oathUser, [ 'recoverycode' => '12345678' ] )
				->willReturn( $verifyResult );
		}

		$provider = new RecoveryCodesSecondaryAuthenticationProvider(
			$module,
			$userRepository,
			$this->createNoOpMock( OATHAuthLogger::class )
		);
		$provider->init(
			new NullLogger(),
			$this->createNoOpMock( AuthManager::class ),
			$this->createNoOpMock( HookContainer::class ),
			new HashConfig( [ 'OATHAuthEnforce2FAForAll' => false ] ),
			$this->createNoOpMock( UserNameUtils::class )
		);
		return $provider;
	}

	private function getUser(): User {
		$user = $this->createNoOpMock( User::class, [ 'getName', 'pingLimiter' ] );
		$user->method( 'getName' )->willReturn( 'TestUser' );
		$user->method( 'pingLimiter' )->willReturn( false );
		return $user;
	}

	private function getRecoveryCodeRequest(): RecoveryCodesAuthenticationRequest {
		$req = new RecoveryCodesAuthenticationRequest();
		$req->RecoveryCode = '12345678';
		return $req;
	}

	public static function provideBeginSecondaryAuthentication(): array {
		return [
			'normal login, only recovery codes' => [
				'securityLevel' => null,
				'hasOtherKeys' => false,
				'expectUI' => true,
			],
			'normal login, other keys' => [
				'securityLevel' => null,
				'hasOtherKeys' => true,
				'expectUI' => true,
			],
			'restricted reauth, other keys (T428982)' => [
				'securityLevel' => 'edit',
				'hasOtherKeys' => true,
				'expectUI' => false,
			],
			'restricted reauth, only recovery codes (T428982)' => [
				'securityLevel' => 'edit',
				'hasOtherKeys' => false,
				'expectUI' => false,
			],
			'OATHManage reauth, other keys' => [
				'securityLevel' => 'OATHManage',
				'hasOtherKeys' => true,
				'expectUI' => true,
			],
			'OATHManage reauth, only recovery codes: skip 2FA' => [
				'securityLevel' => 'OATHManage',
				'hasOtherKeys' => false,
				'expectUI' => false,
			],
		];
	}

	/**
	 * @dataProvider provideBeginSecondaryAuthentication
	 */
	public function testBeginSecondaryAuthentication(
		?string $securityLevel, bool $hasOtherKeys, bool $expectUI
	): void {
		$reqs = $securityLevel === null ? [] : [ $this->getElevatedSecurityRequest( $securityLevel ) ];
		$response = $this->getProvider( $hasOtherKeys )
			->beginSecondaryAuthentication( $this->getUser(), $reqs );

		if ( $expectUI ) {
			$this->assertSame( AuthenticationResponse::UI, $response->status );
			$this->assertCount( 1, $response->neededRequests );
			$this->assertInstanceOf( RecoveryCodesAuthenticationRequest::class, $response->neededRequests[0] );
		} else {
			$this->assertEquals( AuthenticationResponse::newAbstain(), $response );
		}
	}

	public static function provideContinueSecondaryAuthentication(): array {
		return [
			'normal login' => [
				'securityLevel' => null,
				'expectPass' => true,
			],
			'OATHManage reauth' => [
				'securityLevel' => 'OATHManage',
				'expectPass' => true,
			],
			'restricted reauth (T428982)' => [
				'securityLevel' => 'edit',
				'expectPass' => false,
			],
		];
	}

	/**
	 * @dataProvider provideContinueSecondaryAuthentication
	 */
	public function testContinueSecondaryAuthentication( ?string $securityLevel, bool $expectPass ): void {
		$reqs = [ $this->getRecoveryCodeRequest() ];
		if ( $securityLevel !== null ) {
			$reqs[] = $this->getElevatedSecurityRequest( $securityLevel );
		}
		// A restricted reauth must fail before the code is checked, even if the code is valid
		$provider = $this->getProvider( true, $expectPass ? true : null );
		$response = $provider->continueSecondaryAuthentication( $this->getUser(), $reqs );

		if ( $expectPass ) {
			$this->assertEquals( AuthenticationResponse::newPass(), $response );
		} else {
			$this->assertSame( AuthenticationResponse::FAIL, $response->status );
			$this->assertSame( 'oathauth-recovery-code-login-failed', $response->message->getKey() );
		}
	}
}
