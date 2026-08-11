<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';
require_once ROOT_PATH . '/lib/MessageRepository.php';
require_once ROOT_PATH . '/lib/ExcelImporter.php';

require_editor();

$action = input('action', 'preview');

try {
    switch ($action) {
        case 'preview':
            require_csrf();
            if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                json_error('Please upload a valid Excel or CSV file.');
            }

            $file = $_FILES['file'];
            $original = $file['name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $allowed = ['xlsx', 'xls', 'csv'];
            if (!in_array($ext, $allowed, true)) {
                json_error('Invalid file type. Allowed: .xlsx, .xls, .csv');
            }

            if ($file['size'] > 15 * 1024 * 1024) {
                json_error('File is too large (max 15MB).');
            }

            if (!is_dir(UPLOAD_TEMP_PATH)) {
                mkdir(UPLOAD_TEMP_PATH, 0755, true);
            }

            $tmpName = 'import_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = UPLOAD_TEMP_PATH . DIRECTORY_SEPARATOR . $tmpName;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                json_error('Failed to store uploaded file.');
            }

            try {
                $preview = ExcelImporter::preview($dest, $original);
            } finally {
                @unlink($dest);
            }

            json_success(['preview' => $preview]);
            break;

        case 'confirm':
            require_csrf();
            $importId = (int) input('import_id');
            $resolutions = input('resolutions', []);
            if (!is_array($resolutions)) {
                $resolutions = [];
            }
            // Normalize keys
            $normalized = [];
            foreach ($resolutions as $key => $value) {
                $normalized[(int) $key] = $value;
            }
            $result = ExcelImporter::confirm($importId, $normalized);
            json_success(['result' => $result]);
            break;

        case 'cancel':
            require_csrf();
            ExcelImporter::cancel((int) input('import_id'));
            json_success(['message' => 'Import cancelled.']);
            break;

        case 'history':
            $rows = db()->query('SELECT id, filename, status, total_rows, imported_rows, new_patients, messages_created, errors_count, created_at, completed_at
                FROM excel_imports ORDER BY created_at DESC LIMIT 50')->fetchAll();
            json_success(['imports' => $rows]);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('import API', $e);
    $msg = $e->getMessage();
            // Safe user-facing messages for known errors
    if (str_contains($msg, 'PhpSpreadsheet') || str_contains($msg, 'Required columns') ||
        str_contains($msg, 'resolve duplicate') || str_contains($msg, 'Row ') ||
        str_contains($msg, 'preview') || str_contains($msg, 'empty') ||
        str_contains($msg, 'Import preview') || str_contains($msg, 'choose Create')) {
        json_error($msg, 400);
    }
    json_error('Import could not be completed. Please check the file and try again.', 500);
}
