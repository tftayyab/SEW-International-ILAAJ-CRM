<?php
/**
 * Application bootstrap
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Karachi');

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_TEMP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp');

require_once ROOT_PATH . '/includes/env.php';
load_env_file();

require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/csrf.php';

// Autoload Composer if available (PhpSpreadsheet)
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
