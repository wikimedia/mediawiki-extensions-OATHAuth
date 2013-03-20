<?php
/**
 * OATHAuth extension - Support for HMAC based one time passwords
 *
 *
 * For more info see http://mediawiki.org/wiki/Extension:OATHAuth
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2012 Ryan Lane
 * @license GNU General Public Licence 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'OATHAuth',
	'author' => 'Ryan Lane',
	'version' => '0.1',
	'url' => 'http://mediawiki.org/wiki/Extension:OATHAuth',
	'descriptionmsg' => 'oathauth-desc',
);

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['OATHAuth'] = $dir . 'OATHAuth.i18n.php';
$wgExtensionMessagesFiles['OATHAuthAlias'] = $dir . 'OATHAuth.alias.php';
$wgAutoloadClasses['HOTP'] = $dir . 'lib/hotp.php';
$wgAutoloadClasses['HOTPResult'] = $dir . 'lib/hotp.php';
$wgAutoloadClasses['Base32'] = $dir . 'lib/base32.php';
$wgAutoloadClasses['OATHUser'] = $dir . 'OATHUser.php';
$wgAutoloadClasses['SpecialOATH'] = $dir . 'special/SpecialOATH.php';
$wgSpecialPages['OATH'] = 'SpecialOATH';
$wgSpecialPageGroups['OATH'] = 'oath';

$wgResourceModules['ext.oathauth'] = array(
	'scripts' => array(
		'modules/jquery.qrcode.js',
		'modules/qrcode.js',
	),
	'position' => 'top',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'OATHAuth',
);

$wgHooks['AbortChangePassword'][] = 'OATHUser::AbortChangePassword';
$wgHooks['AbortLogin'][] = 'OATHUser::AbortLogin';
$wgHooks['UserLoginForm'][] = 'OATHUser::ModifyUITemplate';
$wgHooks['ChangePasswordForm'][] = 'OATHUser::ChangePasswordForm';
$wgHooks['TwoFactorIsEnabled'][] = 'OATHUser::TwoFactorIsEnabled';
# Schema updates
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efOATHAuthSchemaUpdates';
$wgHooks['GetPreferences'][] = 'OATHUser::manageOATH';

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efOATHAuthSchemaUpdates( $updater ) {
	$base = dirname( __FILE__ );
	switch ( $updater->getDB()->getType() ) {
	case 'mysql':
		$updater->addExtensionTable( 'oathauth_users', "$base/oathauth.sql" );
		break;
	}
	return true;
}
