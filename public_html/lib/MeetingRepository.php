<?php
/**
 * Meeting repository
 */

declare(strict_types=1);

class MeetingRepository
{
    public static function list(array $filters = []): array
    {
        self::ensureLinkColumn();
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(m.name LIKE ? OR m.location LIKE ? OR m.description LIKE ? OR m.meeting_link LIKE ?)';
            array_push($params, $q, $q, $q, $q);
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = db()->prepare("SELECT COUNT(*) FROM meetings m WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pager = paginate($total, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20));

        $sql = "SELECT m.*,
            (SELECT COUNT(*) FROM meeting_patients mp WHERE mp.meeting_id = m.id) AS patients_count,
            (SELECT COUNT(*) FROM meeting_patients mp2 WHERE mp2.meeting_id = m.id AND mp2.attended = 1) AS patients_attended
            FROM meetings m WHERE {$whereSql}
            ORDER BY m.meeting_date IS NULL, m.meeting_date DESC, m.id DESC
            LIMIT {$pager['per_page']} OFFSET {$pager['offset']}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll(), 'pagination' => $pager];
    }

    public static function find(int $id): ?array
    {
        self::ensureLinkColumn();
        $stmt = db()->prepare('SELECT * FROM meetings WHERE id = ?');
        $stmt->execute([$id]);
        $meeting = $stmt->fetch();
        if (!$meeting) {
            return null;
        }

        $p = db()->prepare('SELECT p.id, p.name, p.mother_name, p.number, p.country, p.city, p.occupation, mp.attended
            FROM meeting_patients mp JOIN patients p ON p.id = mp.patient_id WHERE mp.meeting_id = ? ORDER BY p.name');
        $p->execute([$id]);
        $meeting['patients'] = $p->fetchAll();

        return $meeting;
    }

    public static function create(array $data): int
    {
        self::ensureLinkColumn();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO meetings (name, meeting_date, start_time, end_time, location, description, notes, meeting_link)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                trim_str($data['name']),
                parse_date($data['meeting_date'] ?? null),
                null_if_empty($data['start_time'] ?? null),
                null_if_empty($data['end_time'] ?? null),
                null_if_empty($data['location'] ?? null),
                null_if_empty($data['description'] ?? null),
                null_if_empty($data['notes'] ?? null),
                self::cleanLink($data['meeting_link'] ?? null),
            ]);
            $id = (int) $pdo->lastInsertId();
            self::syncAttendees($pdo, $id, $data['patient_ids'] ?? []);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        self::ensureLinkColumn();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE meetings SET name = ?, meeting_date = ?, start_time = ?, end_time = ?, location = ?, description = ?, notes = ?, meeting_link = ? WHERE id = ?');
            $stmt->execute([
                trim_str($data['name']),
                parse_date($data['meeting_date'] ?? null),
                null_if_empty($data['start_time'] ?? null),
                null_if_empty($data['end_time'] ?? null),
                null_if_empty($data['location'] ?? null),
                null_if_empty($data['description'] ?? null),
                null_if_empty($data['notes'] ?? null),
                self::cleanLink($data['meeting_link'] ?? null),
                $id,
            ]);
            self::syncAttendees($pdo, $id, $data['patient_ids'] ?? []);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Expected patient attendees on create/edit. New patients start unmarked (attended=0).
     * Existing marks are preserved when the patient stays on the list.
     */
    private static function syncAttendees(PDO $pdo, int $meetingId, array $patientIds): void
    {
        $prevP = [];
        $stmtP = $pdo->prepare('SELECT patient_id, attended FROM meeting_patients WHERE meeting_id = ?');
        $stmtP->execute([$meetingId]);
        foreach ($stmtP->fetchAll() as $row) {
            $prevP[(int) $row['patient_id']] = (int) $row['attended'];
        }

        $pdo->prepare('DELETE FROM meeting_patients WHERE meeting_id = ?')->execute([$meetingId]);

        $pStmt = $pdo->prepare('INSERT INTO meeting_patients (meeting_id, patient_id, attended) VALUES (?, ?, ?)');
        foreach (array_unique(array_map('intval', $patientIds)) as $pid) {
            if ($pid > 0) {
                $pStmt->execute([$meetingId, $pid, $prevP[$pid] ?? 0]);
            }
        }
    }

    public static function setPatientAttendance(int $meetingId, int $patientId, bool $attended): bool
    {
        $check = db()->prepare('SELECT 1 FROM meeting_patients WHERE meeting_id = ? AND patient_id = ?');
        $check->execute([$meetingId, $patientId]);
        if (!$check->fetchColumn()) {
            return false;
        }
        $stmt = db()->prepare('UPDATE meeting_patients SET attended = ? WHERE meeting_id = ? AND patient_id = ?');
        $stmt->execute([$attended ? 1 : 0, $meetingId, $patientId]);
        return true;
    }

    /** @param int[]|null $ids null = all on meeting */
    public static function setPatientAttendanceBulk(int $meetingId, bool $attended, ?array $ids = null): int
    {
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
            if (!$ids) {
                return 0;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("UPDATE meeting_patients SET attended = ? WHERE meeting_id = ? AND patient_id IN ({$placeholders})");
            $stmt->execute(array_merge([$attended ? 1 : 0, $meetingId], $ids));
            return $stmt->rowCount();
        }
        $stmt = db()->prepare('UPDATE meeting_patients SET attended = ? WHERE meeting_id = ?');
        $stmt->execute([$attended ? 1 : 0, $meetingId]);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM meetings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function validate(array $data): array
    {
        $errors = [];
        if (trim_str($data['name'] ?? '') === '') {
            $errors[] = 'Meeting name is required.';
        }
        return $errors;
    }

    private static function cleanLink(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function ensureLinkColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = db()->query("SHOW COLUMNS FROM meetings LIKE 'meeting_link'")->fetch();
            if (!$col) {
                db()->exec('ALTER TABLE meetings ADD COLUMN meeting_link VARCHAR(1000) NULL');
            }
        } catch (Throwable $e) {
            log_error('ensure meeting_link column', $e);
        }
    }
}
