<?php
/**
 * Patient repository
 */

declare(strict_types=1);

class PatientRepository
{
    public static function find(int $id, bool $includeArchived = false): ?array
    {
        $sql = 'SELECT p.*,
            (SELECT pi.id FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_id,
            (SELECT pi.image_url FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_url,
            (SELECT COUNT(*) FROM patient_images pi2 WHERE pi2.patient_id = p.id) AS image_count,
            (SELECT m.message_text FROM messages m WHERE m.patient_id = p.id ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC LIMIT 1) AS last_message,
            (SELECT COALESCE(m2.message_date, DATE(m2.created_at)) FROM messages m2 WHERE m2.patient_id = p.id ORDER BY m2.message_date IS NULL, m2.message_date DESC, m2.import_order DESC, m2.id DESC LIMIT 1) AS last_activity
            FROM patients p WHERE p.id = ?';
        if (!$includeArchived) {
            $sql .= ' AND p.is_archived = 0';
        }
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function search(array $filters = []): array
    {
        $where = ['p.is_archived = 0'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(p.name LIKE ? OR p.mother_name LIKE ? OR p.number LIKE ? OR p.country LIKE ? OR p.city LIKE ? OR p.occupation LIKE ? OR p.notes LIKE ?)';
            array_push($params, $q, $q, $q, $q, $q, $q, $q);
        }

        foreach (['name', 'mother_name', 'number', 'country', 'city', 'occupation'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = "p.{$field} LIKE ?";
                $params[] = '%' . $filters[$field] . '%';
            }
        }

        if (!empty($filters['exact_number'])) {
            $where[] = 'p.number = ?';
            $params[] = $filters['exact_number'];
        }

        $allowedSort = [
            'name' => 'p.name',
            'number' => 'p.number',
            'country' => 'p.country',
            'city' => 'p.city',
            'occupation' => 'p.occupation',
            'created_at' => 'p.created_at',
            'last_activity' => 'last_activity',
        ];
        $sort = $filters['sort'] ?? 'last_activity';
        $sortCol = $allowedSort[$sort] ?? 'last_activity';
        $dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $whereSql = implode(' AND ', $where);

        $countStmt = db()->prepare("SELECT COUNT(*) FROM patients p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $pager = paginate($total, $page, $perPage);

        $nullsLast = $sortCol === 'last_activity' ? 'last_activity IS NULL, ' : '';

        $sql = "SELECT p.*,
            (SELECT pi.id FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_id,
            (SELECT pi.image_url FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_url,
            (SELECT COUNT(*) FROM patient_images pi2 WHERE pi2.patient_id = p.id) AS image_count,
            (SELECT m.message_text FROM messages m WHERE m.patient_id = p.id ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC LIMIT 1) AS last_message,
            (SELECT COALESCE(m2.message_date, DATE(m2.created_at)) FROM messages m2 WHERE m2.patient_id = p.id ORDER BY m2.message_date IS NULL, m2.message_date DESC, m2.import_order DESC, m2.id DESC LIMIT 1) AS last_activity
            FROM patients p
            WHERE {$whereSql}
            ORDER BY {$nullsLast}{$sortCol} {$dir}, p.id DESC
            LIMIT {$pager['per_page']} OFFSET {$pager['offset']}";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['data' => $rows, 'pagination' => $pager];
    }

    public static function findByNumber(string $number): array
    {
        $stmt = db()->prepare('SELECT p.*, 
            (SELECT pi.image_url FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_url
            FROM patients p WHERE p.number = ? AND p.is_archived = 0 ORDER BY p.name ASC, p.id ASC');
        $stmt->execute([$number]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO patients (name, mother_name, number, country, city, occupation, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim_str($data['name']),
            null_if_empty($data['mother_name'] ?? null),
            trim_str($data['number']),
            null_if_empty($data['country'] ?? null),
            null_if_empty($data['city'] ?? null),
            null_if_empty($data['occupation'] ?? null),
            null_if_empty($data['notes'] ?? null),
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = db()->prepare('UPDATE patients SET name = ?, mother_name = ?, number = ?, country = ?, city = ?, occupation = ?, notes = ? WHERE id = ? AND is_archived = 0');
        return $stmt->execute([
            trim_str($data['name']),
            null_if_empty($data['mother_name'] ?? null),
            trim_str($data['number']),
            null_if_empty($data['country'] ?? null),
            null_if_empty($data['city'] ?? null),
            null_if_empty($data['occupation'] ?? null),
            null_if_empty($data['notes'] ?? null),
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Clear active patient if pointing here
            $pdo->prepare('UPDATE system_state SET active_patient_id = NULL WHERE active_patient_id = ?')->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM patients WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function validate(array $data): array
    {
        $errors = [];
        if (trim_str($data['name'] ?? '') === '') {
            $errors[] = 'Patient name is required.';
        }
        if (trim_str($data['number'] ?? '') === '') {
            $errors[] = 'Phone number is required.';
        }
        return $errors;
    }
}
