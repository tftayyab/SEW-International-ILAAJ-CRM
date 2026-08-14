<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/ImageRepository.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';
require_once ROOT_PATH . '/lib/GoogleDriveService.php';

require_any_role();

$action = input('action', 'list');

try {
    switch ($action) {
        case 'status':
            require_editor();
            json_success(['drive' => GoogleDriveService::status()]);
            break;

        case 'list':
            $patientId = (int) input('patient_id');
            if (!PatientRepository::find($patientId)) {
                json_error('Patient not found.', 404);
            }
            json_success(['images' => ImageRepository::forPatient($patientId)]);
            break;

        case 'upload':
            require_editor();
            require_csrf();

            $patientId = (int) ($_POST['patient_id'] ?? input('patient_id'));
            if (!PatientRepository::find($patientId)) {
                json_error('Patient not found.', 404);
            }
            if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
                $messages = [
                    UPLOAD_ERR_INI_SIZE => 'Image exceeds server upload limit.',
                    UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
                    UPLOAD_ERR_PARTIAL => 'Upload was incomplete. Try again.',
                    UPLOAD_ERR_NO_FILE => 'Please choose an image to upload.',
                ];
                json_error($messages[$code] ?? 'Upload failed.');
            }

            $file = $_FILES['image'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');

            $uploaded = GoogleDriveService::uploadImage(
                $file['tmp_name'],
                $file['name'] ?? 'image.jpg',
                $mime
            );

            $id = ImageRepository::create([
                'patient_id' => $patientId,
                'image_url' => $uploaded['image_url'],
                'drive_file_id' => $uploaded['file_id'],
                'description' => $_POST['description'] ?? input('description'),
                'is_profile_picture' => $_POST['is_profile_picture'] ?? input('is_profile_picture'),
            ]);

            json_success([
                'id' => $id,
                'image' => ImageRepository::find($id),
                'drive' => $uploaded,
            ], 201);
            break;

        case 'create':
            // Manual URL fallback (optional)
            require_editor();
            require_csrf();
            $data = [
                'patient_id' => (int) input('patient_id'),
                'image_url' => input('image_url'),
                'drive_file_id' => input('drive_file_id'),
                'description' => input('description'),
                'is_profile_picture' => input('is_profile_picture'),
            ];
            $errors = ImageRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            if (!PatientRepository::find((int) $data['patient_id'])) {
                json_error('Patient not found.', 404);
            }
            $id = ImageRepository::create($data);
            json_success(['id' => $id, 'image' => ImageRepository::find($id)], 201);
            break;

        case 'update':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            $existing = ImageRepository::find($id);
            if (!$existing) {
                json_error('Image not found.', 404);
            }
            $data = [
                'image_url' => input('image_url', $existing['image_url']),
                'drive_file_id' => input('drive_file_id', $existing['drive_file_id'] ?? null),
                'description' => input('description', $existing['description']),
                'is_profile_picture' => input('is_profile_picture', $existing['is_profile_picture']),
            ];
            ImageRepository::update($id, $data);
            json_success(['image' => ImageRepository::find($id)]);
            break;

        case 'delete':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!ImageRepository::find($id)) {
                json_error('Image not found.', 404);
            }
            ImageRepository::delete($id);
            json_success(['message' => 'Image deleted.']);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('images API', $e);
    $msg = $e->getMessage();
    if (str_contains($msg, 'Google Drive') || str_contains($msg, 'Drive') || str_contains($msg, 'configured') || str_contains($msg, 'image')) {
        json_error($msg, 400);
    }
    json_error('An unexpected error occurred.', 500);
}
