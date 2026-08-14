<?php
/**
 * Database configuration
 * Update these values for your local MySQL environment.
 */
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'dbname' => getenv('DB_NAME') ?: 'webhngff_ilaaj_crm',
    'username' => getenv('DB_USER') ?: 'webhngff_hanzalamaw',
    'password' => getenv('DB_PASS') ?: 'Hani2006!',
    'charset' => 'utf8mb4',
];
