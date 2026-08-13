<?php
/**
 * Create a login account (no public registration).
 *
 *   C:\xampp\php\php.exe scripts/create_user.php USERNAME PASSWORD
 *
 * Or insert in MySQL:
 *   INSERT INTO users (username, password, is_active) VALUES ('admin', 'yourpassword', 1);
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/UserRepository.php';

$username = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php scripts/create_user.php USERNAME PASSWORD\n");
    exit(1);
}

try {
    $id = UserRepository::create($username, $password);
    echo "Created user #{$id} ({$username}). They can sign in, then pick a role.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
