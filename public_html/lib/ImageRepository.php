<?php
/**
 * Patient image repository
 */

declare(strict_types=1);

class ImageRepository
{
    public static function forPatient(int $patientId): array
    {
        $stmt = db()->prepare('SELECT * FROM patient_images WHERE patient_id = ? ORDER BY is_profile_picture DESC, id ASC');
        $stmt->execute([$patientId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['display_url'] = drive_display_url($row['image_url']);
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM patient_images WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['display_url'] = drive_display_url($row['image_url']);
        }
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $patientId = (int) $data['patient_id'];
            $isProfile = !empty($data['is_profile_picture']) ? 1 : 0;
            if ($isProfile) {
                $pdo->prepare('UPDATE patient_images SET is_profile_picture = 0 WHERE patient_id = ?')->execute([$patientId]);
            }
            $stmt = $pdo->prepare('INSERT INTO patient_images (patient_id, image_url, drive_file_id, description, is_profile_picture) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $patientId,
                trim_str($data['image_url']),
                null_if_empty($data['drive_file_id'] ?? null),
                null_if_empty($data['description'] ?? null),
                $isProfile,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $isProfile = !empty($data['is_profile_picture']) ? 1 : 0;
            if ($isProfile) {
                $pdo->prepare('UPDATE patient_images SET is_profile_picture = 0 WHERE patient_id = ?')->execute([$existing['patient_id']]);
            }
            $imageUrl = array_key_exists('image_url', $data) ? trim_str((string) $data['image_url']) : $existing['image_url'];
            $driveFileId = array_key_exists('drive_file_id', $data)
                ? null_if_empty($data['drive_file_id'] ?? null)
                : ($existing['drive_file_id'] ?? null);
            $stmt = $pdo->prepare('UPDATE patient_images SET image_url = ?, drive_file_id = ?, description = ?, is_profile_picture = ? WHERE id = ?');
            $stmt->execute([
                $imageUrl,
                $driveFileId,
                null_if_empty($data['description'] ?? null),
                $isProfile,
                $id,
            ]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $stmt = db()->prepare('DELETE FROM patient_images WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0 && !empty($existing['drive_file_id'])) {
            require_once ROOT_PATH . '/lib/GoogleDriveService.php';
            GoogleDriveService::deleteFile($existing['drive_file_id']);
        }
        return $stmt->rowCount() > 0;
    }

    public static function validate(array $data, bool $requireUrl = true): array
    {
        $errors = [];
        if (empty($data['patient_id'])) {
            $errors[] = 'Patient is required.';
        }
        if ($requireUrl && trim_str($data['image_url'] ?? '') === '') {
            $errors[] = 'Image URL is required.';
        }
        return $errors;
    }
}
