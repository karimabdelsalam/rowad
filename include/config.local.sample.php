<?php

/*
 * Local deployment configuration.
 * Copy this file to include/config.local.php and fill in your values.
 * config.local.php is ignored by git so secrets never reach the repository.
 */

$db_config['dbhost']  = 'localhost';
$db_config['dbname']  = '';
$db_config['dbuser']  = '';
$db_config['dbpass']  = '';
$db_config['charset'] = 'utf8mb4';

define('COOKIEPREFIX', 'aqaar_smio');

// Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
define('SECRET_KEY', '');

define('DEBUG_MODE', false);
