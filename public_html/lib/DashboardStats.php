<?php
/**
 * Dashboard statistics
 */

declare(strict_types=1);

class DashboardStats
{
    public static function all(array $filters = []): array
    {
        $pdo = db();
        $range = self::rangeFromFilters($filters);
        $years = self::availableYears($pdo);

        $pWhere = 'p.is_archived = 0';
        $pParams = [];
        $pDateSql = '';
        if ($range) {
            $pDateSql = ' AND p.created_at BETWEEN ? AND ?';
            $pWhere .= $pDateSql;
            $pParams = [$range['from_dt'], $range['to_dt']];
        }

        $mWhere = '1=1';
        $mParams = [];
        if ($range) {
            $mWhere = 'COALESCE(message_date, DATE(created_at)) BETWEEN ? AND ?';
            $mParams = [$range['from'], $range['to']];
        }

        $meetWhere = '1=1';
        $meetParams = [];
        if ($range) {
            $meetWhere = 'meeting_date BETWEEN ? AND ?';
            $meetParams = [$range['from'], $range['to']];
        }

        $impWhere = "status = 'completed'";
        $impParams = [];
        if ($range) {
            $impWhere .= ' AND completed_at BETWEEN ? AND ?';
            $impParams = [$range['from_dt'], $range['to_dt']];
        }

        $totalPatients = self::count($pdo, "SELECT COUNT(*) FROM patients p WHERE {$pWhere}", $pParams);

        $newPatients7 = 0;
        $newPatients30 = 0;
        if (!$range) {
            $newPatients7 = self::count($pdo, 'SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            $newPatients30 = self::count($pdo, 'SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }

        $totalMessages = self::count($pdo, "SELECT COUNT(*) FROM messages WHERE {$mWhere}", $mParams);
        $patientMessages = self::count($pdo, "SELECT COUNT(*) FROM messages WHERE sender_type = 'patient' AND {$mWhere}", $mParams);
        $ameerMessages = self::count($pdo, "SELECT COUNT(*) FROM messages WHERE sender_type = 'ameer_sahab' AND {$mWhere}", $mParams);

        $pendingSql = "SELECT COUNT(*) FROM patients p
            WHERE p.is_archived = 0
              AND (SELECT m.sender_type FROM messages m
                    WHERE m.patient_id = p.id
                    ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC
                    LIMIT 1) = 'patient'";
        $pendingParams = [];
        if ($range) {
            $pendingSql .= " AND (SELECT COALESCE(m.message_date, DATE(m.created_at)) FROM messages m
                    WHERE m.patient_id = p.id
                    ORDER BY m.message_date IS NULL, m.message_date DESC, m.import_order DESC, m.id DESC
                    LIMIT 1) BETWEEN ? AND ?";
            $pendingParams = [$range['from'], $range['to']];
        }
        $pendingReplies = self::count($pdo, $pendingSql, $pendingParams);

        $meetings = self::count($pdo, "SELECT COUNT(*) FROM meetings WHERE {$meetWhere}", $meetParams);

        $withImages = self::count(
            $pdo,
            "SELECT COUNT(DISTINCT pi.patient_id) FROM patient_images pi
             INNER JOIN patients p ON p.id = pi.patient_id
             WHERE {$pWhere}",
            $pParams
        );
        $withoutImages = max(0, $totalPatients - $withImages);

        $byCountry = self::rows(
            $pdo,
            "SELECT COALESCE(NULLIF(TRIM(p.country), ''), 'Unknown') AS label, COUNT(*) AS total
             FROM patients p WHERE {$pWhere} GROUP BY label ORDER BY total DESC LIMIT 10",
            $pParams
        );
        $byCity = self::rows(
            $pdo,
            "SELECT COALESCE(NULLIF(TRIM(p.city), ''), 'Unknown') AS label, COUNT(*) AS total
             FROM patients p WHERE {$pWhere} GROUP BY label ORDER BY total DESC LIMIT 10",
            $pParams
        );
        $byOccupation = self::rows(
            $pdo,
            "SELECT COALESCE(NULLIF(TRIM(p.occupation), ''), 'Unknown') AS label, COUNT(*) AS total
             FROM patients p WHERE {$pWhere} GROUP BY label ORDER BY total DESC LIMIT 10",
            $pParams
        );

        $recentPatients = self::rows(
            $pdo,
            "SELECT p.id, p.name, p.number, p.city, p.country, p.created_at
             FROM patients p WHERE {$pWhere} ORDER BY p.created_at DESC LIMIT 8",
            $pParams
        );
        $recentMessages = self::rows(
            $pdo,
            "SELECT m.id, m.sender_type, m.message_text, m.message_date, m.created_at, p.name AS patient_name, p.id AS patient_id
             FROM messages m
             JOIN patients p ON p.id = m.patient_id
             WHERE {$mWhere}
             ORDER BY COALESCE(m.message_date, DATE(m.created_at)) DESC, m.id DESC
             LIMIT 8",
            $mParams
        );
        $recentMeetings = self::rows(
            $pdo,
            "SELECT id, name, meeting_date, location FROM meetings
             WHERE {$meetWhere}
             ORDER BY meeting_date IS NULL, meeting_date DESC, id DESC
             LIMIT 5",
            $meetParams
        );

        $imports = self::count($pdo, "SELECT COUNT(*) FROM excel_imports WHERE {$impWhere}", $impParams);
        $lastImport = self::row(
            $pdo,
            "SELECT * FROM excel_imports WHERE {$impWhere} ORDER BY completed_at DESC LIMIT 1",
            $impParams
        );

        return [
            'totals' => [
                'patients' => $totalPatients,
                'new_patients_7' => $newPatients7,
                'new_patients_30' => $newPatients30,
                'messages' => $totalMessages,
                'patient_messages' => $patientMessages,
                'ameer_messages' => $ameerMessages,
                'pending_replies' => $pendingReplies,
                'meetings' => $meetings,
                'with_images' => $withImages,
                'without_images' => $withoutImages,
                'imports' => $imports,
            ],
            'by_country' => $byCountry,
            'by_city' => $byCity,
            'by_occupation' => $byOccupation,
            'recent_patients' => $recentPatients,
            'recent_messages' => $recentMessages,
            'recent_meetings' => $recentMeetings,
            'last_import' => $lastImport,
            'filter' => [
                'period' => $range['period'] ?? 'all',
                'year' => $range['year'] ?? null,
                'from' => $range['from'] ?? null,
                'to' => $range['to'] ?? null,
                'label' => $range['label'] ?? 'All time',
                'years' => $years,
            ],
        ];
    }

