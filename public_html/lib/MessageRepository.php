<?php
/**
 * Message repository
 */

declare(strict_types=1);

class MessageRepository
{
    public static function forPatient(int $patientId, bool $hideUnsentAmeer = false): array
    {
        $stmt = db()->prepare('SELECT * FROM messages WHERE patient_id = ?
            ORDER BY message_date IS NULL, message_date DESC, import_order DESC, id DESC');
        $stmt->execute([$patientId]);
        $messages = $stmt->fetchAll();

        if ($hideUnsentAmeer && $messages) {
            PatientRepository::ensureResponseSentColumn();
            $patient = PatientRepository::find($patientId);
            if ($patient && empty($patient['response_sent']) && $messages[0]['sender_type'] === 'ameer_sahab') {
                array_shift($messages);
            }
        }

        return $messages;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nextImportOrder(int $patientId): int
    {
        $stmt = db()->prepare('SELECT COALESCE(MAX(import_order), 0) + 1 FROM messages WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn();
    }

    public static function create(array $data): int
    {
        $patientId = (int) $data['patient_id'];
        $order = isset($data['import_order']) ? (int) $data['import_order'] : self::nextImportOrder($patientId);
        $date = array_key_exists('message_date', $data) ? parse_date($data['message_date']) : date('Y-m-d');

        $stmt = db()->prepare('INSERT INTO messages (patient_id, sender_type, message_text, message_date, import_order)
            VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $patientId,
            $data['sender_type'],
            trim_str($data['message_text']),
            $date,
            $order,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = db()->prepare('UPDATE messages SET sender_type = ?, message_text = ?, message_date = ? WHERE id = ?');
        return $stmt->execute([
            $data['sender_type'],
            trim_str($data['message_text']),
            parse_date($data['message_date'] ?? null),
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM messages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function validate(array $data): array
    {
        $errors = [];
        if (empty($data['patient_id'])) {
            $errors[] = 'Patient is required.';
        }
        if (!in_array($data['sender_type'] ?? '', ['patient', 'ameer_sahab'], true)) {
            $errors[] = 'Sender must be Patient or Ameer Sahab.';
        }
        if (trim_str($data['message_text'] ?? '') === '') {
            $errors[] = 'Message text is required.';
        }
        return $errors;
    }
}
