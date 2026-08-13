<?php
/**
 * Application users — created in the database, not via a public register screen.
 */

declare(strict_types=1);

class UserRepository
{
    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(120) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_users_username (username)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $old = db()->query("SHOW COLUMNS FROM users LIKE 'password_hash'")->fetch();
            if ($old) {
                db()->exec('ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL');
            }
        } catch (Throwable $e) {
            log_error('ensure users table', $e);
        }
    }

    public static function find(int $id): ?array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT id, username, password, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT id, username, password, is_active FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $username, string $password): int
    {
        self::ensureTable();
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Username and password are required.');
        }
        if (self::findByUsername($username)) {
            throw new InvalidArgumentException('That username is already taken.');
        }
        $stmt = db()->prepare('INSERT INTO users (username, password, is_active) VALUES (?, ?, 1)');
        $stmt->execute([$username, $password]);
        return (int) db()->lastInsertId();
    }
}
