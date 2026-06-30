<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Module;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Module\TOTP
 * @group Database
 */
class TOTPTest extends MediaWikiIntegrationTestCase {

	private const array TOTP_ARRAY = [ 'secret' => 'BI5MNFS3MFS577GN7ALT2LY4FYLANBQXBGKNL656YQ' ];

	private const string RECOVERY_TOKEN = '64SZLJTTPRI5XBUE';

	private const array RECOVERY_ARRAY = [ 'recoverycodekeys' => [ self::RECOVERY_TOKEN ] ];

	private const array RECOVERY_TOKEN_ARRAY = [ 'token' => self::RECOVERY_TOKEN ];

	public function testVerifyTOTP() {
		$key1 = TOTPKey::newFromArray( self::TOTP_ARRAY );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === TOTP::MODULE_NAME ?
				[ $key1 ] : [] );
		$module = new TOTP(
			$this->createMock( OATHUserRepository::class ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$this->createMock( OATHAuthLogger::class )
		);

		$this->overrideConfigValue( 'OATHAuthWindowRadius', 1 );
		ConvertibleTimestamp::setFakeTime( '20251121225810' );

		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '602401' ] ),
			'Token for this timestamp passes verification' );
		$this->assertFalse( $module->verify( $mockUser, [ 'token' => '602401' ] ),
			'Prevents replay attacks' );
		$this->assertFalse( $module->verify( $mockUser, [ 'token' => '455138' ] ),
			'Token for future window fails verification' );

		ConvertibleTimestamp::setFakeTime( '20251121230910' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '502636' ] ),
			'Token for next window passes verification' );

		ConvertibleTimestamp::setFakeTime( '20251121231110' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '683170' ] ),
			'Token for previous window passes verification' );
	}

	public function testVerifyTOTPMultiple() {
		$key1 = TOTPKey::newFromArray( self::TOTP_ARRAY );
		$key2 = TOTPKey::newFromArray( [ 'secret' => '75YQX2JREHDEGJXOCRBZZRT3AM3Y5VG6CD32IWEJCI' ] );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === TOTP::MODULE_NAME ?
				[ $key1, $key2 ] : [] );
		$module = new TOTP(
			$this->createMock( OATHUserRepository::class ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$this->createMock( OATHAuthLogger::class )
		);

		ConvertibleTimestamp::setFakeTime( '20251121233540' );

		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '932322' ] ),
			'Token for second key passes verification' );

		ConvertibleTimestamp::setFakeTime( '20251121233640' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '529789' ] ),
			'Token for first key passes verification' );
	}

	public function testVerifyWithRecoveryCode() {
		$key1 = TOTPKey::newFromArray( self::TOTP_ARRAY );
		$rcKey = RecoveryCodeKeys::newFromArray( self::RECOVERY_ARRAY );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => match ( $moduleName ) {
				TOTP::MODULE_NAME => [ $key1 ],
				RecoveryCodes::MODULE_NAME => [ $rcKey ],
				default => []
			} );
		$mockUserRepo = $this->createMock( OATHUserRepository::class );
		$this->setService( 'OATHAuth.UserRepository', $mockUserRepo );
		$module = new TOTP(
			$mockUserRepo,
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$this->createMock( OATHAuthLogger::class )
		);

		$this->assertTrue( $module->verify( $mockUser, self::RECOVERY_TOKEN_ARRAY ) );
	}

	public function testVerifyRejectsRecoveryCodeWhenFallbackDisabled() {
		$key1 = TOTPKey::newFromArray( self::TOTP_ARRAY );
		$rcKey = RecoveryCodeKeys::newFromArray( self::RECOVERY_ARRAY );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => match ( $moduleName ) {
				TOTP::MODULE_NAME => [ $key1 ],
				RecoveryCodes::MODULE_NAME => [ $rcKey ],
				default => []
			} );
		$mockUserRepo = $this->createMock( OATHUserRepository::class );
		$this->setService( 'OATHAuth.UserRepository', $mockUserRepo );
		$module = new TOTP(
			$mockUserRepo,
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry(),
			$this->createMock( OATHAuthLogger::class )
		);

		$this->assertFalse(
			$module->verify(
				$mockUser,
				self::RECOVERY_TOKEN_ARRAY + [ 'disableRecoveryCodeFallback' => true, ]
			)
		);
	}
}
