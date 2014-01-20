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
	'oathauth-desc' => 'Provides authentication support using HMAC based one-time passwords',

	'oath' => 'OATHAuth',
	'specialpages-group-oath' => 'Two Factor Authentication',
	'oathauth-account' => 'Two Factor Account Name:',
	'oathauth-secret' => 'Two Factor Secret Key:',
	'oathauth-enable' => 'Enable Two Factor Authentication',
	'oathauth-failedtoenableoauth' => 'Failed to enable two factor authentication.',
	'oathauth-alreadyenabled' => 'Two factor authentication is already enabled.',
	'oathauth-verify' => 'Verify two factor token',
	'openstackmanager-scratchtokens' => 'The following list is a list of one-time use scratch tokens. These tokens can only be used once, and are for emergency use. Please write these down and keep them in a secure location. If you lose your phone, these tokens are the only way to rescue your account. These tokens will never be shown again.',
	'oathauth-reset' => 'Reset Two Factor Credentials',
	'oathauth-donotdeleteoldsecret' => 'Please do not delete your old credentials until you have successfully validated your new credentials.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Current Token',
	'oathauth-newtoken' => 'New Token',
	'oathauth-disable' => 'Disable Two Factor Authentication',
	'oathauth-displayoathinfo' => 'Two Factor Authentication Options',
	'oathauth-validatedoath' => 'Validated two factor credentials. Two factor authentication will now be enforced.',
	'oathauth-backtopreferences' => 'Back to preferences.',
	'oathauth-failedtovalidateoauth' => 'Failed to validate two factor credentials',
	'oathauth-reattemptreset' => 'Reattempt reset of two factor credentials.',
	'oathauth-reattemptenable' => 'Reattempt enabling of two factor authentication.',
	'oathauth-disabledoath' => 'Disabled two factor authentication.',
	'oathauth-failedtodisableoauth' => 'Failed to disable two factor authentication.',
	'oathauth-reattemptdisable' => 'Reattempt disabling of two factor authentication.',
	'oathauth-failedtoresetoath' => 'Failed to reset two factor credentials.',
	'oathauth-notloggedin' => 'Login required',
	'oathauth-mustbeloggedin' => 'You must be logged in to perform this action.',
	'oathauth-prefs-label' => 'Two-factor authentication:',
	'oathauth-abortlogin' => 'The two-factor authentication token provided was invalid.',
);

/** Message documentation (Message documentation)
 * @author Raymond
 * @author Ryan Lane <rlane@wikimedia.org>
 * @author Shirayuki
 */
$messages['qqq'] = array(
	'oathauth-desc' => '{{desc|name=OATH Auth|url=http://www.mediawiki.org/wiki/Extension:OATHAuth}}',
	'oath' => '{{optional}}
{{doc-special|OATH}}',
	'specialpages-group-oath' => '{{doc-special-group|like=[[Special:OATH]]}}
{{Identical|Two factor authentication}}',
	'oathauth-account' => 'Plain text associated with two factor authentication on this wiki (username@<wiki name>) found on Special:OATH.',
	'oathauth-secret' => 'Plain text found on Special:OATH while enabling OATH',
	'oathauth-enable' => 'Page title on Special:OATH, when enabling OATH.',
	'oathauth-failedtoenableoauth' => 'Plain text, found on Special:OATH when failing to enable OATH.',
	'oathauth-alreadyenabled' => 'Plain text, found on Special:OATH when failing to enable OATH.',
	'oathauth-verify' => 'Link, found on Special:OATH when no parameters are passed.',
	'openstackmanager-scratchtokens' => 'Plain text, found on Special:OATH while enabling OATH.',
	'oathauth-reset' => 'Page title for Special:OATH, when resetting OATH.',
	'oathauth-donotdeleteoldsecret' => 'Plain text, found on Special:OATH while resetting OATH.',
	'oathauth-token' => 'HTMLForm label, found on [[Special:OATH]], when verifying OATH.
{{Identical|Token}}',
	'oathauth-currenttoken' => 'HTMLForm label, found on Special:OATH, when verifying OATH.',
	'oathauth-newtoken' => 'HTMLForm label, found on Special:OATH, when verifying OATH.',
	'oathauth-disable' => 'Page title on Special:OATH while disabling OATH.',
	'oathauth-displayoathinfo' => 'Page title on Special:OATH when no parameters are passed.',
	'oathauth-validatedoath' => 'Plain text found on Special:OATH after a token has been validated.',
	'oathauth-backtopreferences' => 'Used as link text. Link found on Special:OATH after any action has completed.',
	'oathauth-failedtovalidateoauth' => 'Plain text found on Special:OATH when validation of a token has failed.',
	'oathauth-reattemptreset' => 'Link found when resetting OATH credentials has failed on Special:OATH.',
	'oathauth-reattemptenable' => 'Link found when enabling OATH credentials has failed on Special:OATH.',
	'oathauth-disabledoath' => 'Plain text found on Special:OATH when disabling OATH has been successful.',
	'oathauth-failedtodisableoauth' => 'Plain text found Special:OATH when disabling OATH has been unsuccessful.',
	'oathauth-reattemptdisable' => 'Link found when disabling OATH credentials has failed on Special:OATH.',
	'oathauth-failedtoresetoath' => 'Plain text found on Special:OATH when reseting OATH has been unsuccessful.',
	'oathauth-notloggedin' => 'Page title seen on Special:OATH when a user is not logged in.
{{Identical|Login required}}',
	'oathauth-mustbeloggedin' => 'Plain text seen on Special:OATH when a user is not logged in.',
	'oathauth-prefs-label' => 'Plain text label seen on Special:Preferences',
	'oathauth-abortlogin' => 'Error message shown on login and password change pages when authentication is aborted.',
);

/** Afrikaans (Afrikaans)
 * @author Naudefj
 */
$messages['af'] = array(
	'oathauth-notloggedin' => 'Aanmelding is verpligtend',
);

/** Asturian (asturianu)
 * @author Xuacu
 */
$messages['ast'] = array(
	'oathauth-desc' => "Ufre soporte d'identificación utilizando contraseñes pa una sola vez basaes en HMAC",
	'specialpages-group-oath' => 'Identificación de dos factores',
	'oathauth-account' => 'Nome de la cuenta de dos factores:',
	'oathauth-secret' => 'Clave secreta de dos factores:',
	'oathauth-enable' => 'Activar la identificación de dos factores',
	'oathauth-failedtoenableoauth' => 'Error al activar la identificación de dos factores.',
	'oathauth-alreadyenabled' => 'La identificación de dos factores yá ta activada.',
	'oathauth-verify' => 'Comprobar el pase de dos factores',
	'openstackmanager-scratchtokens' => "La siguiente llista ye una llista de pases d'un solu usu. Estos pases sólo puen utilizase una vez, y son pa usu d'emerxencia. Por favor, apúnteles y guárdeles nun llugar seguru. Si pierde'l teléfonu, estos pases son la única manera de rescatar la so cuenta. Estos pases nunca volverán a apaecer.",
	'oathauth-reset' => 'Reaniciar les credenciales de dos factores',
	'oathauth-donotdeleteoldsecret' => 'Nun desanicie les sos credenciales antigües mentanto nun valide correutamente les nueves.',
	'oathauth-token' => 'Pase',
	'oathauth-currenttoken' => 'Pase actual',
	'oathauth-newtoken' => 'Pase nuevu',
	'oathauth-disable' => 'Desactivar la identificación de dos factores',
	'oathauth-displayoathinfo' => 'Opciones de la identificación de dos factores',
	'oathauth-validatedoath' => 'Validáronse les credenciales de dos factores. Agora sedrá obligatoria la identificación de dos factores.',
	'oathauth-backtopreferences' => 'Volver a les preferencies.',
	'oathauth-failedtovalidateoauth' => 'Nun pudieron validase les credenciales de dos factores',
	'oathauth-reattemptreset' => 'Volver a intentar el reestablecimientu de les credenciales de dos factores.',
	'oathauth-reattemptenable' => 'Volver a intentar activar la identificación de dos factores.',
	'oathauth-disabledoath' => 'Desactivada la identificación de dos factores.',
	'oathauth-failedtodisableoauth' => 'Error al desactivar la identificación de dos factores.',
	'oathauth-reattemptdisable' => 'Volver a intentar desactivar la identificación de dos factores.',
	'oathauth-failedtoresetoath' => 'Nun pudieron reaniciase les credenciales de dos factores.',
	'oathauth-notloggedin' => 'Ye necesario aniciar sesión',
	'oathauth-mustbeloggedin' => "Tien d'aniciar sesión pa facer esta aición.",
	'oathauth-prefs-label' => 'Identificación de dos factores:',
	'oathauth-abortlogin' => "El pase d'identificación de dos factores dau nun ye válidu.",
);

/** Breton (brezhoneg)
 * @author Fohanno
 */
$messages['br'] = array(
	'oathauth-token' => 'Jedouer',
	'oathauth-currenttoken' => 'Jedouer red',
	'oathauth-newtoken' => 'Jedouer nevez',
);

/** Chechen (нохчийн)
 * @author Умар
 */
$messages['ce'] = array(
	'oathauth-notloggedin' => 'Хьай цӀарца чугӀо',
);

/** Czech (čeština)
 * @author Mormegil
 */
