<?php
/**
 * Google Drive via OAuth 2.0 refresh token.
 * All secrets live in .env — no JSON key file required for deploy.
 */

declare(strict_types=1);

class GoogleDriveService
{
    private static ?array $config = null;
    private static ?string $accessToken = null;
    private static int $tokenExpiresAt = 0;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require ROOT_PATH . '/config/google_drive.php';
        }
        return self::$config;
    }

    public static function isConfigured(): bool
    {
        $cfg = self::config();
        return trim((string) ($cfg['folder_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== ''
            && trim((string) ($cfg['refresh_token'] ?? '')) !== '';
    }

    public static function canStartOAuth(): bool
    {
        $cfg = self::config();
        return trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== '';
    }

    public static function status(): array
    {
        $cfg = self::config();
        return [
            'configured' => self::isConfigured(),
            'has_client_id' => trim((string) ($cfg['client_id'] ?? '')) !== '',
            'has_client_secret' => trim((string) ($cfg['client_secret'] ?? '')) !== '',
            'has_refresh_token' => trim((string) ($cfg['refresh_token'] ?? '')) !== '',
            'has_folder_id' => trim((string) ($cfg['folder_id'] ?? '')) !== '',
            'redirect_uri' => self::oauthRedirectUri(),
        ];
    }

    public static function oauthRedirectUri(): string
    {
        $fromEnv = trim((string) (env('GOOGLE_OAUTH_REDIRECT_URI', '') ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        return $scheme . '://' . $host . base_url('api/google_oauth.php');
    }

    public static function authorizationUrl(string $state): string
    {
        $cfg = self::config();
        if (!self::canStartOAuth()) {
            throw new RuntimeException('Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env first.');
        }

        $params = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => self::oauthRedirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens. Returns refresh_token when Google provides it.
     *
     * @return array{access_token?:string,refresh_token?:string,expires_in?:int,raw:array}
     */
    public static function exchangeCode(string $code): array
    {
        $cfg = self::config();
        $body = http_build_query([
            'code' => $code,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => self::oauthRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        $response = self::httpRequest(
            'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            $body,
            'POST'
        );

        if (($response['status'] ?? 0) >= 400 || empty($response['json']['access_token'])) {
            $msg = $response['json']['error_description'] ?? $response['json']['error'] ?? 'token exchange failed';
            throw new RuntimeException('Google OAuth failed: ' . $msg);
        }

        return [
            'access_token' => $response['json']['access_token'] ?? null,
            'refresh_token' => $response['json']['refresh_token'] ?? null,
            'expires_in' => isset($response['json']['expires_in']) ? (int) $response['json']['expires_in'] : null,
            'raw' => $response['json'],
        ];
    }

    /**
     * @return array{file_id:string,image_url:string,display_url:string,name:string}
     */
    public static function uploadImage(string $tmpPath, string $originalName, string $mimeType): array
    {
        if (!self::isConfigured()) {
            throw new RuntimeException(
                'Google Drive is not configured. Set OAuth values in .env and connect via Drive Setup (see docs/GOOGLE_DRIVE_SETUP.md).'
            );
        }

        $cfg = self::config();
        $allowed = $cfg['allowed_mime'] ?? [];
        if ($allowed && !in_array($mimeType, $allowed, true)) {
            throw new RuntimeException('Only JPG, PNG, GIF, or WebP images are allowed.');
        }

        $max = (int) ($cfg['max_bytes'] ?? (8 * 1024 * 1024));
        $size = filesize($tmpPath);
        if ($size === false || $size <= 0) {
            throw new RuntimeException('Uploaded file is empty or unreadable.');
        }
        if ($size > $max) {
            throw new RuntimeException('Image is too large (max ' . round($max / 1048576, 1) . ' MB).');
        }

        $token = self::getAccessToken();
        $safeName = self::safeFilename($originalName);
        $binary = file_get_contents($tmpPath);
        if ($binary === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        $metadata = json_encode([
            'name' => $safeName,
            'parents' => [$cfg['folder_id']],
        ], JSON_UNESCAPED_SLASHES);

        $boundary = 'ilaaj_' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . $binary . "\r\n"
            . "--{$boundary}--";

        $response = self::httpRequest(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink',
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/related; boundary=' . $boundary,
            ],
            $body,
            'POST'
        );

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            $msg = $response['json']['error']['message'] ?? ('HTTP ' . ($response['status'] ?? '?'));
            throw new RuntimeException('Drive upload failed: ' . $msg);
        }

        $fileId = $response['json']['id'] ?? null;
        if (!$fileId) {
            throw new RuntimeException('Drive upload failed: no file ID returned.');
        }

        if (!empty($cfg['make_public'])) {
            self::makePublic($fileId, $token);
        }

        return [
            'file_id' => $fileId,
            'image_url' => 'https://drive.google.com/file/d/' . $fileId . '/view',
            'display_url' => 'https://drive.google.com/uc?export=view&id=' . $fileId,
            'name' => $response['json']['name'] ?? $safeName,
        ];
    }

    public static function deleteFile(?string $fileId): void
    {
        $fileId = trim((string) $fileId);
        if ($fileId === '' || !self::isConfigured()) {
            return;
        }

        try {
            $token = self::getAccessToken();
            self::httpRequest(
                'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId),
                ['Authorization: Bearer ' . $token],
                null,
                'DELETE'
            );
        } catch (Throwable $e) {
            log_error('Drive delete failed for ' . $fileId, $e);
        }
    }

    public static function downloadFile(string $fileId): array
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }
        $token = self::getAccessToken();
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media';

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required to load Drive images.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Failed to download Drive file: ' . $err);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Drive download failed (HTTP ' . $status . ').');
        }

        // Strip charset from mime if present
        $mime = trim(explode(';', (string) $mime)[0]);
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = 'image/jpeg';
        }

        return ['bytes' => $raw, 'mime' => $mime];
    }

    private static function makePublic(string $fileId, string $token): void
    {
        $body = json_encode(['role' => 'reader', 'type' => 'anyone']);
        $response = self::httpRequest(
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/permissions',
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            $body,
            'POST'
        );
        if (($response['status'] ?? 0) >= 400) {
            $msg = $response['json']['error']['message'] ?? 'permission error';
            log_error('Drive permission failed: ' . $msg);
        }
    }

    private static function getAccessToken(): string
    {
        if (self::$accessToken && time() < self::$tokenExpiresAt - 60) {
            return self::$accessToken;
        }

        $cfg = self::config();
        $body = http_build_query([
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $cfg['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        $response = self::httpRequest(
            'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            $body,
            'POST'
        );

        $access = $response['json']['access_token'] ?? null;
        if (!$access) {
            $msg = $response['json']['error_description'] ?? $response['json']['error'] ?? 'token error';
            throw new RuntimeException('Google auth failed (refresh token): ' . $msg);
        }

        self::$accessToken = $access;
        self::$tokenExpiresAt = time() + (int) ($response['json']['expires_in'] ?? 3600);
        return self::$accessToken;
    }

    private static function httpRequest(string $url, array $headers, ?string $body, string $method): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 120,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new RuntimeException('Network error talking to Google: ' . $err);
            }
        } else {
            $opts = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'content' => $body ?? '',
                    'ignore_errors' => true,
                    'timeout' => 120,
                ],
            ];
            $raw = file_get_contents($url, false, stream_context_create($opts));
            if ($raw === false) {
                throw new RuntimeException('Network error talking to Google.');
            }
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }

        $json = json_decode((string) $raw, true);
        return [
            'status' => $status,
            'raw' => $raw,
            'json' => is_array($json) ? $json : [],
        ];
    }

    private static function safeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'image.jpg';
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) {
            $name .= '.jpg';
        }
        return date('Ymd_His') . '_' . $name;
    }

    /** Accept raw folder ID or full Drive folder URL. */
    public static function normalizeFolderId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $value, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $value, $m)) {
            return $m[1];
        }
        return $value;
    }
}
