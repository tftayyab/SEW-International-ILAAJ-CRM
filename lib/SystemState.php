<?php
/**
 * System state for Editor → Ameer Sahab sync
 */

declare(strict_types=1);

class SystemState
{
    private static function ensureNonceColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = db()->query("SHOW COLUMNS FROM system_state LIKE 'present_nonce'")->fetch();
            if (!$col) {
                db()->exec('ALTER TABLE system_state ADD COLUMN present_nonce INT UNSIGNED NOT NULL DEFAULT 0');
            }
        } catch (Throwable $e) {
            log_error('ensure present_nonce column', $e);
        }
    }

    public static function getActivePatientId(): ?int
    {
        $state = self::getState();
        return $state['active_patient_id'];
    }

    public static function getState(): array
    {
        self::ensureNonceColumn();
        $row = db()->query('SELECT active_patient_id, updated_at, present_nonce FROM system_state WHERE id = 1')->fetch();
        if (!$row) {
            db()->exec('INSERT INTO system_state (id, active_patient_id) VALUES (1, NULL)');
            return ['active_patient_id' => null, 'updated_at' => null, 'present_nonce' => 0];
        }
        return [
            'active_patient_id' => $row['active_patient_id'] !== null ? (int) $row['active_patient_id'] : null,
            'updated_at' => $row['updated_at'],
            'present_nonce' => (int) ($row['present_nonce'] ?? 0),
        ];
    }

    public static function setActivePatient(?int $patientId): array
    {
        self::ensureNonceColumn();
        if ($patientId !== null) {
            $check = db()->prepare('SELECT id FROM patients WHERE id = ? AND is_archived = 0');
            $check->execute([$patientId]);
            if (!$check->fetch()) {
                throw new RuntimeException('Patient not found.');
            }
        }
        $stmt = db()->prepare(
            'UPDATE system_state
             SET active_patient_id = ?, updated_at = NOW(), present_nonce = present_nonce + 1
             WHERE id = 1'
        );
        $stmt->execute([$patientId]);
        return self::getState();
    }
}
