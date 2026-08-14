<?php
/**
 * PDO database connection
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require ROOT_PATH . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        if (php_sapi_name() === 'cli') {
            throw $e;
        }
        http_response_code(500);
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed. Check config/database.php.']);
            exit;
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database Error</title></head><body style="font-family:sans-serif;padding:2rem;">';
        echo '<h1>Database connection failed</h1>';
        echo '<p>Please import <code>database/schema.sql</code> and update <code>config/database.php</code>.</p>';
        echo '</body></html>';
        exit;
    }

    return $pdo;
}
