<?php
/**
 * Workers & Meetings repositories
 */

declare(strict_types=1);

class WorkerRepository
{
    public static function all(?string $q = null): array
    {
        if ($q) {
            $like = '%' . $q . '%';
            $stmt = db()->prepare('SELECT * FROM workers WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC');
            $stmt->execute([$like, $like]);
            return $stmt->fetchAll();
        }
        return db()->query('SELECT * FROM workers ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM workers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO workers (name, phone) VALUES (?, ?)');
        $stmt->execute([trim_str($data['name']), null_if_empty($data['phone'] ?? null)]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = db()->prepare('UPDATE workers SET name = ?, phone = ? WHERE id = ?');
        return $stmt->execute([trim_str($data['name']), null_if_empty($data['phone'] ?? null), $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM workers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function validate(array $data): array
    {
        $errors = [];
        if (trim_str($data['name'] ?? '') === '') {
            $errors[] = 'Worker name is required.';
        }
        return $errors;
    }
}

class MeetingRepository
{
    public static function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(m.name LIKE ? OR m.location LIKE ? OR m.description LIKE ?)';
            array_push($params, $q, $q, $q);
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = db()->prepare("SELECT COUNT(*) FROM meetings m WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pager = paginate($total, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20));

        $sql = "SELECT m.*,
            (SELECT COUNT(*) FROM meeting_workers mw WHERE mw.meeting_id = m.id) AS workers_count,
            (SELECT COUNT(*) FROM meeting_workers mw2 WHERE mw2.meeting_id = m.id AND mw2.attended = 1) AS workers_attended,
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
        $stmt = db()->prepare('SELECT * FROM meetings WHERE id = ?');
        $stmt->execute([$id]);
        $meeting = $stmt->fetch();
        if (!$meeting) {
            return null;
        }

        $w = db()->prepare('SELECT w.*, mw.attended FROM meeting_workers mw JOIN workers w ON w.id = mw.worker_id WHERE mw.meeting_id = ? ORDER BY w.name');
        $w->execute([$id]);
        $meeting['workers'] = $w->fetchAll();

        $p = db()->prepare('SELECT p.id, p.name, p.mother_name, p.number, p.country, p.city, p.occupation, mp.attended
            FROM meeting_patients mp JOIN patients p ON p.id = mp.patient_id WHERE mp.meeting_id = ? ORDER BY p.name');
        $p->execute([$id]);
        $meeting['patients'] = $p->fetchAll();

        return $meeting;
    }

    public static function create(array $data): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO meetings (name, meeting_date, start_time, end_time, location, description, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                trim_str($data['name']),
                parse_date($data['meeting_date'] ?? null),
                null_if_empty($data['start_time'] ?? null),
                null_if_empty($data['end_time'] ?? null),
                null_if_empty($data['location'] ?? null),
                null_if_empty($data['description'] ?? null),
                null_if_empty($data['notes'] ?? null),
            ]);
            $id = (int) $pdo->lastInsertId();
            self::syncAttendees($pdo, $id, $data['worker_ids'] ?? [], $data['patient_ids'] ?? []);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE meetings SET name = ?, meeting_date = ?, start_time = ?, end_time = ?, location = ?, description = ?, notes = ? WHERE id = ?');
            $stmt->execute([
                trim_str($data['name']),
                parse_date($data['meeting_date'] ?? null),
                null_if_empty($data['start_time'] ?? null),
                null_if_empty($data['end_time'] ?? null),
                null_if_empty($data['location'] ?? null),
                null_if_empty($data['description'] ?? null),
                null_if_empty($data['notes'] ?? null),
                $id,
            ]);
            self::syncAttendees($pdo, $id, $data['worker_ids'] ?? [], $data['patient_ids'] ?? []);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Expected attendees on create/edit. New people start as not-yet-marked (attended=0).
     * Existing attendance marks are preserved when the person stays on the list.
     */
    private static function syncAttendees(PDO $pdo, int $meetingId, array $workerIds, array $patientIds): void
    {
        $prevW = [];
        $stmtW = $pdo->prepare('SELECT worker_id, attended FROM meeting_workers WHERE meeting_id = ?');
        $stmtW->execute([$meetingId]);
        foreach ($stmtW->fetchAll() as $row) {
            $prevW[(int) $row['worker_id']] = (int) $row['attended'];
        }

        $prevP = [];
        $stmtP = $pdo->prepare('SELECT patient_id, attended FROM meeting_patients WHERE meeting_id = ?');
        $stmtP->execute([$meetingId]);
        foreach ($stmtP->fetchAll() as $row) {
            $prevP[(int) $row['patient_id']] = (int) $row['attended'];
        }

        $pdo->prepare('DELETE FROM meeting_workers WHERE meeting_id = ?')->execute([$meetingId]);
        $pdo->prepare('DELETE FROM meeting_patients WHERE meeting_id = ?')->execute([$meetingId]);

        $wStmt = $pdo->prepare('INSERT INTO meeting_workers (meeting_id, worker_id, attended) VALUES (?, ?, ?)');
        foreach (array_unique(array_map('intval', $workerIds)) as $wid) {
            if ($wid > 0) {
                $wStmt->execute([$meetingId, $wid, $prevW[$wid] ?? 0]);
            }
        }

        $pStmt = $pdo->prepare('INSERT INTO meeting_patients (meeting_id, patient_id, attended) VALUES (?, ?, ?)');
        foreach (array_unique(array_map('intval', $patientIds)) as $pid) {
            if ($pid > 0) {
                $pStmt->execute([$meetingId, $pid, $prevP[$pid] ?? 0]);
            }
        }
    }

    public static function setWorkerAttendance(int $meetingId, int $workerId, bool $attended): bool
    {
        $check = db()->prepare('SELECT 1 FROM meeting_workers WHERE meeting_id = ? AND worker_id = ?');
        $check->execute([$meetingId, $workerId]);
        if (!$check->fetchColumn()) {
            return false;
        }
        $stmt = db()->prepare('UPDATE meeting_workers SET attended = ? WHERE meeting_id = ? AND worker_id = ?');
        $stmt->execute([$attended ? 1 : 0, $meetingId, $workerId]);
        return true;
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
    public static function setWorkerAttendanceBulk(int $meetingId, bool $attended, ?array $ids = null): int
    {
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
            if (!$ids) {
                return 0;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("UPDATE meeting_workers SET attended = ? WHERE meeting_id = ? AND worker_id IN ({$placeholders})");
            $stmt->execute(array_merge([$attended ? 1 : 0, $meetingId], $ids));
            return $stmt->rowCount();
        }
        $stmt = db()->prepare('UPDATE meeting_workers SET attended = ? WHERE meeting_id = ?');
        $stmt->execute([$attended ? 1 : 0, $meetingId]);
        return $stmt->rowCount();
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
}