    /**
     * @return array{period:string,year:?int,from:string,to:string,from_dt:string,to_dt:string,label:string}|null
     */
    public static function rangeFromFilters(array $filters): ?array
    {
        $period = strtolower(trim((string) ($filters['period'] ?? 'all')));
        if ($period === '' || $period === 'all') {
            return null;
        }

        if ($period === 'year') {
            $year = (int) ($filters['year'] ?? date('Y'));
            if ($year < 1970 || $year > 2100) {
                $year = (int) date('Y');
            }
            return [
                'period' => 'year',
                'year' => $year,
                'from' => sprintf('%04d-01-01', $year),
                'to' => sprintf('%04d-12-31', $year),
                'from_dt' => sprintf('%04d-01-01 00:00:00', $year),
                'to_dt' => sprintf('%04d-12-31 23:59:59', $year),
                'label' => (string) $year,
            ];
        }

        if ($period === 'custom') {
            $from = self::validDate((string) ($filters['from'] ?? ''));
            $to = self::validDate((string) ($filters['to'] ?? ''));
            if (!$from) {
                $from = date('Y-01-01');
            }
            if (!$to) {
                $to = date('Y-m-d');
            }
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            return [
                'period' => 'custom',
                'year' => null,
                'from' => $from,
                'to' => $to,
                'from_dt' => $from . ' 00:00:00',
                'to_dt' => $to . ' 23:59:59',
                'label' => $from . ' – ' . $to,
            ];
        }

        return null;
    }

    private static function validDate(string $value): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return $value;
    }

    private static function availableYears(PDO $pdo): array
    {
        $sql = "SELECT DISTINCT y FROM (
            SELECT YEAR(created_at) AS y FROM patients
            UNION SELECT YEAR(COALESCE(message_date, created_at)) FROM messages
            UNION SELECT YEAR(meeting_date) FROM meetings WHERE meeting_date IS NOT NULL
        ) t WHERE y IS NOT NULL ORDER BY y DESC";
        $years = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $years = array_values(array_unique(array_map('intval', $years)));
        $current = (int) date('Y');
        if (!in_array($current, $years, true)) {
            array_unshift($years, $current);
        }
        return $years ?: [$current];
    }

    private static function count(PDO $pdo, string $sql, array $params = []): int
    {
        if (!$params) {
            return (int) $pdo->query($sql)->fetchColumn();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function rows(PDO $pdo, string $sql, array $params = []): array
    {
        if (!$params) {
            return $pdo->query($sql)->fetchAll();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function row(PDO $pdo, string $sql, array $params = []): ?array
    {
        $rows = self::rows($pdo, $sql, $params);
        return $rows[0] ?? null;
    }
}
