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
	echo "This file is an extension to the MediaWiki software and cannot be used standalone.\n";
	die( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'OATHAuth',
	'author' => 'Ryan Lane',
	'version' => '0.2.1',
	'url' => 'http://mediawiki.org/wiki/Extension:OATHAuth',
	'descriptionmsg' => 'oathauth-desc',
);

/**
 * The number of token windows in each direction that should be valid
 *
 * This tells OATH to accept tokens for a range of $wgOATHAuthWindowRadius * 2 windows
 * (which is effectively ((1 + 2 * $wgOATHAuthWindowRadius) * 30) seconds).
 * This range of valid windows is centered around the current time.
 *
 * The purpose of this configuration variable is to account for differences between
 * the user's clock and the server's clock. However, it is recommended to keep it as
 * low as possible.
 *
 * @var int
 */
$wgOATHAuthWindowRadius = 4;

$dir = __DIR__ . '/';

$wgMessagesDirs['OATHAuth'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OATHAuth'] = $dir . 'OATHAuth.i18n.php';
$wgExtensionMessagesFiles['OATHAuthAlias'] = $dir . 'OATHAuth.alias.php';
$wgAutoloadClasses['OATHAuthHooks'] = $dir . 'OATHAuth.hooks.php';
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

$wgHooks['AbortChangePassword'][] = 'OATHAuthHooks::AbortChangePassword';
$wgHooks['AbortLogin'][] = 'OATHAuthHooks::AbortLogin';
$wgHooks['UserLoginForm'][] = 'OATHAuthHooks::ModifyUITemplate';
$wgHooks['ChangePasswordForm'][] = 'OATHAuthHooks::ChangePasswordForm';
$wgHooks['TwoFactorIsEnabled'][] = 'OATHAuthHooks::TwoFactorIsEnabled';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'OATHAuthHooks::OATHAuthSchemaUpdates';
$wgHooks['GetPreferences'][] = 'OATHAuthHooks::manageOATH';


