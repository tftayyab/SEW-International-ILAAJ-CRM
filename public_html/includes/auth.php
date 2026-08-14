<?php
/**
 * JWT login + role selection.
 * Users are created in the database (see scripts/create_user.php).
 * After sign-in the landing screen still chooses Editor or Ameer Sahab.
 */

declare(strict_types=1);

require_once ROOT_PATH . '/lib/Jwt.php';
require_once ROOT_PATH . '/lib/UserRepository.php';

const ROLE_EDITOR = 'editor';
const ROLE_AMEER = 'ameer';
const AUTH_COOKIE = 'ilaaj_jwt';

function jwt_secret(): string
{
    $secret = env('JWT_SECRET', '');
    if ($secret !== null && strlen($secret) >= 16) {
        return $secret;
    }
    return hash('sha256', (string) env('DB_NAME', 'ilaaj_crm') . '|ilaaj-jwt-fallback');
}

function jwt_ttl_seconds(): int
{
    $hours = (int) env('JWT_TTL_HOURS', '12');
    if ($hours < 1) {
        $hours = 12;
    }
    return $hours * 3600;
}

function auth_cookie_path(): string
{
    $base = base_url();
    return $base === '' ? '/' : $base;
}

function auth_read_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return trim((string) ($_COOKIE[AUTH_COOKIE] ?? ''));
}

function auth_issue_token(array $user, ?string $role = null): string
{
    $ttl = jwt_ttl_seconds();
    $now = time();
    $token = Jwt::encode([
        'sub' => (int) $user['id'],
        'usr' => (string) $user['username'],
        'iat' => $now,
        'exp' => $now + $ttl,
    ], jwt_secret());

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(AUTH_COOKIE, $token, [
        'expires' => $now + $ttl,
        'path' => auth_cookie_path(),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[AUTH_COOKIE] = $token;
    current_user(true);

    return $token;
}

function auth_clear(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(AUTH_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => auth_cookie_path(),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[AUTH_COOKIE]);
    unset($_SESSION['role']);
    current_user(true);
}

function current_user(bool $refresh = false): ?array
{
    static $cached = false;
    if ($refresh) {
        $cached = false;
    }
    if ($cached !== false) {
        return $cached;
    }

    $cached = null;
    $token = auth_read_token();
    if ($token === '') {
        return null;
    }

    $payload = Jwt::decode($token, jwt_secret());
    if (!$payload || empty($payload['sub'])) {
        return null;
    }

    try {
        $row = UserRepository::find((int) $payload['sub']);
    } catch (Throwable $e) {
        log_error('auth current user', $e);
        return null;
    }

    if (!$row || !(int) $row['is_active']) {
        return null;
    }

    $cached = [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
    ];
    return $cached;
}

function current_username(): ?string
{
    $user = current_user();
    return $user['username'] ?? null;
}

function normalize_role(?string $role): ?string
{
    $role = strtolower(trim((string) $role));
    if ($role === ROLE_EDITOR || $role === ROLE_AMEER) {
        return $role;
    }
    return null;
}

/**
 * Role is per tab (query ?view= / X-App-Role), not the shared login cookie,
 * so Editor and Ameer Sahab can stay open side by side.
 */
function requested_role(): ?string
{
    $header = normalize_role($_SERVER['HTTP_X_APP_ROLE'] ?? null);
    if ($header) {
        return $header;
    }
    if (is_api_request()) {
        return null;
    }
    return normalize_role((string) ($_GET['view'] ?? $_POST['view'] ?? ''));
}

function current_role(): ?string
{
    if (!is_logged_in()) {
        return null;
    }
    return requested_role();
}

function with_view(string $url): string
{
    $role = current_role();
    if (!$role || preg_match('/(?:^|[?&])view=/', $url)) {
        return $url;
    }
    $sep = str_contains($url, '?') ? '&' : '?';
    return $url . $sep . 'view=' . rawurlencode($role);
}

function is_editor(): bool
{
    return current_role() === ROLE_EDITOR;
}

function is_ameer(): bool
{
    return current_role() === ROLE_AMEER;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }
    if (is_api_request()) {
        json_error('Please sign in.', 401);
    }
    redirect(base_url('pages/login.php'));
}

function require_role(string $role): void
{
    require_login();
    if (current_role() !== $role) {
        if (is_api_request()) {
            json_error('Unauthorized. Editor access required.', 403);
        }
        redirect(base_url('index.php'));
    }
}

function require_editor(): void
{
    require_role(ROLE_EDITOR);
}

function require_ameer(): void
{
    require_role(ROLE_AMEER);
}

function require_any_role(): void
{
    require_login();
    if (!current_role()) {
        if (is_api_request()) {
            json_error('Please select a role from the home screen.', 401);
        }
        redirect(base_url('index.php'));
    }
}

function set_role(string $role): void
{
    // Role is chosen per tab via ?view= — do not write it into the shared JWT cookie.
}

function clear_role(): void
{
    unset($_SESSION['role']);
}

function attempt_login(string $username, string $password): ?array
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return null;
    }

    $row = UserRepository::findByUsername($username);
    if (!$row || !(int) $row['is_active']) {
        return null;
    }
    if ((string) $row['password'] !== $password) {
        return null;
    }

    auth_issue_token($row, null);
    return current_user();
}