$messages['cs'] = array(
	'oathauth-desc' => 'Poskytuje podporu pro autentizaci pomocí jednorázových hesel založených na HMAC',
	'specialpages-group-oath' => 'Dvoufaktorová autentizace',
	'oathauth-account' => 'Název účtu pro dvoufaktorovou autentizaci:',
	'oathauth-secret' => 'Tajný klíč pro dvoufaktorovou autentizaci:',
	'oathauth-enable' => 'Zapnutí dvoufaktorové autentizace',
	'oathauth-failedtoenableoauth' => 'Nepodařilo se zapnout dvoufaktorovou autentizaci.',
	'oathauth-alreadyenabled' => 'Dvoufaktorová autentizace již je zapnuta.',
	'oathauth-verify' => 'Ověření kódu dvoufaktorové autentizace',
	'openstackmanager-scratchtokens' => 'Následující seznam obsahuje jednorázové provizorní kódy. Tyto kódy lze použít pouze jednou a slouží pro případ nouze. Opište si je a uchovávejte je na bezpečném místě. Pokud ztratíte svůj telefon, budou tyto kódy jediným způsobem, jak zachránit svůj účet. Tyto kódy se již nikdy znovu nezobrazí.',
	'oathauth-reset' => 'Reinicializace dvoufaktorové autentizace',
	'oathauth-donotdeleteoldsecret' => 'Staré údaje si prosím nemažte, dokud se vám úspěšně nepodaří ověřit si nové přihlášení.',
	'oathauth-token' => 'Kód',
	'oathauth-currenttoken' => 'Aktuální kód',
	'oathauth-newtoken' => 'Nový kód',
	'oathauth-disable' => 'Vypnutí dvoufaktorové autentizace',
	'oathauth-displayoathinfo' => 'Nastavení dvoufaktorové autentizace',
	'oathauth-validatedoath' => 'Dvoufaktorové přihlášení ověřeno. Odteď bude vynucována dvoufaktorová autentizace.',
	'oathauth-backtopreferences' => 'Zpět na nastavení.',
	'oathauth-failedtovalidateoauth' => 'Nepodařilo se ověřit dvoufaktorové přihlášení.',
	'oathauth-reattemptreset' => 'Znovu zkusit reinicializovat dvoufaktorovou autentizaci.',
	'oathauth-reattemptenable' => 'Znovu zkusit zapnout dvoufaktorovou autentizaci.',
	'oathauth-disabledoath' => 'Dvoufaktorová autentizace vypnuta.',
	'oathauth-failedtodisableoauth' => 'Nepodařilo se vypnout dvoufaktorovou autentizaci.',
	'oathauth-reattemptdisable' => 'Znovu zkusit vypnout dvoufaktorovou autentizaci.',
	'oathauth-failedtoresetoath' => 'Nepodařilo se reinicializovat dvoufaktorovou autentizaci.',
	'oathauth-notloggedin' => 'Vyžadováno přihlášení',
	'oathauth-mustbeloggedin' => 'Pro tuto činnost musíte být přihlášeni.',
	'oathauth-prefs-label' => 'Dvoufaktorová autentizace:',
	'oathauth-abortlogin' => 'Poskytnutý dvoufaktorový ověřovací token byl neplatný.',
);

/** German (Deutsch)
 * @author Kghbln
 * @author Metalhead64
 */
$messages['de'] = array(
	'oathauth-desc' => 'Ermöglicht die Authentifizierung mit HMAC-gestützten Einmalpasswörtern',
	'specialpages-group-oath' => 'Zwei-Faktor-Authentifizierung',
	'oathauth-account' => 'Zwei-Faktor-Kontoname:',
	'oathauth-secret' => 'Zwei-Faktor-Geheimschlüssel:',
	'oathauth-enable' => 'Die Zwei-Faktor-Authentifizierung aktivieren',
	'oathauth-failedtoenableoauth' => 'Die Zwei-Faktor-Authentifizierung konnte nicht aktiviert werden.',
	'oathauth-alreadyenabled' => 'Die Zwei-Faktor-Authentifizierung ist bereits aktiviert.',
	'oathauth-verify' => 'Den Zwei-Faktor-Token bestätigten',
	'openstackmanager-scratchtokens' => 'Die folgende Liste ist eine Liste einmalig verwendbarer Sondertoken. Diese Token können jeweils nur einmal verwendet werden und sind für Notfälle vorgesehen. Bitte schreibe sie auf und verwahre sie an einem sicheren Ort. Sofern dir dein Mobiltelefon abhanden kommt, werden diese Token die einzige Möglichkeit sein, dein Konto zu retten. Diese Token werden dir niemals wieder angezeigt werden.',
	'oathauth-reset' => 'Die Zwei-Faktor-Anmeldeinformationen zurücksetzen',
	'oathauth-donotdeleteoldsecret' => 'Bitte lösche deine alten Anmeldeinformationen nicht, bis du deine neuen Anmeldeinformationen erfolgreich bestätigt hast.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Aktueller Token',
	'oathauth-newtoken' => 'Neuer Token',
	'oathauth-disable' => 'Die Zwei-Faktor-Authentifizierung deaktivieren',
	'oathauth-displayoathinfo' => 'Optionen zur Zwei-Faktor-Authentifizierung',
	'oathauth-validatedoath' => 'Die Zwei-Faktor-Anmeldeinformationen wurden bestätigt. Die Zwei-Faktor-Authentifizierung wird jetzt durchgesetzt.',
	'oathauth-backtopreferences' => 'Zurück zu den Einstellungen.',
	'oathauth-failedtovalidateoauth' => 'Die Zwei-Faktor-Anmeldeinformationen konnten nicht bestätigt werden.',
	'oathauth-reattemptreset' => 'Das Zurücksetzen der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-reattemptenable' => 'Das Aktivieren der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-disabledoath' => 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',
	'oathauth-failedtodisableoauth' => 'Die Zwei-Faktor-Authentifizierung konnte nicht deaktiviert werden.',
	'oathauth-reattemptdisable' => 'Das Deaktivieren der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-failedtoresetoath' => 'Die Zwei-Faktor-Anmeldeinformationen konnten nicht zurückgesetzt werden.',
	'oathauth-notloggedin' => 'Anmeldung erforderlich',
	'oathauth-mustbeloggedin' => 'Du musst angemeldet sein, um diese Aktion durchführen zu können.',
	'oathauth-prefs-label' => 'Zwei-Faktor-Authentifizierung:',
	'oathauth-abortlogin' => 'Der angegebene Zweifaktor-Authentifizierungstoken war ungültig.',
);

/** German (formal address) (Deutsch (Sie-Form)‎)
 * @author Kghbln
 */
$messages['de-formal'] = array(
	'openstackmanager-scratchtokens' => 'Die folgende Liste ist eine Liste einmalig verwendbarer Sondertoken. Diese Token können jeweils nur einmal verwendet werden und sind für Notfälle vorgesehen. Bitte schreiben Sie sie auf und verwahren Sie sie an einem sicheren Ort. Sofern Ihnen Ihr Mobiltelefon abhanden kommt, werden diese Token die einzige Möglichkeit sein, Ihr Konto zu retten. Diese Token werden Ihnen niemals wieder angezeigt werden.',
	'oathauth-donotdeleteoldsecret' => 'Bitte löschen Sie Ihre alten Anmeldeinformationen nicht, bis Sie Ihre neuen Anmeldeinformationen erfolgreich bestätigt haben.',
);

/** Lower Sorbian (dolnoserbski)
 * @author Derbeth
 * @author Michawiki
 */
$messages['dsb'] = array(
	'oathauth-desc' => 'Zmóžnja awtentifkaciju z pomocu raz wužytych gronidłow na zakłaźe HMAC',
	'specialpages-group-oath' => 'Dwójofaktorowa awtentifikacija',
	'oathauth-account' => 'Kontowe mě dwójofaktoroweje awtentifikacije:',
	'oathauth-secret' => 'Dwójofaktorowy pótajmny kluc:',
	'oathauth-enable' => 'Dwójofaktorowu awtentifikaciju zmóžniś',
	'oathauth-failedtoenableoauth' => 'Dwójofaktorowa awtentifikacija njedajo se zmóžniś.',
	'oathauth-alreadyenabled' => 'Dwójofaktorowa awtentifikacija jo se južo zmóžniła.',
	'oathauth-verify' => 'Dwójofaktorowy token pśeglědaś',
	'openstackmanager-scratchtokens' => 'Slědujuca lisćina jo lisćina raz wužywajobnych specialnych tokenow. Toś te tokeny daju se jano jaden raz wužywaś a su za nuzne pady. Pšosym napiš je a zachowaj je na wěstem městnje. Jolic zgubijoš swój mobilny telefon, budu toś te tokeny jadnučka móžnosć, aby swójo konto wuchował. Toś te tokeny njepokažu śi žednje zas.',
	'oathauth-reset' => 'Dwójofaktorowe pśizjawjeńske informacije slědk stajiś',
	'oathauth-donotdeleteoldsecret' => 'Pšosym njelašuj swóje stare pśizjawjeńske informacije, až njejsy swóje nowe pśizjawjeńske informacije wuspěšnje wobkšuśił.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Aktualny token',
	'oathauth-newtoken' => 'Nowy token',
	'oathauth-disable' => 'Dwójofaktorowu awtentifikaciju znjemóžniś',
	'oathauth-displayoathinfo' => 'Nastajenja dwójofaktoroweje awtentifikacije',
	'oathauth-validatedoath' => 'Dwójofaktorowe pśizjawjeńske informacije su se wobkšuśili. Dwójofaktorowa awtentifikacija buźo se něnto pśesajźiś.',
	'oathauth-backtopreferences' => 'Slědk k nastajenjam',
	'oathauth-failedtovalidateoauth' => 'Dwójofaktorowe pśizjawjeńske informacije njejsu dali se wobkšuśiś',
	'oathauth-reattemptreset' => 'Slědkstajanje dwójofaktorowych pśizjawjeńskich informacijow hyšći raz wopytaś.',
	'oathauth-reattemptenable' => 'Zmóžnjenje dwójofaktoroweje awtentifikacije hyšći raz wopytaś.',
	'oathauth-disabledoath' => 'Dwójofaktorowa awtentifikacija jo se znjemóžniła.',
	'oathauth-failedtodisableoauth' => 'Dwójofaktorowa awtentifikacija njedajo se znjemóžniś.',
	'oathauth-reattemptdisable' => 'Znjemóžnjenje dwójofaktoroweje awtentifikacije hyšći raz wopytaś.',
	'oathauth-failedtoresetoath' => 'Slědkstajanje dwójofaktorowych pśizjawjeńskich informacijow njejo se raźiło.',
	'oathauth-notloggedin' => 'Pśizjawjenje trěbne',
	'oathauth-mustbeloggedin' => 'Musyš pśizjawjony byś, aby toś tu akciju pśewjadł.',
	'oathauth-prefs-label' => 'Dwójofaktorowa awtentifikacija:',
	'oathauth-abortlogin' => 'Pódany token dwójofaktoroweje awtentifikacije jo njepłaśiwy był',
);

