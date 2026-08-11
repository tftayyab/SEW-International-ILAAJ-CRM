<?php
/**
 * Dashboard statistics
 */

declare(strict_types=1);

class DashboardStats
{
    public static function all(): array
    {
        $pdo = db();

        $totalPatients = (int) $pdo->query('SELECT COUNT(*) FROM patients WHERE is_archived = 0')->fetchColumn();
        $newPatients7 = (int) $pdo->query('SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
        $newPatients30 = (int) $pdo->query('SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();

        $totalMessages = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $patientMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_type = 'patient'")->fetchColumn();
        $ameerMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_type = 'ameer_sahab'")->fetchColumn();

        $workers = (int) $pdo->query('SELECT COUNT(*) FROM workers')->fetchColumn();
        $meetings = (int) $pdo->query('SELECT COUNT(*) FROM meetings')->fetchColumn();

        $withImages = (int) $pdo->query('SELECT COUNT(DISTINCT patient_id) FROM patient_images')->fetchColumn();
        $withoutImages = max(0, $totalPatients - $withImages);

        $byCountry = $pdo->query("SELECT COALESCE(NULLIF(TRIM(country), ''), 'Unknown') AS label, COUNT(*) AS total
            FROM patients WHERE is_archived = 0 GROUP BY label ORDER BY total DESC LIMIT 10")->fetchAll();
        $byCity = $pdo->query("SELECT COALESCE(NULLIF(TRIM(city), ''), 'Unknown') AS label, COUNT(*) AS total
            FROM patients WHERE is_archived = 0 GROUP BY label ORDER BY total DESC LIMIT 10")->fetchAll();
        $byOccupation = $pdo->query("SELECT COALESCE(NULLIF(TRIM(occupation), ''), 'Unknown') AS label, COUNT(*) AS total
            FROM patients WHERE is_archived = 0 GROUP BY label ORDER BY total DESC LIMIT 10")->fetchAll();

        $recentPatients = $pdo->query('SELECT id, name, number, city, country, created_at FROM patients WHERE is_archived = 0 ORDER BY created_at DESC LIMIT 8')->fetchAll();
        $recentMessages = $pdo->query('SELECT m.id, m.sender_type, m.message_text, m.message_date, m.created_at, p.name AS patient_name, p.id AS patient_id
            FROM messages m JOIN patients p ON p.id = m.patient_id ORDER BY m.created_at DESC LIMIT 8')->fetchAll();
        $recentMeetings = $pdo->query('SELECT id, name, meeting_date, location FROM meetings ORDER BY meeting_date IS NULL, meeting_date DESC, id DESC LIMIT 5')->fetchAll();

        $imports = (int) $pdo->query("SELECT COUNT(*) FROM excel_imports WHERE status = 'completed'")->fetchColumn();
        $lastImport = $pdo->query("SELECT * FROM excel_imports WHERE status = 'completed' ORDER BY completed_at DESC LIMIT 1")->fetch() ?: null;

        return [
            'totals' => [
                'patients' => $totalPatients,
                'new_patients_7' => $newPatients7,
                'new_patients_30' => $newPatients30,
                'messages' => $totalMessages,
                'patient_messages' => $patientMessages,
                'ameer_messages' => $ameerMessages,
                'workers' => $workers,
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
        ];
    }
}
