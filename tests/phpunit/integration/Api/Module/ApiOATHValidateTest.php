<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Api\Module;

use Base32\Base32;
use jakobo\HOTP\HOTP;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\UserIdentity;
use Wikimedia\TestingAccessWrapper;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @covers \MediaWiki\Extension\OATHAuth\Api\Module\ApiOATHValidate
 * @group Database
 */
class ApiOATHValidateTest extends ApiTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	public function testFailures() {
		$testUser = $this->getTestUser()->getUserIdentity();
		$this->failureTest( $testUser, 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAA I am fake', 'User does not exist' );
		$this->failureTest( $testUser, $testUser->getName(), '2FA not enabled for user' );
	}

	public function failureTest( UserIdentity $user, string $username, string $message ) {
		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $username,
				'data' => '{"token": "123456"}',
			],
			null,
			new UltimateAuthority( $user )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => false,
					'valid' => false,
				],
			],
			$result,
			$message
		);
	}

	public function provideToken() {
		$key = TOTPKey::newFromRandom();
		$secret = TestingAccessWrapper::newFromObject( $key )->secret;
		yield 'correct' => [
			HOTP::generateByTime(
				Base32::decode( $secret['secret'] ),
				$secret['period'],
			)->toHOTP( 6 ),
			$key,
			true,
			'Correct TOTP token'
		];

		yield 'incorrect' => [
			'000000',
			TOTPKey::newFromRandom(),
			false,
			'Incorrect TOTP token'
		];
	}

	/** @dataProvider provideToken */
	public function testToken( string $token, TOTPKey $key, bool $valid, string $message ) {
		$testUser = $this->getTestUser();

		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$userRepository->createKey(
			$userRepository->findByUser( $testUser->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => json_encode( [ 'token' => $token ] ),
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => true,
					'valid' => $valid,
				],
			],
			$result,
			$message
		);
	}

	public function testFailedTokenCreatesCheckUserEntry() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$testUser = $this->getTestUser();
		$key = TOTPKey::newFromRandom();

		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$userRepository->createKey(
			$userRepository->findByUser( $testUser->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$logCountBefore = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'verify-failed',
				'cupe_actor' => $testUser->getUser()->getActorId(),
			] )
			->fetchField();

		$this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => json_encode( [ 'token' => '000000' ] ),
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$logCountAfter = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'verify-failed',
				'cupe_actor' => $testUser->getUser()->getActorId(),
			] )
			->fetchField();

		$this->assertSame(
			(int)$logCountBefore + 1,
			(int)$logCountAfter,
			'A verify-failed entry should be created in cu_private_event'
		);
	}

	public function testSuccessfulTokenCreatesCheckUserEntry() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$testUser = $this->getTestUser();
		$key = TOTPKey::newFromRandom();
		$secret = TestingAccessWrapper::newFromObject( $key )->secret;

		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$userRepository->createKey(
			$userRepository->findByUser( $testUser->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$logCountBefore = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'verify-success',
				'cupe_actor' => $testUser->getUser()->getActorId(),
			] )
			->fetchField();

		$token = HOTP::generateByTime(
			Base32::decode( $secret['secret'] ),
			$secret['period'],
		)->toHOTP( 6 );

		$this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => json_encode( [ 'token' => $token ] ),
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$logCountAfter = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'verify-success',
				'cupe_actor' => $testUser->getUser()->getActorId(),
			] )
			->fetchField();

		$this->assertSame(
			(int)$logCountBefore + 1,
			(int)$logCountAfter,
			'A verify-success entry should be created in cu_private_event'
		);
	}
}
