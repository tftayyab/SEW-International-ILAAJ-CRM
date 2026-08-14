<?php
/**
 * Application bootstrap
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Karachi');

// ROOT_PATH = public_html (web root). BASE_PATH = parent (vendor, uploads, .env).
define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', dirname(ROOT_PATH));
define('UPLOAD_TEMP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp');

require_once ROOT_PATH . '/includes/env.php';
load_env_file();

require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/csrf.php';

// Autoload Composer if available (PhpSpreadsheet) — vendor sits above public_html
$autoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