/** Spanish (español)
 * @author Armando-Martin
 * @author McDutchie
 */
$messages['es'] = array(
	'oathauth-desc' => 'Proporciona soporte de autenticación utilizando contraseñas de una sola vez basadas en HMAC',
	'specialpages-group-oath' => 'Autenticación de dos factores',
	'oathauth-account' => 'Nombre de cuenta de dos factores:',
	'oathauth-secret' => 'Clave secreta de dos factores:',
	'oathauth-enable' => 'Habilitar la autenticación de dos factores',
	'oathauth-failedtoenableoauth' => 'Error al habilitar la autenticación de dos factores.',
	'oathauth-alreadyenabled' => 'Ya está habilitada la autenticación de dos factores.',
	'oathauth-verify' => 'Verificar clave de dos factores',
	'openstackmanager-scratchtokens' => 'La lista siguiente es una lista de claves de un solo uso. Estas claves sólo pueden utilizarse una vez y son para uso de emergencia. Por favor, anótalas y manténlas en un lugar seguro. Si pierdes tu teléfono, estas claves son la única manera de rescatar tu cuenta. Estas claves nunca se mostrarán una segunda vez.',
	'oathauth-reset' => 'Restablecer credenciales de dos factores',
	'oathauth-donotdeleteoldsecret' => 'No elimines las viejas credenciales hasta haber validado correctamente tus nuevas credenciales.',
	'oathauth-token' => 'Clave',
	'oathauth-currenttoken' => 'Clave actual',
	'oathauth-newtoken' => 'Nueva clave',
	'oathauth-disable' => 'Deshabilitar la autenticación de dos factores',
	'oathauth-displayoathinfo' => 'Opciones de autenticación de dos factores',
	'oathauth-validatedoath' => 'Se han validado las credenciales de dos factores. Ahora se aplicará la autenticación de dos factores.',
	'oathauth-failedtovalidateoauth' => 'Error al validar las credenciales de dos factores',
	'oathauth-reattemptreset' => 'Reintentar la reposición de credenciales de dos factores.',
	'oathauth-reattemptenable' => 'Reintentar la activación de la autenticación de dos factores.',
	'oathauth-disabledoath' => 'Se ha deshabilitado la autenticación de dos factores.',
	'oathauth-failedtodisableoauth' => 'Error al deshabilitar la autenticación de dos factores.',
	'oathauth-reattemptdisable' => 'Reintentar la desactivación de la autenticación de dos factores.',
	'oathauth-failedtoresetoath' => 'Error al restablecer las credenciales de dos factores',
	'oathauth-notloggedin' => 'Es necesario iniciar sesión',
	'oathauth-mustbeloggedin' => 'Debes haber iniciado sesión para realizar esta acción.',
);

/** Estonian (eesti)
 * @author Avjoska
 */
$messages['et'] = array(
	'oathauth-notloggedin' => 'Vajalik on sisselogimine',
);

/** Persian (فارسی)
 * @author Armin1392
 */
$messages['fa'] = array(
	'specialpages-group-oath' => 'دو عامل تأیید',
	'oathauth-account' => 'دو عامل نام حساب:',
	'oathauth-secret' => 'دو عامل کلید مخفی:',
	'oathauth-enable' => 'فعال کردن دو عامل تأیید',
	'oathauth-failedtoenableoauth' => 'عدم موفقیت فعال کردن دو عامل تأیید.',
	'oathauth-alreadyenabled' => 'دو عامل تأیید در حال حاضر فعال شده‌است.',
	'oathauth-verify' => 'تأیید دو عامل نمادین',
	'oathauth-reset' => 'تنظیم مجدد دو عامل اعتبارنامه‌ها',
	'oathauth-token' => 'نماد',
	'oathauth-currenttoken' => 'نماد کنونی',
	'oathauth-newtoken' => 'نماد جدید',
	'oathauth-disable' => 'غیرفعال کردن دو عامل تأیید',
	'oathauth-displayoathinfo' => 'گزینه‌های دو عامل تأیید',
	'oathauth-backtopreferences' => 'بازگشت به اولویت‌ها',
	'oathauth-failedtovalidateoauth' => 'عدم موفقیت برای معتبر ساختن ۲ عامل اعتبارنامه‌ها',
	'oathauth-reattemptreset' => 'تلاش دوباره برای تنظیم مجدد دو عامل اعتبارنامه',
	'oathauth-reattemptenable' => 'تلاش دوباره برای فعال کردن دو عامل تأیید.',
	'oathauth-disabledoath' => 'غیرفعال کردن دو عامل تأیید.',
	'oathauth-failedtodisableoauth' => 'عدم موفقیت غیرفعال کردن دو عامل تأیید.',
	'oathauth-reattemptdisable' => 'تلاش دوباره برای غیرفعال کردن دو عامل تأیید.',
	'oathauth-failedtoresetoath' => 'عدم موفقیت برای تنظیم مجدد دو عامل اعتبارنامه.',
	'oathauth-notloggedin' => 'ورود لازم شد',
	'oathauth-mustbeloggedin' => 'شما باید برای انجام این عمل  وارد سیستم شوید.',
	'oathauth-prefs-label' => 'دو عامل تأیید:',
	'oathauth-abortlogin' => 'دو عامل نماد تأیید ارائه شده نامعتبر بود.',
);

/** French (français)
 * @author Crochet.david
 * @author Gomoko
 * @author Peter17
 * @author Sherbrooke
 */
