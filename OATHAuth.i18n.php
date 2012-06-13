<?php
/**
 * Internationalisation file for extension OATHAuth
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2011 Ryan Lane
 * @license GNU General Public Licence 2.0 or later
 */

$messages = array();

/** English
 * @author Ryan Lane <rlane@wikimedia.org>
 */
$messages['en'] = array(
	'oathauth-desc' => 'Provides authentication support using HMAC based one time passwords',

	'oathauth' => 'OATHAuth',
	'specialpages-group-oath' => 'Two Factor Authentication',
	'oathauth-account' => 'Two Factor Account Name:',
	'oathauth-secret' => 'Two Factor Secret Key:',
	'oathauth-enable' => 'Enable Two Factor Authentication',
	'oathauth-failedtoenableoauth' => 'Failed to enable two factor authentication.',
	'oathauth-alreadyenabled' => 'Two factor authentication is already enabled.',
	'oathauth-verify' => 'Verify two factor token',
	'openstackmanager-scratchtokens' => 'The following list is a list of one time use scratch tokens. These tokens can only be used once, and are for emergency use. Please write these down and keep them in a secure location. If you lose your phone, these tokens are the only way to rescue your account. These tokens will never be shown again.',
	'oathauth-reset' => 'Reset Two Factor Credentials',
	'oathauth-donotdeleteoldsecret' => 'Please do not delete your old credentials until you have successfully validated your new credentials.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Current Token',
	'oathauth-newtoken' => 'New Token',
	'oathauth-disable' => 'Disable Two Factor Authentication',
	'oathauth-displayoathinfo' => 'Two Factor Authentication Options',
	'oathauth-validatedoath' => 'Validated two factor credentials. Two factor authentication will now be enforced.',
	'oathauth-backtodisplay' => 'Back to two factor options.',
	'oathauth-failedtovalidateoauth' => 'Failed to validate two factor credentials',
	'oathauth-reattemptreset' => 'Reattempt reset of two factor credentials.',
	'oathauth-reattemptenable' => 'Reattempt enabling of two factor authentication.',
	'oathauth-disabledoath' => 'Disabled two factor authentication.',
	'oathauth-failedtodisableoauth' => 'Failed to disable two factor authentication.',
	'oathauth-reattemptdisable' => 'Reattempt disabling of two factor authentication.',
	'oathauth-failedtoresetoath' => 'Failed to reset two factor credentials.',
	'oathauth-notloggedin' => 'Login required',
	'oathauth-mustbeloggedin' => 'You must be logged in to perform this action.',
);

/** Message documentation (Message documentation)
 * @author Ryan Lane <rlane@wikimedia.org>
 */
$messages['qqq'] = array(
	'oathauth-desc' => '{{desc}}',
	'oathauth' => 'Extension name, found on Special:Version',
	'specialpages-group-oath' => 'Used in the special page list',
	'oathauth-account' => 'Plain text associated with two factor authentication on this wiki (username@<wiki name>) found on Special:OATH.',
	'oathauth-secret' => 'Plain text found on Special:OATH while enabling OATH',
	'oathauth-enable' => 'Page title on Special:OATH, when enabling OATH.',
	'oathauth-failedtoenableoauth' => 'Plain text, found on Special:OATH when failing to enable OATH.',
	'oathauth-alreadyenabled' => 'Plain text, found on Special:OATH when failing to enable OATH.',
	'oathauth-verify' => 'Link, found on Special:OATH when no parameters are passed.',
	'openstackmanager-scratchtokens' => 'Plain text, found on Special:OATH while enabling OATH.',
	'oathauth-reset' => 'Page title for Special:OATH, when resetting OATH.',
	'oathauth-donotdeleteoldsecret' => 'Plain text, found on Special:OATH while resetting OATH.',
	'oathauth-token' => 'HTMLForm label, found on Special:OATH, when verifying OATH.',
	'oathauth-currenttoken' => 'HTMLForm label, found on Special:OATH, when verifying OATH.',
	'oathauth-newtoken' => 'HTMLForm label, found on Special:OATH, when verifying OATH.',
	'oathauth-disable' => 'Page title on Special:OATH while disabling OATH.',
	'oathauth-displayoathinfo' => 'Page title on Special:OATH when no parameters are passed.',
	'oathauth-validatedoath' => 'Plain text found on Special:OATH after a token has been validated.',
	'oathauth-backtodisplay' => 'Link found on Special:OATH after any action has completed.',
	'oathauth-failedtovalidateoauth' => 'Plain text found on Special:OATH when validation of a token has failed.',
	'oathauth-reattemptreset' => 'Link found when resetting OATH credentials has failed on Special:OATH.',
	'oathauth-reattemptenable' => 'Link found when enabling OATH credentials has failed on Special:OATH.',
	'oathauth-disabledoath' => 'Plain text found on Special:OATH when disabling OATH has been successful.',
	'oathauth-failedtodisableoauth' => 'Plain text found Special:OATH when disabling OATH has been unsuccessful.',
	'oathauth-reattemptdisable' => 'Link found when disabling OATH credentials has failed on Special:OATH.',
	'oathauth-failedtoresetoath' => 'Plain text found on Special:OATH when reseting OATH has been unsuccessful.',
	'oathauth-notloggedin' => 'Page title seen on Special:OATH when a user is not logged in.',
	'oathauth-mustbeloggedin' => 'Plain text seen on Special:OATH when a user is not logged in.',
);
