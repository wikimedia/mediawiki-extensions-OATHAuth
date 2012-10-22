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

/** German (Deutsch)
 * @author Kghbln
 * @author Metalhead64
 */
$messages['de'] = array(
	'oathauth-desc' => 'Ermöglicht die Authentifizierung mit HMAC-gestützten Einmalpasswörtern',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Zurück zu den Optionen zur Zwei-Faktor-Authentifizierung',
	'oathauth-failedtovalidateoauth' => 'Die Zwei-Faktor-Anmeldeinformationen konnten nicht bestätigt werden.',
	'oathauth-reattemptreset' => 'Das Zurücksetzen der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-reattemptenable' => 'Das Aktivieren der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-disabledoath' => 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',
	'oathauth-failedtodisableoauth' => 'Die Zwei-Faktor-Authentifizierung konnte nicht deaktiviert werden.',
	'oathauth-reattemptdisable' => 'Das Deaktivieren der Zwei-Faktor-Anmeldeinformationen wird nun erneut versucht.',
	'oathauth-failedtoresetoath' => 'Die Zwei-Faktor-Anmeldeinformationen konnten nicht zurückgesetzt werden.',
	'oathauth-notloggedin' => 'Anmeldung erforderlich',
	'oathauth-mustbeloggedin' => 'Du musst angemeldet sein, um diese Aktion durchführen zu können.',
);

/** German (formal address) (Deutsch (Sie-Form)‎)
 * @author Kghbln
 */
$messages['de-formal'] = array(
	'openstackmanager-scratchtokens' => 'Die folgende Liste ist eine Liste einmalig verwendbarer Sondertoken. Diese Token können jeweils nur einmal verwendet werden und sind für Notfälle vorgesehen. Bitte schreiben Sie sie auf und verwahren Sie sie an einem sicheren Ort. Sofern Ihnen Ihr Mobiltelefon abhanden kommt, werden diese Token die einzige Möglichkeit sein, Ihr Konto zu retten. Diese Token werden Ihnen niemals wieder angezeigt werden.',
	'oathauth-donotdeleteoldsecret' => 'Bitte löschen Sie Ihre alten Anmeldeinformationen nicht, bis Sie Ihre neuen Anmeldeinformationen erfolgreich bestätigt haben.',
);

/** Spanish (español)
 * @author Armando-Martin
 * @author McDutchie
 */