$messages['fr'] = array(
	'oathauth-desc' => "Fournit un support d'authentification utilisant HMAC, basé sur des mots de passe à utilisation unique.",
	'specialpages-group-oath' => 'Authentification à deux facteurs',
	'oathauth-account' => 'Nom du compte à deux facteurs:',
	'oathauth-secret' => 'Clé secrète à deux facteurs:',
	'oathauth-enable' => "Activer l'authentification à deux facteurs",
	'oathauth-failedtoenableoauth' => "Impossible d'activer l'authentification à deux facteurs.",
	'oathauth-alreadyenabled' => "L'authentification à deux facteurs est déjà activée.",
	'oathauth-verify' => 'Vérifier le jeton à deux facteurs',
	'openstackmanager-scratchtokens' => "La liste suivante est une liste de jetons à gratter à utilisation unique. Ces jetons ne peuvent être utilisés qu'une seule fois, et servent en cas d'urgence. Veuillez les écrire et les conserver dans un endroit sûr. Si vous perdez votre téléphone, ces jetons sont le seul moyen de sauver votre compte. Ces jetons ne seront jamais affichés de nouveau.",
	'oathauth-reset' => 'Réinitialiser les identités de double facteur',
	'oathauth-donotdeleteoldsecret' => "Veuillez ne pas supprimer vos anciennes identifications jusqu'à ce que vous ayez bien validé vos nouvelles identifications.",
	'oathauth-token' => 'Jeton',
	'oathauth-currenttoken' => 'Jeton actuel',
	'oathauth-newtoken' => 'Nouveau jeton',
	'oathauth-disable' => "Désactiver l'authentification à deux facteurs",
	'oathauth-displayoathinfo' => "Options de l'authentification à deux facteurs",
	'oathauth-validatedoath' => "Identifications à deux facteurs validées. L'authentification à deux facteurs sera désormais appliquée.",
	'oathauth-backtopreferences' => 'Retourner aux préférences.',
	'oathauth-failedtovalidateoauth' => 'Échec de validation des identifications à deux facteurs',
	'oathauth-reattemptreset' => 'Nouvel essai de réinitialisation des identifications à deux facteurs.',
	'oathauth-reattemptenable' => "Nouvelle tentative pour activer l'authentification à deux facteurs.",
	'oathauth-disabledoath' => 'Authentification à deux facteurs désactivée.',
	'oathauth-failedtodisableoauth' => "Échec de la désactivation de l'authentification à deux facteurs.",
	'oathauth-reattemptdisable' => "Nouvel essai de désactivation de l'authentification à deux facteurs.",
	'oathauth-failedtoresetoath' => 'Échec à la réinitialisation des identités à deux facteurs.',
	'oathauth-notloggedin' => 'Connexion nécessaire',
	'oathauth-mustbeloggedin' => 'Vous devez être connecté pour effectuer cette action.',
	'oathauth-prefs-label' => 'Authentification à deux facteurs :',
	'oathauth-abortlogin' => 'Le jeton d’authentification à deux facteurs fourni n’était pas valide.',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'oathauth-desc' => 'Balye n’assistance d’ôtentificacion empleyent HMAC basâ sur des contresegnos a usâjo solèt',
	'specialpages-group-oath' => 'Ôtentificacion a doux factors',
	'oathauth-account' => 'Nom du compto a doux factors :',
	'oathauth-secret' => 'Cllâf secrèta a doux factors :',
	'oathauth-enable' => 'Activar l’ôtentificacion a doux factors',
	'oathauth-failedtoenableoauth' => 'Falyita de l’activacion de l’ôtentificacion a doux factors.',
	'oathauth-alreadyenabled' => 'L’ôtentificacion a doux factors est ja activâye.',
	'oathauth-verify' => 'Controlar lo jeton a doux factors',
	'oathauth-reset' => 'Tornar inicialisar les refèrences a doux factors',
	'oathauth-token' => 'Jeton',
	'oathauth-currenttoken' => 'Jeton d’ora',
	'oathauth-newtoken' => 'Novél jeton',
	'oathauth-disable' => 'Dèsactivar l’ôtentificacion a doux factors',
	'oathauth-displayoathinfo' => 'Chouèx de l’ôtentificacion a doux factors',
	'oathauth-failedtovalidateoauth' => 'Falyita de la validacion de les refèrences a doux factors',
	'oathauth-reattemptreset' => 'Tentativa novèla de remisa a zérô de les refèrences a doux factors.',
	'oathauth-reattemptenable' => 'Tentativa novèla d’activacion de l’ôtentificacion a doux factors.',
	'oathauth-disabledoath' => 'Ôtentificacion a doux factors dèsactivâye.',
	'oathauth-failedtodisableoauth' => 'Falyita de la dèsactivacion de l’ôtentificacion a doux factors.',
	'oathauth-reattemptdisable' => 'Tentativa novèla de dèsactivacion de l’ôtentificacion a doux factors.',
	'oathauth-failedtoresetoath' => 'Falyita de la remisa a zérô de les refèrences a doux factors.',
	'oathauth-notloggedin' => 'Branchement nècèssèro',
	'oathauth-mustbeloggedin' => 'Vos dête étre branchiê por fâre cel’accion.',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'oathauth-desc' => 'Proporciona un soporte de autenticación mediante HMAC baseado en contrasinais dunha soa vez',
	'specialpages-group-oath' => 'Autenticación de dous factores',
	'oathauth-account' => 'Nome da conta de dous factores:',
	'oathauth-secret' => 'Clave secreta de dous factores:',
	'oathauth-enable' => 'Activar a autenticación de dous factores',
	'oathauth-failedtoenableoauth' => 'Erro ao activar a autenticación de dous factores.',
	'oathauth-alreadyenabled' => 'A autenticación de dous factores xa está activada.',
	'oathauth-verify' => 'Comprobar o pase de dous factores',
	'openstackmanager-scratchtokens' => 'A seguinte é unha lista de pases dun só uso. Estes pases unicamente se poden empregar unha vez, e son para casos de emerxencia. Escríbaos nun papel e gárdeos nun lugar seguro. Se perde o seu teléfono, estes pases son o único xeito de recuperar a súa conta. Esta é a única vez que poderá ver os pases.',
	'oathauth-reset' => 'Restablecer as credenciais de dous factores',
	'oathauth-donotdeleteoldsecret' => 'Non borre as súas credenciais vellas ata que valide correctamente as novas.',
	'oathauth-token' => 'Pase',
	'oathauth-currenttoken' => 'Pase actual',
	'oathauth-newtoken' => 'Novo pase',
	'oathauth-disable' => 'Desactivar a autenticación de dous factores',
	'oathauth-displayoathinfo' => 'Opcións da autenticación de dous factores',
	'oathauth-validatedoath' => 'Validáronse as credenciais de dous factores. Agora hase aplicar a autenticación de dous factores.',
	'oathauth-backtopreferences' => 'Volver ás preferencias.',
	'oathauth-failedtovalidateoauth' => 'Erro ao validar as credenciais de dous factores',
	'oathauth-reattemptreset' => 'Reintentar o restablecemento das credenciais de dous factores.',
	'oathauth-reattemptenable' => 'Reintentar a activación da autenticación de dous factores.',
	'oathauth-disabledoath' => 'Desactivouse a autenticación de dous factores.',
	'oathauth-failedtodisableoauth' => 'Erro ao desactivar a autenticación de dous factores.',
	'oathauth-reattemptdisable' => 'Reintentar a desactivación da autenticación de dous factores.',
	'oathauth-failedtoresetoath' => 'Erro ao restablecer as credenciais de dous factores.',
	'oathauth-notloggedin' => 'Cómpre acceder ao sistema',
	'oathauth-mustbeloggedin' => 'Cómpre acceder ao sistema para levar a cabo a acción.',
	'oathauth-prefs-label' => 'Autenticación de dous factores:',
	'oathauth-abortlogin' => 'O pase de autenticación de dous factores achegado non era válido.',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'oathauth-desc' => 'Zmóžnja awtentifkaciju z pomocu jónkróć wužiwanych hesłow na zakładźe HMAC',
	'specialpages-group-oath' => 'Dwufaktorowa awtentifikacija',
	'oathauth-account' => 'Kontowe mjeno dwufaktoroweje awtentifikacije:',
	'oathauth-secret' => 'Tajny kluč dwufaktoroweje awtentfikacije:',
	'oathauth-enable' => 'Dwufaktorowu awtentifikaciju zmóžnić',
	'oathauth-failedtoenableoauth' => 'Dwufaktorowa awtentifikacija njeda so zmóžnić.',
	'oathauth-alreadyenabled' => 'Dwufaktorowa awtentifikacija je so hižo zmóžniła.',
	'oathauth-verify' => 'Dwufaktorowy token přepruwować',
	'openstackmanager-scratchtokens' => 'Slědowaca lisćina je lisćina jónkróć wužiwajomnych specialnych tokenow. Tute tokeny dadźa so jenož jadyn raz wužiwać a su za nuzowe pady. Prošu napisaj je a wobchowaj je na wěstym městnje. Jeli zhubiš swój mobilny telefon, budu tute tokeny jenička móžnosć, zo by swoje konto zachował. Tute tokeny so ći ženje znowa njepokazaja.',
	'oathauth-reset' => 'Dwufaktorowe přizjewjenske informacije wróćo stajić',
	'oathauth-donotdeleteoldsecret' => 'Prošu njezhašaj swoje stare přizjewjenske informacije, doniž njejsy swoje nowe přizjewjenske informacije wuspěšnje wobkrućił.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Aktualny token',
	'oathauth-newtoken' => 'Nowy token',
	'oathauth-disable' => 'Dwufaktorowu awtentifikaciju znjemóžnić',
	'oathauth-displayoathinfo' => 'Nastajenja dwufaktoroweje awtentifikacije',
	'oathauth-validatedoath' => 'Dwufaktorowe přizjewjenske informacije su so wobkrućili. Dwufaktorowa awtentifikacija budźe so nětko wukonjeć.',
	'oathauth-backtopreferences' => 'Wróćo k nastajenjam',
	'oathauth-failedtovalidateoauth' => 'Dwufaktorowe přizjewjenske informacije njedachu so wobkrućić',
	'oathauth-reattemptreset' => 'Wróćostajenje dwufaktorowych přizjewjenskich informacijow hišće raz spytać.',
	'oathauth-reattemptenable' => 'Zmóžnjenje dwufaktoroweje awtentifikacije hišće raz spytać.',
	'oathauth-disabledoath' => 'Dwufaktorowu awtentifikaciju znjemóžnjena.',
	'oathauth-failedtodisableoauth' => 'Dwufaktorowa awtentifikacija njeda so znjemóžnić.',
	'oathauth-reattemptdisable' => 'Znjemóžnjenje dwufaktoroweje awtentifikacije hišće raz spytać.',
	'oathauth-failedtoresetoath' => 'Dwufaktorowe přizjewjenske informacije njedachu so wróćo stajić',
	'oathauth-notloggedin' => 'Přizjewjenje trěbne',
	'oathauth-mustbeloggedin' => 'Dyrbiš přizjewjeny być, zo by tutu akciju wuwjedł.',
	'oathauth-prefs-label' => 'Dwufaktorowa awtentifikacija:',
	'oathauth-abortlogin' => 'Podaty token dwufaktoroweje awtentifikacije je njepłaćiwy był',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'oathauth-desc' => 'Forni supporto de authentication usante contrasignos a uso unic a base de HMAC',
	'specialpages-group-oath' => 'Authentication de duo factores',
	'oathauth-account' => 'Nomine de conto de duo factores:',
	'oathauth-secret' => 'Clave secrete de duo factores:',
	'oathauth-enable' => 'Activar le authentication de duo factores',
	'oathauth-failedtoenableoauth' => 'Error durante le activation del authentication de duo factores.',
	'oathauth-alreadyenabled' => 'Le authentication de duo factores es jam activate.',
	'oathauth-verify' => 'Verificar indicio de duo factores',
	'openstackmanager-scratchtokens' => 'Le sequente lista contine indicios a uso unic. Iste indicios pote esser usate un sol vice e es pro casos de emergentia. Per favor nota los e guarda los in un loco secur. Si tu perde tu telephono, iste indicios es le sol maniera de salvar tu conto. Iste indicios nunquam essera monstrate un altere vice.',
	'oathauth-reset' => 'Reinitialisar le informationes de identification de duo factores',
	'oathauth-donotdeleteoldsecret' => 'Per favor non dele vostre ancian informationes de identification usque vos ha validate con successo vostre nove informationes de identification.',
	'oathauth-token' => 'Indicio',
	'oathauth-currenttoken' => 'Indicio actual',
	'oathauth-newtoken' => 'Nove indicio',
	'oathauth-disable' => 'Disactivar le authentication de duo factores',
	'oathauth-displayoathinfo' => 'Optiones de authentication de duo factores',
	'oathauth-validatedoath' => 'Le informationes de identification de duo factores ha essite validate. Le authentication de duo factores essera applicate desde ora.',
	'oathauth-failedtovalidateoauth' => 'Le validation del informationes de identification de duo factores ha fallite.',
	'oathauth-reattemptreset' => 'Re-tentar le reinitialisation del informationes de identification de duo factores.',
	'oathauth-reattemptenable' => 'Re-tentar le activation del authentication de duo factores.',
	'oathauth-disabledoath' => 'Le authentication de duo factores ha essite disactivate.',
	'oathauth-failedtodisableoauth' => 'Le disactivation del authentication de duo factores ha fallite.',
	'oathauth-reattemptdisable' => 'Re-tentar le disactivation del authentication de duo factores.',
	'oathauth-failedtoresetoath' => 'Le reinitialisation del informationes de identification de duo factores ha fallite.',
	'oathauth-notloggedin' => 'Identification necessari',
	'oathauth-mustbeloggedin' => 'Es necessari aperir session pro exequer iste action.',
);

