<?php
/**
 * Database configuration.
 * Local XAMPP uses .env. On Namecheap, localhost + the cPanel database are used
 * even if a local .env was uploaded by mistake.
 */

$httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
$isLocal = $httpHost === ''
    || str_contains($httpHost, 'localhost')
    || str_starts_with($httpHost, '127.0.0.1');

if ($isLocal) {
    return [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'dbname' => env('DB_NAME', 'ilaaj_crm'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ];
}

$host = env('DB_HOST', 'localhost');
$dbname = env('DB_NAME', 'webhngff_ilaaj_crm');
$username = env('DB_USER', 'webhngff_hanzalamaw');
$password = env('DB_PASS', 'Hani2006!');

if ($host === '127.0.0.1') {
    $host = 'localhost';
}
if ($dbname === 'ilaaj_crm') {
    $dbname = 'webhngff_ilaaj_crm';
}
if ($username === 'root') {
    $username = 'webhngff_hanzalamaw';
}

return [
    'host' => $host,
    'port' => env('DB_PORT', '3306'),
    'dbname' => $dbname,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8mb4',
];
