<?php

ob_start();

define('ROOT_DIR', dirname(__DIR__));
define('INC_DIR', ROOT_DIR . '/include');
define('LIB_DIR', ROOT_DIR . '/include/lib');
define('UPLOAD_DIR', 'media');
define('MEDIA_PATH', ROOT_DIR . '/' . UPLOAD_DIR . '/');
define('SMARTY_DIR', LIB_DIR . '/Smarty/');
define('CP_DIR_NAME', 'control');
define('CP_DIR', ROOT_DIR);
define('CP_DIR_MOD', ROOT_DIR . '/modules');
define('DIR_MOD', ROOT_DIR . '/modules');
define('CACHE_DIR', ROOT_DIR . '/' . UPLOAD_DIR . '/cache');
define('TEMP_DIR', ROOT_DIR . '/' . UPLOAD_DIR . '/temp');
define('CP_DEF_TEMPLATES', CP_DIR_MOD . '/core/templates');
define('ERROR_LOG_FILE', ROOT_DIR . '/error_log.log');

@ini_set('log_errors', 1);
@ini_set('display_errors', 1);
@ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('error_log', ERROR_LOG_FILE);

/*
 * Deployment-specific settings (database credentials, secret key, ...)
 * live in include/config.local.php which is NOT committed to git.
 * Copy include/config.local.sample.php to include/config.local.php
 * and fill in your values. Environment variables win over the file.
 */
$db_config = array(
  'dbhost'  => getenv('DB_HOST') ?: 'localhost',
  'dbname'  => getenv('DB_NAME') ?: '',
  'dbuser'  => getenv('DB_USER') ?: '',
  'dbpass'  => getenv('DB_PASS') ?: '',
  'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
);

if (is_file(INC_DIR . '/config.local.php')) {
  require INC_DIR . '/config.local.php';
}

if (!defined('COOKIEPREFIX')) {
  define('COOKIEPREFIX', getenv('COOKIE_PREFIX') ?: 'aqaar_smio');
}
if (!defined('SECRET_KEY')) {
  define('SECRET_KEY', getenv('SECRET_KEY') ?: '');
}
if (!defined('DEBUG_MODE')) {
  define('DEBUG_MODE', filter_var(getenv('DEBUG_MODE') ?: '0', FILTER_VALIDATE_BOOLEAN));
}
