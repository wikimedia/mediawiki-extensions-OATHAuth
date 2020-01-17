<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['null_casts_as_any_type'] = true;

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/OATHAuth',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/OATHAuth',
	]
);

return $cfg;
