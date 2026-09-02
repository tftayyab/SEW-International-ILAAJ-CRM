<?php
/**
 * Patient repository
 */

declare(strict_types=1);

class PatientRepository
{
    public static function ensureResponseSentColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = db()->query("SHOW COLUMNS FROM patients LIKE 'response_sent'")->fetch();
            if (!$col) {
                db()->exec('ALTER TABLE patients ADD COLUMN response_sent TINYINT(1) NOT NULL DEFAULT 1 AFTER is_archived');
                db()->exec('UPDATE patients SET response_sent = 1');
                db()->exec('CREATE INDEX idx_patients_response_sent ON patients (response_sent)');
            }
        } catch (Throwable $e) {
            log_error('ensure response_sent column', $e);
        }
    }

    /** Whether the latest Ameer Sahab response is visible to Ameer Sahab. */
    public static function setResponseSent(int $id, bool $sent): bool
    {
        self::ensureResponseSentColumn();
        $stmt = db()->prepare('UPDATE patients SET response_sent = ? WHERE id = ? AND is_archived = 0');
        return $stmt->execute([$sent ? 1 : 0, $id]);
    }

    public static function find(int $id, bool $includeArchived = false): ?array
    {
        self::ensureResponseSentColumn();
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
        self::ensureResponseSentColumn();
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

    /**
     * Patients whose most recent message came from the patient (Ameer Sahab hasn't replied yet).
     * Ordered by latest patient message first. Returns rows with `last_message`,
     * `last_message_date`, `last_activity`, and profile image info.
     */
    public static function pendingResponses(array $filters = []): array
    {
        $where = ['p.is_archived = 0'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(p.name LIKE ? OR p.mother_name LIKE ? OR p.number LIKE ?)';
            array_push($params, $q, $q, $q);
        }

        // Sub-select picks each patient's most recent message id using the same ordering as elsewhere.
        // Then we join and filter by sender_type = 'patient'.
        $lastMsgId = "(SELECT m.id FROM messages m WHERE m.patient_id = p.id
            ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC
            LIMIT 1)";

        $whereSql = implode(' AND ', $where);

        // Pending when the latest message is from the patient, or the latest Ameer reply is still unsent.
        $countSql = "SELECT COUNT(*)
            FROM patients p
            JOIN messages last_msg ON last_msg.id = {$lastMsgId}
            WHERE {$whereSql} AND (last_msg.sender_type = 'patient'
                OR (last_msg.sender_type = 'ameer_sahab' AND p.response_sent = 0))";
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 24);
        $pager = paginate($total, $page, $perPage);

        $sql = "SELECT p.id, p.name, p.mother_name,
            last_msg.message_text AS last_message,
            last_msg.message_date AS last_message_date,
            last_msg.created_at AS last_message_created_at,
            COALESCE(last_msg.message_date, DATE(last_msg.created_at)) AS last_activity,
            (SELECT pi.id FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_id,
            (SELECT pi.image_url FROM patient_images pi WHERE pi.patient_id = p.id AND pi.is_profile_picture = 1 LIMIT 1) AS profile_image_url
            FROM patients p
            JOIN messages last_msg ON last_msg.id = {$lastMsgId}
            WHERE {$whereSql} AND (last_msg.sender_type = 'patient'
                OR (last_msg.sender_type = 'ameer_sahab' AND p.response_sent = 0))
            ORDER BY last_msg.message_date IS NULL,
                     last_msg.message_date DESC,
                     last_msg.import_order DESC,
                     last_msg.id DESC
            LIMIT {$pager['per_page']} OFFSET {$pager['offset']}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['data' => $rows, 'pagination' => $pager];
    }

    /**
     * Previous/next patient within the same list (patients or pending), including across pages.
     */
    public static function navigateNeighbor(string $context, int $id, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 50)));

        $fetch = $context === 'pending'
            ? static fn(int $p): array => self::pendingResponses(array_merge($filters, ['page' => $p, 'per_page' => $perPage]))
            : static fn(int $p): array => self::search(array_merge($filters, ['page' => $p, 'per_page' => $perPage]));

        $result = $fetch($page);
        $ids = array_map('intval', array_column($result['data'], 'id'));
        $idx = array_search($id, $ids, true);
        $totalPages = (int) ($result['pagination']['total_pages'] ?? 1);
        $total = (int) ($result['pagination']['total'] ?? 0);

        if ($idx === false) {
            return [
                'prev_id' => null,
                'next_id' => null,
                'prev_page' => $page,
                'next_page' => $page,
                'position' => null,
                'total' => $total,
            ];
        }

        $prevId = null;
        $nextId = null;
        $prevPage = $page;
        $nextPage = $page;

        if ($idx > 0) {
            $prevId = $ids[$idx - 1];
        } elseif ($page > 1) {
            $prevResult = $fetch($page - 1);
            $prevRows = $prevResult['data'] ?? [];
            if ($prevRows) {
                $prevId = (int) end($prevRows)['id'];
                $prevPage = $page - 1;
            }
        }

        if ($idx < count($ids) - 1) {
            $nextId = $ids[$idx + 1];
        } elseif ($page < $totalPages) {
            $nextResult = $fetch($page + 1);
            $nextRows = $nextResult['data'] ?? [];
            if ($nextRows) {
                $nextId = (int) $nextRows[0]['id'];
                $nextPage = $page + 1;
            }
        }

        return [
            'prev_id' => $prevId,
            'next_id' => $nextId,
            'prev_page' => $prevPage,
            'next_page' => $nextPage,
            'position' => (($page - 1) * $perPage) + $idx + 1,
            'total' => $total,
        ];
    }

    /**
     * Unfiltered count of patients awaiting an Ameer Sahab reply.
     */
    public static function pendingCount(): int
    {
        $lastMsgId = "(SELECT m.id FROM messages m WHERE m.patient_id = p.id
            ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC
            LIMIT 1)";
        $sql = "SELECT COUNT(*)
            FROM patients p
            JOIN messages last_msg ON last_msg.id = {$lastMsgId}
            WHERE p.is_archived = 0 AND (last_msg.sender_type = 'patient'
                OR (last_msg.sender_type = 'ameer_sahab' AND p.response_sent = 0))";
        return (int) db()->query($sql)->fetchColumn();
    }

    /**
     * Patients with an unsent Ameer Sahab response (response_sent = 0).
     */
    public static function unsentResponses(array $filters = []): array
    {
        self::ensureResponseSentColumn();
        $where = ['p.is_archived = 0', 'p.response_sent = 0'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(p.name LIKE ? OR p.mother_name LIKE ? OR p.number LIKE ?)';
            array_push($params, $q, $q, $q);
        }

        $lastAmeerMsg = "(SELECT m.id FROM messages m WHERE m.patient_id = p.id AND m.sender_type = 'ameer_sahab'
            ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC
            LIMIT 1)";

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM patients p WHERE {$whereSql}";
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 24);
        $pager = paginate($total, $page, $perPage);

        $sql = "SELECT p.id, p.name, p.number, p.response_sent,
            ameer_msg.message_text AS response_text,
            ameer_msg.message_date AS response_date,
            COALESCE(ameer_msg.message_date, DATE(ameer_msg.created_at)) AS last_activity
            FROM patients p
            LEFT JOIN messages ameer_msg ON ameer_msg.id = {$lastAmeerMsg}
            WHERE {$whereSql}
            ORDER BY ameer_msg.message_date IS NULL,
                     ameer_msg.message_date DESC,
                     ameer_msg.import_order DESC,
                     ameer_msg.id DESC,
                     p.id DESC
            LIMIT {$pager['per_page']} OFFSET {$pager['offset']}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'pagination' => $pager];
    }

    public static function unsentCount(): int
    {
        self::ensureResponseSentColumn();
        return (int) db()->query('SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND response_sent = 0')->fetchColumn();
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

    /** Distinct country or city values already stored on patients (for autocomplete). */
    public static function distinctValues(string $field, ?string $q = null, int $limit = 30): array
    {
        if (!in_array($field, ['country', 'city'], true)) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $sql = "SELECT DISTINCT TRIM(p.{$field}) AS value
            FROM patients p
            WHERE p.is_archived = 0 AND TRIM(COALESCE(p.{$field}, '')) != ''";
        $params = [];

        if ($q !== null && trim_str($q) !== '') {
            $sql .= " AND p.{$field} LIKE ?";
            $params[] = trim_str($q) . '%';
        }

        $sql .= ' ORDER BY value ASC LIMIT ' . $limit;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(), 'value');
    }
}
