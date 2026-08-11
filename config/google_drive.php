<?php
/**
 * Google Drive OAuth settings — all from .env (no JSON key file).
 */
$folderRaw = trim((string) (env('GOOGLE_DRIVE_FOLDER_ID', '') ?? ''));
$folderId = $folderRaw;
if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $folderRaw, $m)) {
    $folderId = $m[1];
} elseif (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $folderRaw, $m)) {
    $folderId = $m[1];
}

return [
    'client_id' => env('GOOGLE_CLIENT_ID', '') ?? '',
    'client_secret' => env('GOOGLE_CLIENT_SECRET', '') ?? '',
    'refresh_token' => env('GOOGLE_REFRESH_TOKEN', '') ?? '',
    'folder_id' => $folderId,
    'make_public' => (env('GOOGLE_DRIVE_MAKE_PUBLIC', 'true') ?? 'true') !== 'false',
    'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'max_bytes' => 8 * 1024 * 1024,
];
