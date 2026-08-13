<?php
/**
 * Shared helper functions
 */

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        // /pages/foo.php or /api/foo.php → project web root
        if (preg_match('#^(.*)/(?:pages|api)/[^/]+$#', $script, $m)) {
            $base = $m[1];
        } elseif (preg_match('#^(.*)/[^/]+\.php$#', $script, $m)) {
            // /index.php → project web root
            $base = $m[1];
        } else {
            $base = str_replace('\\', '/', dirname($script));
        }
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if ($base === '.' || $base === '\\' || $base === '/') {
            $base = '';
        }
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return $base;
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function redirect(string $url): void
{
    if (
        function_exists('current_role')
        && current_role()
        && !str_contains($url, 'login.php')
        && !str_contains($url, 'action=logout')
        && !str_contains($url, 'action=switch')
        && !preg_match('/index\.php(?:\?|$)/', $url)
    ) {
        $url = with_view($url);
    }
    header('Location: ' . $url);
    exit;
}

function is_api_request(): bool
{
    $uri = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($uri, '/api/');
}

function json_input(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if (!$raw) {
        $cached = [];
        return $cached;
    }
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(array $data = [], int $code = 200): void
{
    json_response(array_merge(['success' => true], $data), $code);
}

function json_error(string $message, int $code = 400, array $extra = []): void
{
    json_response(array_merge(['success' => false, 'error' => $message], $extra), $code);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function input(string $key, mixed $default = null): mixed
{
    $json = json_input();
    if (array_key_exists($key, $json)) {
        return $json[$key];
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function trim_str(?string $value): string
{
    return trim((string) $value);
}

/**
 * Force a string into valid UTF-8 (CSV/Excel often contain Windows-1252 bytes).
 */
function utf8_sanitize(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    if (mb_check_encoding($value, 'UTF-8')) {
        // Still strip invalid leftover sequences
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return $clean === false ? $value : $clean;
    }
    $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
    if ($converted !== false && $converted !== '') {
        return $converted;
    }
    $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    if (is_string($converted) && $converted !== '') {
        return $converted;
    }
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
}

function null_if_empty(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function parse_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $value = trim($value);

    // Excel serial date (numeric)
    if (is_numeric($value)) {
        $serial = (float) $value;
        if ($serial > 20000 && $serial < 80000) {
            $unix = (int) (($serial - 25569) * 86400);
            return gmdate('Y-m-d', $unix);
        }
    }

    $formats = [
        'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y', 'Y/m/d',
        'd M Y', 'j/n/Y', 'j-M-y', 'd-M-y', 'j-M-Y', 'd-M-Y',
        'j M Y', 'd-M-y', 'j/M/y',
    ];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat('!' . $fmt, $value);
        if ($dt instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $dt->format('Y-m-d');
            }
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function format_date(?string $date, string $format = 'j F Y'): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : $date;
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('j M Y, g:i A', $ts) : $datetime;
}

/**
 * Convert Google Drive share/view URLs into a browser-displayable image URL when possible.
 */
function drive_display_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    // Already a direct uc link
    if (str_contains($url, 'drive.google.com/uc?')) {
        return $url;
    }

    // /file/d/FILE_ID/
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }

    // open?id=FILE_ID
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }

    return $url;
}

function log_error(string $message, Throwable $e = null): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($e) {
        $line .= ' | ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
    error_log($line);
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function paginate(int $total, int $page, int $perPage): array
{
    $perPage = max(1, min(100, $perPage));
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function sender_label(string $senderType): string
{
    return $senderType === 'ameer_sahab' ? 'Ameer Sahab' : 'Patient';
}