/** Italian (italiano)
 * @author Beta16
 * @author Darth Kule
 * @author Gianfranco
 */
$messages['it'] = array(
	'oathauth-desc' => "Fornisce supporto per l'autenticazione utilizzando password a uso singolo basate su HMAC",
	'specialpages-group-oath' => 'Autenticazione a due fattori',
	'oathauth-account' => "Nome dell'account a due fattori:",
	'oathauth-secret' => "Chiave segreta dell'account a due fattori:",
	'oathauth-enable' => 'Abilita autenticazione a due fattori',
	'oathauth-failedtoenableoauth' => "Impossibile abilitare l'autenticazione a due fattori.",
	'oathauth-alreadyenabled' => "L'autenticazione a due fattori è già abilitata.",
	'oathauth-verify' => 'Verificare il token a due fattori',
	'openstackmanager-scratchtokens' => "Il seguente è un elenco di token monouso. Questi token possono essere utilizzati solo una volta e sono per casi di emergenza. Sei pregato di annotarteli e tenerli in un luogo sicuro. Se perdi il telefono, questi token sono l'unico modo per recuperare l'accesso al tuo account. Questi token non saranno mostrati mai più.",
	'oathauth-reset' => 'Reimpostare le credenziali a due fattori',
	'oathauth-donotdeleteoldsecret' => 'Non eliminare le vecchie credenziali fino a quando non si hai convalidato con successo le nuove credenziali.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Token attuale',
	'oathauth-newtoken' => 'Nuovo token',
	'oathauth-disable' => 'Disabilita autenticazione a due fattori',
	'oathauth-displayoathinfo' => 'Opzioni autenticazione a due fattori',
	'oathauth-validatedoath' => "Convalidate le credenziali a due fattori. D'ora in poi sarà applicata l'autenticazione a due fattori.",
	'oathauth-backtopreferences' => 'Torna a preferenze.',
	'oathauth-failedtovalidateoauth' => 'Impossibile convalidare le credenziali a due fattori',
	'oathauth-reattemptreset' => 'Riprova il ripristino delle credenziali a due fattori.',
	'oathauth-reattemptenable' => "Tentare nuovamente l'attivazione dell'autenticazione a due fattori.",
	'oathauth-disabledoath' => 'Disabilita autenticazione a due fattori.',
	'oathauth-failedtodisableoauth' => "Impossibile disattivare l'autenticazione a due fattori.",
	'oathauth-reattemptdisable' => "Tentare nuovamente la disattivazione dell'autenticazione a due fattori.",
	'oathauth-failedtoresetoath' => 'Impossibile reimpostare le credenziali a due fattori',
	'oathauth-notloggedin' => 'Accesso richiesto',
	'oathauth-mustbeloggedin' => 'Devi autenticarti per eseguire questa azione.',
	'oathauth-prefs-label' => 'Autenticazione a due fattori:',
	'oathauth-abortlogin' => 'Il token di autenticazione a due fattori fornito non è valido.',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'oathauth-desc' => 'ワンタイムパスワードに基づいた HMAC を使用する認証機能を提供する',
	'specialpages-group-oath' => '二要素認証',
	'oathauth-account' => '二要素アカウント名:',
	'oathauth-secret' => '二要素秘密鍵:',
	'oathauth-enable' => '二要素認証の有効化',
	'oathauth-failedtoenableoauth' => '二要素認証の有効化に失敗しました。',
	'oathauth-alreadyenabled' => '二要素認証は既に有効になっています。',
	'oathauth-verify' => '二要素トークンを検証',
	'openstackmanager-scratchtokens' => '以下は、一度しか使用できないトークンの一覧です。これらのトークンは一度しか使用できず、緊急用です。これらを書き留めて、安全な場所に保管してください。携帯電話を紛失した際に、これらのトークンがあなたのアカウントを救済する唯一の手段になります。これらのトークンは二度と表示されません。',
	'oathauth-reset' => '二要素信用情報をリセット',
	'oathauth-donotdeleteoldsecret' => '新しい信用情報の検証が完了するまで、古い信用情報を削除しないでください。',
	'oathauth-token' => 'トークン',
	'oathauth-currenttoken' => '現在のトークン',
	'oathauth-newtoken' => '新しいトークン',
	'oathauth-disable' => '二要素認証の無効化',
	'oathauth-displayoathinfo' => '二要素認証のオプション',
	'oathauth-validatedoath' => '二要素信用情報を検証しました。二要素認証を実行します。',
	'oathauth-backtopreferences' => '個人設定に戻る。',
	'oathauth-failedtovalidateoauth' => '二要素信用情報の検証に失敗しました。',
	'oathauth-reattemptreset' => '二要素信用情報のリセットを再試行します。',
	'oathauth-reattemptenable' => '二要素認証の有効化を再試行します。',
	'oathauth-disabledoath' => '二要素認証を無効にしました。',
	'oathauth-failedtodisableoauth' => '二要素認証の無効化に失敗しました。',
	'oathauth-reattemptdisable' => '二要素認証の無効化を再試行します。',
	'oathauth-failedtoresetoath' => '二要素信用情報のリセットに失敗しました。',
	'oathauth-notloggedin' => 'ログインが必要',
	'oathauth-mustbeloggedin' => 'この操作を行うにはログインする必要があります。',
	'oathauth-prefs-label' => '二要素認証:',
	'oathauth-abortlogin' => '指定した二要素認証トークンは無効でした。',
);

/** Georgian (ქართული)
 * @author David1010
 */
$messages['ka'] = array(
	'specialpages-group-oath' => 'ორფაქტორიანი იდენტიფიკაცია',
	'oathauth-account' => 'ორფაქტორიანი ანგარიშის სახელი:',
	'oathauth-secret' => 'ორფაქტორიანი საიდუმლო გასაღები:',
	'oathauth-enable' => 'ორფაქტორიანი იდენტიფიკაციის ჩართვა',
	'oathauth-failedtoenableoauth' => 'ორფაქტორიანი იდენტიფიკაციის ჩართვა ვერ განხორციელდა.',
	'oathauth-alreadyenabled' => 'ორფაქტორიანი იდენტიფიკაცია უკვე ჩართულია.',
	'oathauth-token' => 'ჟეტონი',
	'oathauth-currenttoken' => 'მიმდინარე ჟეტონი',
	'oathauth-newtoken' => 'ახალი ჟეტონი',
	'oathauth-disable' => 'ორფაქტორიანი იდენტიფიკაციის გამორთვა',
	'oathauth-displayoathinfo' => 'ორფაქტორიანი იდენტიფიკაციის პარამეტრები',
	'oathauth-disabledoath' => 'ორფაქტორიანი იდენტიფიკაცია გამორთულია.',
	'oathauth-failedtodisableoauth' => 'ორფაქტორიანი იდენტიფიკაციის გამორთვა ვერ განხორციელდა.',
	'oathauth-notloggedin' => 'შესვლა აუცილებელია',
	'oathauth-mustbeloggedin' => 'ამ მოქმედების შესასრულებლად, თქვენ უნდა შეხვიდეთ სისტემაში.',
);

/** Korean (한국어)
 * @author Priviet
 * @author 아라
 */
