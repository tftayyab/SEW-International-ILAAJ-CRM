<?php
/**
 * Stream a patient image through the server (Drive download or remote URL).
 * Browser stores as blob and revokes on page close.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/ImageRepository.php';
require_once ROOT_PATH . '/lib/GoogleDriveService.php';

require_any_role();

$id = (int) input('id', 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing image id';
    exit;
}

$image = ImageRepository::find($id);
if (!$image) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Image not found';
    exit;
}

try {
    $bytes = null;
    $mime = 'image/jpeg';

    if (!empty($image['drive_file_id']) && GoogleDriveService::isConfigured()) {
        $file = GoogleDriveService::downloadFile($image['drive_file_id']);
        $bytes = $file['bytes'];
        $mime = $file['mime'];
    } else {
        $url = drive_display_url($image['image_url']);
        if ($url === '') {
            throw new RuntimeException('No image URL.');
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'ILAAJ-CRM/1.0',
            ]);
            $bytes = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
            curl_close($ch);
            if ($bytes === false || $status >= 400) {
                throw new RuntimeException('Could not fetch image URL.');
            }
            $mime = trim(explode(';', (string) $mime)[0]);
        } else {
            $bytes = file_get_contents($url);
            if ($bytes === false) {
                throw new RuntimeException('Could not fetch image URL.');
            }
        }
    }

    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
} catch (Throwable $e) {
    log_error('image_proxy', $e);
    http_response_code(502);
    header('Content-Type: text/plain');
    echo 'Unable to load image';
    exit;
}
