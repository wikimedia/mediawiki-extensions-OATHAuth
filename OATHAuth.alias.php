<?php
/**
 * Aliases for OATHAuth's special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'DisableOATHForUser' => [ 'DisableOATHForUser' ],
	'OATHManage' => [ 'Manage_Two-factor_authentication', 'OATH_Manage', 'OATHManage',
		'OATH', 'Two-factor_authentication', 'OATHAuth' ]
];

/** Arabic (العربية) */
$specialPageAliases['ar'] = [
	'OATHManage' => [ 'أواث', 'أواث_أوث' ],
];

/** Egyptian Arabic (مصرى) */
$specialPageAliases['arz'] = [
	'OATHManage' => [ 'اواث', 'اواث_اوث' ],
];

/** Czech (čeština) */
$specialPageAliases['cs'] = [
	'OATHManage' => [ 'Spravovat_dvoufaktorové_ověření', 'Dvoufaktorové_ověření' ],
];

/** Spanish (Español) */
$specialPageAliases['es'] = [
	'DisableOATHForUser' => [
		'Desactivar_la_autenticación_de_dos_factores_de_un_usuario',
		'Desactivar_OATH_de_un_usuario'
	],
	'OATHManage' => [
		'Gestionar_la_autenticación_de_dos_factores',
		'Gestionar_OATH',
		'Autenticación_de_dos_factores'
	]
];

/** Northern Luri (لۊری شومالی) */
$specialPageAliases['lrc'] = [
	'OATHManage' => [ 'قأسأم' ],
];

/** Serbian Cyrillic (српски (ћирилица)) */
$specialPageAliases['sr-ec'] = [
	'OATHManage' => [ 'Двофакторска_потврда_идентитета' ],
];

/** Serbian Latin (srpski (latinica)) */
$specialPageAliases['sr-el'] = [
	'OATHManage' => [ 'Dvofaktorska_potvrda_identiteta' ],
];

/** Urdu (اردو) */
$specialPageAliases['ur'] = [
	'OATHManage' => [ 'حلف_نامہ' ],
];

/** Simplified Chinese (中文（简体）‎) */
$specialPageAliases['zh-hans'] = [
	'OATHManage' => [ 'OATH验证' ],
];

/** Traditional Chinese (中文（繁體）‎) */
$specialPageAliases['zh-hant'] = [
	'OATHManage' => [ 'OATH_認證' ],
];
