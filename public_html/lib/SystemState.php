<?php
/**
 * System state for Editor → Ameer Sahab sync
 */

declare(strict_types=1);

class SystemState
{
    public static function getActivePatientId(): ?int
    {
        $row = db()->query('SELECT active_patient_id, updated_at FROM system_state WHERE id = 1')->fetch();
        if (!$row || $row['active_patient_id'] === null) {
            return null;
        }
        return (int) $row['active_patient_id'];
    }

    public static function getState(): array
    {
        $row = db()->query('SELECT active_patient_id, updated_at FROM system_state WHERE id = 1')->fetch();
        if (!$row) {
            db()->exec('INSERT INTO system_state (id, active_patient_id) VALUES (1, NULL)');
            return ['active_patient_id' => null, 'updated_at' => null];
        }
        return [
            'active_patient_id' => $row['active_patient_id'] !== null ? (int) $row['active_patient_id'] : null,
            'updated_at' => $row['updated_at'],
        ];
    }

    public static function setActivePatient(?int $patientId): array
    {
        if ($patientId !== null) {
            $check = db()->prepare('SELECT id FROM patients WHERE id = ? AND is_archived = 0');
            $check->execute([$patientId]);
            if (!$check->fetch()) {
                throw new RuntimeException('Patient not found.');
            }
        }
        $stmt = db()->prepare('UPDATE system_state SET active_patient_id = ?, updated_at = NOW() WHERE id = 1');
        $stmt->execute([$patientId]);
        return self::getState();
    }
}