$messages['es'] = array(
	'oathauth-desc' => 'Proporciona soporte de autenticación utilizando contraseñas de una sola vez basadas en HMAC',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Volver a las opciones de dos factores.',
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

/** French (français)
 * @author Crochet.david
 * @author Gomoko
 */
$messages['fr'] = array(
	'oathauth-desc' => "Fournit un support d'authentification utilisant HMAC, basé sur des mots de passe à utilisation unique.",
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Retour aux options de deux facteurs.',
	'oathauth-failedtovalidateoauth' => 'Échec de validation des identifications à deux facteurs',
	'oathauth-reattemptreset' => 'Nouvel essai de réinitialisation des identifications à deux facteurs.',
	'oathauth-reattemptenable' => "Nouvelle tentative pour activer l'authentification à deux facteurs.",
	'oathauth-disabledoath' => 'Authentification à deux facteurs désactivée.',
	'oathauth-failedtodisableoauth' => "Échec de la désactivation de l'authentification à deux facteurs.",
	'oathauth-reattemptdisable' => "Nouvel essai de désactivation de l'authentification à deux facteurs.",
	'oathauth-failedtoresetoath' => 'Échec à la réinitialisation des identités à deux facteurs.',
	'oathauth-notloggedin' => 'Connexion nécessaire',
	'oathauth-mustbeloggedin' => 'Vous devez être connecté pour effectuer cette action.',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'oathauth' => 'OATHAuth',
	'oathauth-token' => 'Jeton',
	'oathauth-currenttoken' => 'Jeton d’ora',
	'oathauth-newtoken' => 'Novél jeton',
	'oathauth-notloggedin' => 'Branchement nècèssèro',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'oathauth-desc' => 'Proporciona un soporte de autenticación mediante HMAC baseado en contrasinais dunha soa vez',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Volver ás opcións de dous factores.',
	'oathauth-failedtovalidateoauth' => 'Erro ao validar as credenciais de dous factores',
	'oathauth-reattemptreset' => 'Reintentar o restablecemento das credenciais de dous factores.',
	'oathauth-reattemptenable' => 'Reintentar a activación da autenticación de dous factores.',
	'oathauth-disabledoath' => 'Desactivouse a autenticación de dous factores.',
	'oathauth-failedtodisableoauth' => 'Erro ao desactivar a autenticación de dous factores.',
	'oathauth-reattemptdisable' => 'Reintentar a desactivación da autenticación de dous factores.',
	'oathauth-failedtoresetoath' => 'Erro ao restablecer as credenciais de dous factores.',
	'oathauth-notloggedin' => 'Cómpre acceder ao sistema',
	'oathauth-mustbeloggedin' => 'Cómpre acceder ao sistema para levar a cabo a acción.',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'oathauth-desc' => 'Zmóžnja awtentifkaciju z pomocu jónkróć wužiwanych hesłow na zakładźe HMAC',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Wróćo k nastajenjam dwufaktoroweje awtentifikacije.',
	'oathauth-failedtovalidateoauth' => 'Dwufaktorowe přizjewjenske informacije njedachu so wobkrućić',
	'oathauth-reattemptreset' => 'Wróćostajenje dwufaktorowych přizjewjenskich informacijow hišće raz spytać.',
	'oathauth-reattemptenable' => 'Zmóžnjenje dwufaktoroweje awtentifikacije hišće raz spytać.',
	'oathauth-disabledoath' => 'Dwufaktorowu awtentifikaciju znjemóžnjena.',
	'oathauth-failedtodisableoauth' => 'Dwufaktorowa awtentifikacija njeda so znjemóžnić.',
	'oathauth-reattemptdisable' => 'Znjemóžnjenje dwufaktoroweje awtentifikacije hišće raz spytać.',
	'oathauth-failedtoresetoath' => 'Dwufaktorowe přizjewjenske informacije njedachu so wróćo stajić',
	'oathauth-notloggedin' => 'Přizjewjenje trěbne',
	'oathauth-mustbeloggedin' => 'Dyrbiš přizjewjeny być, zo by tutu akciju wuwjedł.',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'oathauth-desc' => 'Forni supporto de authentication usante contrasignos a uso unic a base de HMAC',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Retornar al optiones de duo factores.',
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
 */
$messages['it'] = array(
	'oathauth-desc' => "Fornisce supporto per l'autenticazione utilizzando password a uso singolo basate su HMAC",
	'oathauth' => 'OATHAuth',
	'specialpages-group-oath' => 'Autenticazione a due fattori',
	'oathauth-account' => "Nome dell'account a due fattori:",
	'oathauth-secret' => "Chiave segreta dell'account a due fattori:",
	'oathauth-enable' => 'Abilita autenticazione a due fattori',
	'oathauth-failedtoenableoauth' => "Impossibile abilitare l'autenticazione a due fattori.",
	'oathauth-alreadyenabled' => "L'autenticazione a due fattori è già abilitata.",
	'oathauth-donotdeleteoldsecret' => 'Non eliminare le vecchie credenziali fino a quando non si hai convalidato con successo le nuove credenziali.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Token attuale',
	'oathauth-newtoken' => 'Nuovo token',
	'oathauth-disable' => 'Disabilita autenticazione a due fattori',
	'oathauth-displayoathinfo' => 'Opzioni autenticazione a due fattori',
	'oathauth-reattemptenable' => "Tentare nuovamente l'attivazione dell'autenticazione a due fattori.",
	'oathauth-disabledoath' => 'Disabilita autenticazione a due fattori.',
	'oathauth-failedtodisableoauth' => "Impossibile disattivare l'autenticazione a due fattori.",
	'oathauth-reattemptdisable' => "Tentare nuovamente la disattivazione dell'autenticazione a due fattori.",
	'oathauth-notloggedin' => 'Accesso richiesto',
	'oathauth-mustbeloggedin' => 'Devi autenticarti per eseguire questa azione.',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'oathauth-desc' => 'ワンタイムパスワードに基づいた HMAC を使用する認証機能を提供する',
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => '二要素のオプションに戻る。',
	'oathauth-failedtovalidateoauth' => '二要素信用情報の検証に失敗しました。',
	'oathauth-reattemptreset' => '二要素信用情報のリセットを再試行します。',
	'oathauth-reattemptenable' => '二要素認証の有効化を再試行します。',
	'oathauth-disabledoath' => '二要素認証を無効にしました。',
	'oathauth-failedtodisableoauth' => '二要素認証の無効化に失敗しました。',
	'oathauth-reattemptdisable' => '二要素認証の無効化を再試行します。',
	'oathauth-failedtoresetoath' => '二要素信用情報のリセットに失敗しました。',
	'oathauth-notloggedin' => 'ログインが必要',
	'oathauth-mustbeloggedin' => 'この操作を行うにはログインする必要があります。',
);

/** Georgian (ქართული)
 * @author David1010
 */
$messages['ka'] = array(
	'oathauth' => 'OATHAuth',
	'specialpages-group-oath' => 'ორფაქტორიანი იდენტიფიკაცია',
	'oathauth-account' => 'ორფაქტორიანი ანგარიშის სახელი:',
	'oathauth-secret' => 'ორფაქტორიანი საიდუმლო გასაღები:',
	'oathauth-enable' => 'ორფაქტორიანი იდენტიფიკაციის ჩართვა',
	'oathauth-failedtoenableoauth' => 'ორფაქტორიანი იდენტიფიკაციის ჩართვა ვერ განხორციელდა.',
	'oathauth-alreadyenabled' => 'ორფაქტორიანი იდენტიფიკაცია უკვე ჩართულია.',
	'oathauth-disable' => 'ორფაქტორიანი იდენტიფიკაციის გამორთვა',
	'oathauth-displayoathinfo' => 'ორფაქტორიანი იდენტიფიკაციის პარამეტრები',
	'oathauth-backtodisplay' => 'ორი ფაქტორის პარამეტრებზე დაბრუნება.',
	'oathauth-disabledoath' => 'ორფაქტორიანი იდენტიფიკაცია გამორთულია.',
	'oathauth-failedtodisableoauth' => 'ორფაქტორიანი იდენტიფიკაციის გამორთვა ვერ განხორციელდა.',
	'oathauth-notloggedin' => 'შესვლა აუცილებელია',
	'oathauth-mustbeloggedin' => 'ამ მოქმედების შესასრულებლად, თქვენ უნდა შეხვიდეთ სისტემაში.',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'oathauth-desc' => 'Määd et Enlogge met enem <code lang="en">HMAC</code>-eijmohl-Paßwoot müjjelesch.',
	'oathauth' => 'OATHAuth',
	'oathauth-notloggedin' => 'Enlogge es nüdich',
	'oathauth-mustbeloggedin' => 'Do moß ald enjelogg sin, öm dat maache ze dörve.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'oathauth-token' => 'Token',
	'oathauth-notloggedin' => 'Dir musst ageloggt sinn',
	'oathauth-mustbeloggedin' => 'Dir musst ageloggt si fir dës Aktioun maachen ze kënnen.',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'oathauth-desc' => 'Овозможува заверка на корисничката веродостојност со HMAC врз основа на еднократни лозинки',
	'oathauth' => 'OATHAuth',
	'specialpages-group-oath' => 'Двофакторска заверка',
	'oathauth-account' => 'Двофакторско корисничко име:',
	'oathauth-secret' => 'Двофакторски таен клуч:',
	'oathauth-enable' => 'Овозможување на двофакторска заверка на веродостојноста',
	'oathauth-failedtoenableoauth' => 'Не успеав да овозможам двофакторска заверка.',
	'oathauth-alreadyenabled' => 'Двофакторската заверка е веќе овозможена.',
	'oathauth-verify' => 'Потврдете го двофакторскиот жетон',
	'openstackmanager-scratchtokens' => 'Ова е список на еднократни жетони. Можат да се користат само еднаш и служат за непредвидени случаи. Запишете ги и чувајте ги на безбедно место. Ако го загубите телефонот, жетоните се единствен начин да си ја повратите сметката. Овие жетони никогаш повеќе нема да се прикажат.',
	'oathauth-reset' => 'Презадавање на двофакторско полномоштво',
	'oathauth-donotdeleteoldsecret' => 'Не ги бришете податоците од вашето постојно полномоштво додека успешно не ги заверите новите.',
	'oathauth-token' => 'Жетон',
	'oathauth-currenttoken' => 'Постоен жетон',
	'oathauth-newtoken' => 'Нов жетон',
	'oathauth-disable' => 'Оневозможување на двофакторска заверка на веродостојноста',
	'oathauth-displayoathinfo' => 'Поставки за двофакторската заверка',
	'oathauth-validatedoath' => 'Двофакторското полномоштво е заверено. Сега стапува на сила.',
	'oathauth-backtodisplay' => 'Назад на поставките.',
	'oathauth-failedtovalidateoauth' => 'Не успеав да го заверам двофакторското полномоштво',
	'oathauth-reattemptreset' => 'Обиди се пак да го зададеш новото полномоштво.',
	'oathauth-reattemptenable' => 'Обиди се пак да ја овозможиш двофакторската заверка.',
	'oathauth-disabledoath' => 'Двофакторската заверка е оневозможена.',
	'oathauth-failedtodisableoauth' => 'Не успеав да ја оневозможам двофакторската заверка.',
	'oathauth-reattemptdisable' => 'Обиди се пак да ја оневозможиш двофакторската заверка.',
	'oathauth-failedtoresetoath' => 'Не успеав одново да го зададам двофакторското полномоштво.',
	'oathauth-notloggedin' => 'Мора да се најавите',
	'oathauth-mustbeloggedin' => 'Мора да сте најавени за да ја извршите оваа постапка.',
);

/** Dutch (Nederlands)
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'oathauth-desc' => 'Biedt ondersteuning voor authenticatie via op HMAC-gebaseerde eenmalige wachtwoorden',
	'oathauth' => 'OATHAuth',
	'specialpages-group-oath' => 'Twee-factor authenticatie',
	'oathauth-account' => 'Gebruikersnaam voor twee-factor:',
	'oathauth-secret' => 'Geheime sleutel voor twee-factor:',
	'oathauth-enable' => 'Twee-factor authenticatie inschakelen',
	'oathauth-failedtoenableoauth' => 'Het inschakelen van twee-factor authenticatie is mislukt.',
	'oathauth-alreadyenabled' => 'Twee-factor authenticatie al ingeschakeld.',
	'oathauth-verify' => 'Twee-factortoken controleren',
	'openstackmanager-scratchtokens' => 'De onderstaande lijst bevat tokens voor eenmalig gebruik. Deze tokens kunnen slechts één keer gebruikt worden en zijn bedoeld voor noodgevallen. Noteer deze tokens alstublieft en bewaar ze op een veilige plaats. Als u uw telefoon bent verloren, zijn deze tokens de enige manier om uw gebruiker te redden. Deze tokens worden nooit meer weergegeven.',
	'oathauth-reset' => 'Twee-factorgegevens opnieuw instellen',
	'oathauth-donotdeleteoldsecret' => 'Verwijder uw oude gegevens niet totdat u bent gevalideerd met uw nieuwe gegevens.',
	'oathauth-token' => 'Token',
	'oathauth-currenttoken' => 'Huidige token',
	'oathauth-newtoken' => 'Nieuwe token',
	'oathauth-disable' => 'Twee-factor authenticatie uitschakelen',
	'oathauth-displayoathinfo' => 'Instellingen voor twee-factor authenticatie',
	'oathauth-validatedoath' => 'De gebruikersgegevens voor twee-factor zijn gevalideerd. Twee-factor authenticatie is nu verplicht.',
	'oathauth-backtodisplay' => 'Terug naar instellingen voor twee-factor authenticatie.',
	'oathauth-failedtovalidateoauth' => 'Het valideren van de gebruikersgegevens voor twee-factor is mislukt.',
	'oathauth-reattemptreset' => 'Opnieuw proberen om twee-factorgegevens in te stellen',
	'oathauth-reattemptenable' => 'Opnieuw proberen om twee-factor authenticatie in te stellen.',
	'oathauth-disabledoath' => 'Twee-factor authenticatie is uitgeschakeld.',
	'oathauth-failedtodisableoauth' => 'Het uitschakelen van twee-factor authenticatie is mislukt.',
	'oathauth-reattemptdisable' => 'Opnieuw proberen om twee-factor authenticatie uit te schakelen.',
	'oathauth-failedtoresetoath' => 'Het opnieuw instellen van de gebruikersgegevens voor twee-factor is mislukt.',
	'oathauth-notloggedin' => 'Aanmelden verplicht',
	'oathauth-mustbeloggedin' => 'U moet aangemeld zijn om deze handeling uit te voeren.',
);

/** Romanian (română)
 * @author Stelistcristi
 */
$messages['ro'] = array(
	'oathauth-notloggedin' => 'Autentificare necesară',
);

/** Sinhala (සිංහල)
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'oathauth' => 'OATHAuth',
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
	'oathauth' => 'OATHAuth',
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
	'oathauth-backtodisplay' => 'Bumalik sa mga pilian ng dalawang salik.',
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

/** Urdu (اردو)
 * @author පසිඳු කාවින්ද
 */
$messages['ur'] = array(
	'oathauth-backtodisplay' => 'واپس دو اختیارات عنصر.',
	'oathauth-disabledoath' => 'معذور تصدیق دو عنصر.',
	'oathauth-failedtodisableoauth' => 'دو اہم عنصر کی توثیق کو غیر فعال کرنے میں ناکام رہے.',
	'oathauth-failedtoresetoath' => 'دو عنصر کی اسناد کو دوبارہ مرتب کرنے میں ناکام رہے.',
	'oathauth-notloggedin' => 'لاگ ان درکار',
	'oathauth-mustbeloggedin' => 'تم ہونا چاہئے لاگ ان اس فعل کو انجام دینے کے لئے.',
);

/** Simplified Chinese (中文（简体）‎)
 */
$messages['zh-hans'] = array(
	'oathauth-notloggedin' => '需要登入',
	'oathauth-mustbeloggedin' => '您必须登录才能执行此操作。',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Simon Shek
 */
$messages['zh-hant'] = array(
	'oathauth-notloggedin' => '需要登入',
	'oathauth-mustbeloggedin' => '您必須登錄才能執行此操作。',
);
