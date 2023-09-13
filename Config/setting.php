<?php
/**
 * ContentsRepair 専用ログ
 */
define('LOG_CONTENTS_REPAIR', 'log_contents_repair');
CakeLog::config('log_contents_repair', [
	'engine' => 'FileLog',
	'types' => ['log_contents_repair'],
	'file' => 'log_contents_repair',
	'size' => '3MB',
	'rotate' => 5,
]);