$messages['ko'] = array(
	'oathauth-desc' => '일회용 비밀번호에 기초한 HMAC를 사용하여 인증 기능을 제공',
	'specialpages-group-oath' => '2요소 인증',
	'oathauth-account' => '2요소 계정 이름:',
	'oathauth-secret' => '2요소 비밀 키:',
	'oathauth-enable' => '2요소 인증 활성화',
	'oathauth-failedtoenableoauth' => '2요소 인증을 활성화하는 데 실패했습니다.',
	'oathauth-alreadyenabled' => '2요소 인증이 이미 활성화되어 있습니다.',
	'oathauth-verify' => '2요소 토큰을 검증',
	'openstackmanager-scratchtokens' => '다음 목록은 일회용 스크래치 토큰 목록입니다. 이 토큰은 한 번만 사용할 수 있으며 비상용입니다. 이것을 적어놓고 안전한 위치에 보관하세요. 핸드폰을 잊어버렸다면 이 토큰이 당신의 계정을 찾을 유일한 수단입니다. 이 토큰은 다시 볼 수 없습니다.',
	'oathauth-reset' => '2요소 자격 정보를 다시 설정',
	'oathauth-donotdeleteoldsecret' => '새로운 자격 정보의 검증을 완료할 때까지 이전 자격 정보를 삭제하지 마세요.',
	'oathauth-token' => '토큰',
	'oathauth-currenttoken' => '현재 토큰',
	'oathauth-newtoken' => '새 토큰',
	'oathauth-disable' => '2요소 비활성화',
	'oathauth-displayoathinfo' => '2요소 인증 선택',
	'oathauth-validatedoath' => '2요소 자격 정보를 검증했습니다. 2요소 인증을 실행합니다.',
	'oathauth-backtopreferences' => '사용자 환경 설정으로 돌아갑니다.',
	'oathauth-failedtovalidateoauth' => '2요소 자격정보를 검증하는 데 실패했습니다',
	'oathauth-reattemptreset' => '2요소 자격 정보 재설정을 재시도',
	'oathauth-reattemptenable' => '2요소 인증 활성화 재시도',
	'oathauth-disabledoath' => '2요소 인증을 비활성화',
	'oathauth-failedtodisableoauth' => '2요소 인증을 비활성화하는 데 실패했습니다.',
	'oathauth-reattemptdisable' => '2요소 인증을 비활성화 재시도',
	'oathauth-failedtoresetoath' => '2요소 자격 정보 재설정에 실패했습니다.',
	'oathauth-notloggedin' => '로그인 필요',
	'oathauth-mustbeloggedin' => '이 행동을 수행하려면 로그인해야 합니다.',
	'oathauth-prefs-label' => '2요소 인증:',
	'oathauth-abortlogin' => '입력된 2요소 인증 토큰이 유효하지 않습니다.',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'oathauth-desc' => 'Määd et Enlogge met enem <code lang="en">HMAC</code>-eijmohl-Paßwoot müjjelesch.',
	'oathauth-notloggedin' => 'Enlogge es nüdich',
	'oathauth-mustbeloggedin' => 'Do moß ald enjelogg sin, öm dat maache ze dörve.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'oathauth-enable' => 'Authentifikatioun mat zwee Elementer aschalten',
	'oathauth-failedtoenableoauth' => "D'Authentifizéierung mat zwee Facteuren konnt net aktivéiert ginn.",
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Aktuellen Token',
	'oathauth-newtoken' => 'Neien Token',
	'oathauth-disable' => 'Authentifikatioun mat zwee Elementer ausschalten',
	'oathauth-displayoathinfo' => 'Optioune vun der Authentifikatioun mat zwee Elementer',
	'oathauth-backtopreferences' => "Zréck op d'Astellungen.",
	'oathauth-disabledoath' => 'Authentifikatioun mat zwee Elementer ausgeschalt',
	'oathauth-failedtodisableoauth' => "D'Authentifizéierung mat zwee Facteuren konnt net ausgeschalt ginn.",
	'oathauth-notloggedin' => 'Dir musst ageloggt sinn',
	'oathauth-mustbeloggedin' => 'Dir musst ageloggt si fir dës Aktioun maachen ze kënnen.',
	'oathauth-prefs-label' => 'Authentifikatioun mat zwee Elementer:',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'oathauth-desc' => 'Овозможува заверка на корисничката веродостојност со HMAC врз основа на еднократни лозинки',
	'specialpages-group-oath' => 'Двофакторска заверка',
	'oathauth-account' => 'Двофакторско корисничко име:',
	'oathauth-secret' => 'Двофакторски таен клуч:',
	'oathauth-enable' => 'Овозможување на двофакторска заверка на веродостојноста',
	'oathauth-failedtoenableoauth' => 'Не успеав да овозможам двофакторска заверка.',
	'oathauth-alreadyenabled' => 'Двофакторската заверка е веќе овозможена.',
	'oathauth-verify' => 'Потврдете ja двофакторската шифра',
	'openstackmanager-scratchtokens' => 'Ова е список на еднократни шифри. Можат да се користат само еднаш и служат за непредвидени случаи. Запишете ги и чувајте ги на безбедно место. Ако го загубите телефонот, шифрите се единствен начин да си ја повратите сметката. Овие шифри никогаш повеќе нема да се прикажат.',
	'oathauth-reset' => 'Презадавање на двофакторско полномоштво',
	'oathauth-donotdeleteoldsecret' => 'Не ги бришете податоците од вашето постојно полномоштво додека успешно не ги заверите новите.',
	'oathauth-token' => 'Шифра',
	'oathauth-currenttoken' => 'Постојна шифра',
	'oathauth-newtoken' => 'Нова шифра',
	'oathauth-disable' => 'Оневозможување на двофакторска заверка на веродостојноста',
	'oathauth-displayoathinfo' => 'Поставки за двофакторската заверка',
	'oathauth-validatedoath' => 'Двофакторското полномоштво е заверено. Сега стапува на сила.',
	'oathauth-backtopreferences' => 'Назад на поставките.',
	'oathauth-failedtovalidateoauth' => 'Не успеав да го заверам двофакторското полномоштво',
	'oathauth-reattemptreset' => 'Обиди се пак да го зададеш новото полномоштво.',
	'oathauth-reattemptenable' => 'Обиди се пак да ја овозможиш двофакторската заверка.',
	'oathauth-disabledoath' => 'Двофакторската заверка е оневозможена.',
	'oathauth-failedtodisableoauth' => 'Не успеав да ја оневозможам двофакторската заверка.',
	'oathauth-reattemptdisable' => 'Обиди се пак да ја оневозможиш двофакторската заверка.',
	'oathauth-failedtoresetoath' => 'Не успеав одново да го зададам двофакторското полномоштво.',
	'oathauth-notloggedin' => 'Мора да се најавите',
	'oathauth-mustbeloggedin' => 'Мора да сте најавени за да ја извршите оваа постапка.',
	'oathauth-prefs-label' => 'Двофакторска заверка:',
	'oathauth-abortlogin' => 'Укажаната шифра за двофакторска заверка е неважечка.',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 */
$messages['ms'] = array(
	'oathauth-desc' => 'Menyediakan sokongan penentusahan dengan menggunakan kata laluan kegunaan sekali berasaskan HMAC',
	'specialpages-group-oath' => 'Penentusahan Dwifaktor',
	'oathauth-account' => 'Nama Akaun Dwifaktor:',
	'oathauth-secret' => 'Kunci Rahsia Dwifaktor:',
	'oathauth-enable' => 'Hidupkan Penentusahan Dwifaktor',
	'oathauth-failedtoenableoauth' => 'Penentusahan dwifaktor gagal dihidupkan.',
	'oathauth-alreadyenabled' => 'Penentusahan dwifaktor sudah dihidupkan.',
	'oathauth-verify' => 'Sahkan token dwifaktor',
	'openstackmanager-scratchtokens' => 'Yang berikut ialah senarai token kegunaan sekali. Token-token ini hanya boleh digunakan sekali, malah adalah untuk kegunaan kecemasan. Sila catatkan dalam kertas dan simpan dalam tempat yang selamat. Sekiranya anda kehilangan telefon anda, token-token ini sahajalah caranya untuk menyelamatkan akaun anda. Token-token ini tidak akan dipaparkan lagi.',
	'oathauth-reset' => 'Reset Watikah Dwifaktor',
	'oathauth-donotdeleteoldsecret' => 'Tolong jangan padamkan watikah lama sehingga watikah baru anda berjaya disahkan.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Token Semasa',
	'oathauth-newtoken' => 'Token Baru',
	'oathauth-disable' => 'Matikan Penentusahan Dwifaktor',
	'oathauth-displayoathinfo' => 'Pilihan Penentusahan Dwifaktor',
	'oathauth-validatedoath' => 'Watikah dwifaktor disahkan. Penentusahan dwifaktor kini akan berkuatkuasa.',
	'oathauth-backtopreferences' => 'Kembali ke keutamaan.',
	'oathauth-failedtovalidateoauth' => 'Watikah dwifaktor gagal disahkan',
	'oathauth-reattemptreset' => 'Mencuba semula reset watikah dwifaktor.',
	'oathauth-reattemptenable' => 'Mencuba semula penghidupan penentusahan dwifaktor.',
	'oathauth-disabledoath' => 'Penentusahan dwifaktor dimatikan.',
	'oathauth-failedtodisableoauth' => 'Penentusahan dwifaktor gagal dimatikan.',
	'oathauth-reattemptdisable' => 'Mencuba semula pematian penentusahan dwifaktor.',
	'oathauth-failedtoresetoath' => 'Watikah dwifaktor gagal direset.',
	'oathauth-notloggedin' => 'Log masuk diperlukan',
	'oathauth-mustbeloggedin' => 'Anda mesti log masuk untuk melakukan tindakan ini.',
	'oathauth-prefs-label' => 'Penentusahan dwifaktor:',
	'oathauth-abortlogin' => 'Token penentusahan dwifaktor yang diberikan adalah tidak sah.',
);

/** Dutch (Nederlands)
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'oathauth-desc' => 'Biedt ondersteuning voor authenticatie via op HMAC-gebaseerde eenmalige wachtwoorden',
	'specialpages-group-oath' => 'Twee-factor authenticatie',
	'oathauth-account' => 'Gebruikersnaam voor twee-factor:',
	'oathauth-secret' => 'Geheime sleutel voor twee-factor:',
	'oathauth-enable' => 'Twee-factor authenticatie inschakelen',
	'oathauth-failedtoenableoauth' => 'Het inschakelen van twee-factor authenticatie is mislukt.',
	'oathauth-alreadyenabled' => 'Twee-factor authenticatie al ingeschakeld.',
	'oathauth-verify' => 'Twee-factortoken controleren',
	'openstackmanager-scratchtokens' => 'De onderstaande lijst bevat tokens voor eenmalig gebruik. Deze tokens kunnen slechts één keer gebruikt worden en zijn bedoeld voor noodgevallen. Noteer deze tokens en bewaar ze op een veilige plaats. Als u uw telefoon bent verloren, zijn deze tokens de enige manier om uw gebruiker te redden. Deze tokens worden nooit meer weergegeven.',
	'oathauth-reset' => 'Twee-factorgegevens opnieuw instellen',
	'oathauth-donotdeleteoldsecret' => 'Verwijder uw oude gegevens niet totdat u bent gevalideerd met uw nieuwe gegevens.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Huidige token',
	'oathauth-newtoken' => 'Nieuwe token',
	'oathauth-disable' => 'Twee-factor authenticatie uitschakelen',
	'oathauth-displayoathinfo' => 'Instellingen voor twee-factor authenticatie',
	'oathauth-validatedoath' => 'De gebruikersgegevens voor twee-factor zijn gevalideerd. Twee-factor authenticatie is nu verplicht.',
	'oathauth-backtopreferences' => 'Terug naar voorkeuren.',
	'oathauth-failedtovalidateoauth' => 'Het valideren van de gebruikersgegevens voor twee-factor is mislukt.',
	'oathauth-reattemptreset' => 'Opnieuw proberen om twee-factorgegevens in te stellen',
	'oathauth-reattemptenable' => 'Opnieuw proberen om twee-factor authenticatie in te stellen.',
	'oathauth-disabledoath' => 'Twee-factor authenticatie is uitgeschakeld.',
	'oathauth-failedtodisableoauth' => 'Het uitschakelen van twee-factor authenticatie is mislukt.',
	'oathauth-reattemptdisable' => 'Opnieuw proberen om twee-factor authenticatie uit te schakelen.',
	'oathauth-failedtoresetoath' => 'Het opnieuw instellen van de gebruikersgegevens voor twee-factor is mislukt.',
	'oathauth-notloggedin' => 'Aanmelden verplicht',
	'oathauth-mustbeloggedin' => 'U moet aangemeld zijn om deze handeling uit te voeren.',
	'oathauth-prefs-label' => 'Twee-factor authenticatie:',
	'oathauth-abortlogin' => 'Het opgegeven token voor twee-factorauthenticatie is ongeldig.',
);

/** Polish (polski)
 * @author Chrumps
 */
$messages['pl'] = array(
	'oathauth-account' => 'Nazwa konta dwuskładnikowego:',
	'oathauth-secret' => 'Tajny klucz dwuskładnikowy:',
	'oathauth-enable' => 'Włączenie uwierzytelniania dwuskładnikowego',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Dragonòt
 */
$messages['pms'] = array(
	'oathauth-desc' => "A dà un supòrt d'autenticassion dovrand HMAC basà su dle ciav a usagi ùnich",
	'specialpages-group-oath' => 'Autenticassion a Doi Fator',
	'oathauth-account' => 'Nòm dël Cont dij Doi Fator:',
	'oathauth-secret' => 'Ciav Segreta dij Doi Fator:',
	'oathauth-enable' => "Abilité l'Autenticassion a Doi Fator",
	'oathauth-failedtoenableoauth' => "Falì a abilité l'autenticassion a doi fator.",
	'oathauth-alreadyenabled' => "L'autenticassion a doi fator a l'é già abilità.",
	'oathauth-verify' => 'Verifiché ël geton a doi fator',
	'openstackmanager-scratchtokens' => "La lista sì-dapress a l'é na lista ëd geton da dovré na vira sola. Sti geton a peulo mach esse dovrà na vira, e a son da dovré an cas d'emergensa. Për piasì, ch'a jë scriva e ch'a-j goerna ant un pòst sigur. S'a perd to teléfon, costi geton a son l'ùnica manera ëd salvé sò cont. Costi geton a saran mai pi mostrà torna.",
	'oathauth-reset' => "Riamposté j'identità ëd Doi Fator",
	'oathauth-donotdeleteoldsecret' => "Për piasì, ch'a scancela nen soe veje credensiaj fin ch'a l'abia pa validà për da bin soe neuve credensiaj.",
	'oathauth-token' => 'Marca-pòst',
	'oathauth-currenttoken' => 'Geton Corent',
	'oathauth-newtoken' => 'Geton Neuv',
	'oathauth-disable' => "Disabilité l'Autenticassion a Doi Fator",
	'oathauth-displayoathinfo' => "Opsion dl'Autenticassion a Doi Fator",
	'oathauth-validatedoath' => "Credensiaj a doi fator validà. L'autenticassion a doi fator a sarà dorenavan aplicà.",
	'oathauth-failedtovalidateoauth' => 'Falì a validé le credensiaj a doi fator',
	'oathauth-reattemptreset' => "Neuv tentativ d'amposté torna le credensiaj a doi fator.",
	'oathauth-reattemptenable' => "Neuv tentativ d'abilité l'autenticassion a doi fator.",
	'oathauth-disabledoath' => 'Autenticassion a doi fator disabilità.',
	'oathauth-failedtodisableoauth' => "Falì a disabilité l'autenticassion a doi fator.",
	'oathauth-reattemptdisable' => "Neuv tentativ ëd disabilité l'autenticassion a doi fator.",
	'oathauth-failedtoresetoath' => 'Falì a anulé le credensiaj a doi fator',
	'oathauth-notloggedin' => 'A venta rintré ant ël sistema',
	'oathauth-mustbeloggedin' => "A dev esse intrà ant ël sistema për fé st'assion.",
);

/** Romanian (română)
 * @author Firilacroco
 * @author Stelistcristi
 */
$messages['ro'] = array(
	'oathauth-token' => 'Jeton',
	'oathauth-currenttoken' => 'Jeton curent',
	'oathauth-newtoken' => 'Jeton nou',
	'oathauth-notloggedin' => 'Autentificare necesară',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'specialpages-group-oath' => 'Autendicazione a Doje Fattore',
	'oathauth-secret' => 'Chiave segrete a Doje Fattore:',
	'oathauth-enable' => "Abbilite l'Autendicazione a Doje Fattore",
	'oathauth-token' => 'Gettone',
	'oathauth-currenttoken' => 'Gettone de mò',
	'oathauth-newtoken' => 'Gettone nuève',
	'oathauth-disable' => "Disabbilite l'Autendicazione a Doje Fattore",
);

/** Russian (русский)
 * @author Okras
 */
$messages['ru'] = array(
	'oathauth-desc' => 'Обеспечивает поддержку проверки подлинности с помощью HMAC на основе одноразовых паролей',
	'specialpages-group-oath' => 'Двухфакторная аутентификация',
	'oathauth-account' => 'Двухфакторное имя учётной записи:',
	'oathauth-secret' => 'Двухфакторный секретный ключ:',
	'oathauth-enable' => 'Включить двухфакторную аутентификацию',
	'oathauth-failedtoenableoauth' => 'Не удалось включить двухфакторную аутентификацию.',
	'oathauth-alreadyenabled' => 'Двухфакторная аутентификация уже включена.',
	'oathauth-verify' => 'Проверить двухфакторный токен',
	'openstackmanager-scratchtokens' => 'Это — список одноразовых токенов. Эти токены могут быть использованы только один раз и предназначены для использования в чрезвычайных ситуациях. Пожалуйста, запишите их и хранить в безопасном месте. Если вы потеряете свой телефон, они будут единственным способом спасти ваш аккаунт. Эти токены больше никогда не будут показаны.',
	'oathauth-reset' => 'Сбросить двухфакторные учётные данные',
	'oathauth-donotdeleteoldsecret' => 'Пожалуйста, не удаляйте старые учётные данные до тех пор, пока вы успешно не проверите ваши новые учётные данные.',
	'oathauth-token' => 'Токен',
	'oathauth-currenttoken' => 'Текущий токен',
	'oathauth-newtoken' => 'Новый токен',
	'oathauth-disable' => 'Отключить двухфакторную аутентификацию',
	'oathauth-displayoathinfo' => 'Настройки двухфакторной аутентификации',
	'oathauth-validatedoath' => 'Двухфакторные учётные данные проверены. Теперь будет использоваться двухфакторная аутентификация.',
	'oathauth-backtopreferences' => 'Обратно к настройкам.',
	'oathauth-failedtovalidateoauth' => 'Не удалось проверить двухфакторные учётные данные',
	'oathauth-reattemptreset' => 'Повторите попытку сброса двухфакторных учётных данных.',
	'oathauth-reattemptenable' => 'Повторите попытку включения двухфакторной аутентификации.',
	'oathauth-disabledoath' => 'Двухфакторная аутентификация отключена.',
	'oathauth-failedtodisableoauth' => 'Не удалось отключить двухфакторную аутентификацию.',
	'oathauth-reattemptdisable' => 'Повторите попытку отключение двухфакторной аутентификации.',
	'oathauth-failedtoresetoath' => 'Не удалось сбросить двухфакторные учётные данные.',
	'oathauth-notloggedin' => 'Требуется авторизация',
	'oathauth-mustbeloggedin' => 'Для выполнения этого действия вы должны быть авторизованы.',
	'oathauth-prefs-label' => 'Двухфакторная аутентификация:',
	'oathauth-abortlogin' => 'Предоставленный токен двухфакторной аутентификации недействителен.',
);

/** Sinhala (සිංහල)
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'specialpages-group-oath' => 'ද්විසාධක සහතික කිරීම',
	'oathauth-account' => 'ද්විසාධක ගිණුමේ නාමය:',
	'oathauth-secret' => 'ද්විසාධක රහස් යතුර:',
	'oathauth-enable' => 'ද්විසාධක සහතික කිරීම සක්‍රිය කරන්න',
	'oathauth-verify' => 'ද්විසාධක ටෝකනය සත්‍යාපනය කරන්න',
	'oathauth-reset' => 'ද්විසාධක සාක්ෂි පත්‍ර යළි සකසන්න',
	'oathauth-token' => 'ටෝකනය',
	'oathauth-currenttoken' => 'වත්මන් ටෝකනය',
	'oathauth-newtoken' => 'නව ටෝකනය',
	'oathauth-disable' => 'ද්විසාධක සහතික කිරීම අක්‍රිය කරන්න',
	'oathauth-displayoathinfo' => 'ද්විසාධක සහතික කිරීම් විකල්පයන්',
	'oathauth-disabledoath' => 'ද්විසාධක සහතික කිරීම අක්‍රිය කර ඇත.',
	'oathauth-notloggedin' => 'ප්‍රවිෂ්ට වී සිටීම අවශ්‍යයි',
	'oathauth-mustbeloggedin' => 'මෙම ක්‍රියාව ඉටු කිරීම සඳහා ඔබ ප්‍රවිෂ්ට වී සිටිය යුතුයි.',
);

/** Swedish (svenska)
 * @author WikiPhoenix
 */
$messages['sv'] = array(
	'oathauth-notloggedin' => 'Inloggning krävs',
);

/** Tamil (தமிழ்)
 * @author Karthi.dr
 */
$messages['ta'] = array(
	'oathauth-notloggedin' => 'புகுபதிகை செய்யப்பட வேண்டும்',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'oathauth-desc' => 'Nagbibigay ng suporta ng pagpapatunay sa pamamagitan ng pang-isang ulit na mga hudyat na nakabatay sa HMAC',
	'specialpages-group-oath' => 'Dalawang Salik na Pagpapatunay',
	'oathauth-account' => 'Dalawang Salik na Pangalan ng Akawnt:',
	'oathauth-secret' => 'Dalawang Salik na Susi ng Lihim:',
	'oathauth-enable' => 'Paganahin ang Dalawang Salik na Pagpapatunay',
	'oathauth-failedtoenableoauth' => 'Nabigo sa pagpapaandar ng dalawang salik na pagpapatunay.',
	'oathauth-alreadyenabled' => 'Gumagana na ang dalawang salik na pagpapatunay.',
	'oathauth-verify' => 'Tiyakin ang dalawang salik na panghalip',
	'openstackmanager-scratchtokens' => 'Ang sumusunod na lista ay isang listahan ng mga pang-isahang ulit na paggamit na mga panghalip na nagagasgas. Ang mga panghalip na ito ay magagamit lamang nang isang beses, at mga para sa paggamit na pangkagipitan. Pakisulat ang mga ito at itabi sa isang ligtas na lugar. Kapag naiwala mo ang telepono mo, ang mga panghalip na ito lang ang makasasaklolo sa akawnt mo. Hindi na muling ipapakita pa ang mga panghalip na ito.',
	'oathauth-reset' => 'Itakdang Muli ang Dalawang Salik na mga Kredensiyal',
	'oathauth-donotdeleteoldsecret' => 'Mangyaring huwag burahin ang luma mong mga kredensiyal hanggang sa matagumpay mo nang napagtibay ang bago mong mga katibayan.',
	'oathauth-token' => 'Kahalip',
	'oathauth-currenttoken' => 'Kasalukuyang Kahalip',
	'oathauth-newtoken' => 'Bagong Kahalip',
	'oathauth-disable' => 'Huwag Paganahin ang Dalawang Salik na Pagpapatunay',
	'oathauth-displayoathinfo' => 'Mga Mapagpipilian sa Dalawang Salik na Pagpapatunay',
	'oathauth-validatedoath' => 'Nakapagpatunay ng dalawang salik na mga katibayan. Isasakatuparan na ngayon ang dalawang salik na pagpapatunay.',
	'oathauth-failedtovalidateoauth' => 'Nabigo sa pagpapatunay ng dalawang salik na mga kredensiyal',
	'oathauth-reattemptreset' => 'Tangkaing muli ang muling pagtatakda ng dalawang salik na mga kredensiyal.',
	'oathauth-reattemptenable' => 'Muling tangkain ang pagpapagana ng dalawang salik na pagpapatunay.',
	'oathauth-disabledoath' => 'Hindi na pinaaandar ang dalawang salik na pagpapatunay.',
	'oathauth-failedtodisableoauth' => 'Nabigo sa hindi pagpapaandar ng dalawang salik na pagpapatunay.',
	'oathauth-reattemptdisable' => 'Muling tangkain ang hindi na pagpapagana ng dalawang salik na pagpapatunay.',
	'oathauth-failedtoresetoath' => 'Nabigo sa muling pagtatakda ng dalawang salik na mga kredensiyal.',
	'oathauth-notloggedin' => 'Kailangan ang paglagda',
	'oathauth-mustbeloggedin' => 'Dapat na nakalagda ka upang maisagawa ang galaw na ito.',
);

/** Ukrainian (українська)
 * @author Andriykopanytsia
 */
$messages['uk'] = array(
	'oathauth-desc' => 'Забезпечує підтримку автентифікації за допомогою HMAC на основі одноразових паролів',
	'specialpages-group-oath' => 'Двофакторна авторизація',
	'oathauth-account' => "Двофакторне ім'я облікового запису:",
	'oathauth-secret' => 'Двофакторний секретний ключ:',
	'oathauth-enable' => 'Увімкнути двофакторну авторизацію',
	'oathauth-failedtoenableoauth' => 'Не вдалося ввімкнути двофакторну авторизацію.',
	'oathauth-alreadyenabled' => 'Двофакторна авторизація вже увімкнена.',
	'oathauth-verify' => 'Перевірити двофакторний код',
	'openstackmanager-scratchtokens' => "У цій таблиці наведено список одноразових кодів. Ці коди можна використовувати лише один раз. Вони призначені для використання в надзвичайних ситуаціях. Будь ласка, запишіть їх і зберігайте у безпечному місці. Якщо ваш телефон загубиться, ці коди єдиний спосіб врятувати ваш обліковий запис. Ці коди ніколи не з'являться знову.",
	'oathauth-reset' => 'Очистити двофакторні облікові дані',
	'oathauth-donotdeleteoldsecret' => 'Будь ласка, не видаляйте старі облікові дані, поки ви успішно не перевірите нові облікові дані.',
	'oathauth-token' => 'Код',
	'oathauth-currenttoken' => 'Поточний код',
	'oathauth-newtoken' => 'Новий код',
	'oathauth-disable' => 'Вимкнути двофакторну авторизацію',
	'oathauth-displayoathinfo' => 'Параметри двофакторної авторизації',
	'oathauth-validatedoath' => 'Перевірено двофакторні облікові дані. Двофакторна авторизація тепер буде застосована.',
	'oathauth-backtopreferences' => 'Назад до налаштувань.',
	'oathauth-failedtovalidateoauth' => 'Не вдалося перевірити двофакторні повноваження',
	'oathauth-reattemptreset' => 'Повторна спроба очищення двофакторних облікових даних.',
	'oathauth-reattemptenable' => 'Повторна спроба вмикання двофакторної авторизації.',
	'oathauth-disabledoath' => 'Вимкнено двофакторну авторизацію.',
	'oathauth-failedtodisableoauth' => 'Не вдалося вимкнути двофакторну авторизацію.',
	'oathauth-reattemptdisable' => 'Повторна спроба вимкнення двофакторної авторизації.',
	'oathauth-failedtoresetoath' => 'Не вдалося очистити двофакторні облікові дані.',
	'oathauth-notloggedin' => 'Потрібний вхід',
	'oathauth-mustbeloggedin' => 'Ви повинні увійти в систему для виконання цієї дії.',
	'oathauth-prefs-label' => 'Двофакторна авторизація:',
	'oathauth-abortlogin' => 'Наданий маркер двофакторної авторизації недійсний.',
);

/** Urdu (اردو)
 * @author පසිඳු කාවින්ද
 */
$messages['ur'] = array(
	'oathauth-disabledoath' => 'معذور تصدیق دو عنصر.',
	'oathauth-failedtodisableoauth' => 'دو اہم عنصر کی توثیق کو غیر فعال کرنے میں ناکام رہے.',
	'oathauth-failedtoresetoath' => 'دو عنصر کی اسناد کو دوبارہ مرتب کرنے میں ناکام رہے.',
	'oathauth-notloggedin' => 'لاگ ان درکار',
	'oathauth-mustbeloggedin' => 'تم ہونا چاہئے لاگ ان اس فعل کو انجام دینے کے لئے.',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Hzy980512
 * @author Liuxinyu970226
 * @author Xiaomingyan
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'oathauth-desc' => '提供使用基于HMAC的一次性密码的身份验证支持',
	'specialpages-group-oath' => '双因素身份验证',
	'oathauth-account' => '双因素帐户名：',
	'oathauth-secret' => '双因素机密密钥：',
	'oathauth-enable' => '启用双因素身份验证',
	'oathauth-failedtoenableoauth' => '启用双因素身份验证失败。',
	'oathauth-alreadyenabled' => '双因素身份验证已被启用。',
	'oathauth-verify' => '验证双因素令牌',
	'openstackmanager-scratchtokens' => '下面列出的是一次性紧急权标。这些权标仅能使用一次，并仅在紧急情况下使用。请将它们记下来并妥善保管。如果您遗失了手机，唯一能够恢复您的账户就只有这些权标。这些权标不会再显示。',
	'oathauth-reset' => '重置双因素凭据',
	'oathauth-donotdeleteoldsecret' => '请在您成功验证您的新凭据之前不要删除老的凭据。',
	'oathauth-token' => '密钥',
	'oathauth-currenttoken' => '当前令牌',
	'oathauth-newtoken' => '新的令牌',
	'oathauth-disable' => '禁用双因素身份验证',
	'oathauth-displayoathinfo' => '双因素身份验证选项',
	'oathauth-validatedoath' => '已验证双因素凭据。双因素身份验证现将实施生效。',
	'oathauth-backtopreferences' => '回到设置。',
	'oathauth-failedtovalidateoauth' => '验证双因素凭据失败',
	'oathauth-reattemptreset' => '再次尝试双因素凭据的重置。',
	'oathauth-reattemptenable' => '再次尝试启用双因素身份验证。',
	'oathauth-disabledoath' => '已禁用双因素身份验证。',
	'oathauth-failedtodisableoauth' => '禁用双因素身份验证失败。',
	'oathauth-reattemptdisable' => '再次尝试禁用双因素身份验证。',
	'oathauth-failedtoresetoath' => '重置双因素凭据失败。',
	'oathauth-notloggedin' => '需要登入',
	'oathauth-mustbeloggedin' => '您必须登录才能执行此操作。',
	'oathauth-prefs-label' => '双重验证：',
	'oathauth-abortlogin' => '提供的双因素身份验证令牌无效。',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Simon Shek
 */
$messages['zh-hant'] = array(
	'oathauth-notloggedin' => '需要登入',
	'oathauth-mustbeloggedin' => '您必須登錄才能執行此操作。',
);
