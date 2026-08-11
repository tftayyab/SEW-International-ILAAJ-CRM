<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/MessageRepository.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';

require_any_role();

$action = input('action', 'list');

try {
    switch ($action) {
        case 'list':
            $patientId = (int) input('patient_id');
            if (!PatientRepository::find($patientId)) {
                json_error('Patient not found.', 404);
            }
            json_success(['messages' => MessageRepository::forPatient($patientId)]);
            break;

        case 'create':
            require_editor();
            require_csrf();
            $data = [
                'patient_id' => (int) input('patient_id'),
                'sender_type' => input('sender_type'),
                'message_text' => input('message_text'),
                'message_date' => input('message_date', date('Y-m-d')),
            ];
            $errors = MessageRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            if (!PatientRepository::find((int) $data['patient_id'])) {
                json_error('Patient not found.', 404);
            }
            $id = MessageRepository::create($data);
            json_success(['id' => $id, 'message' => MessageRepository::find($id)], 201);
            break;

        case 'update':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            $existing = MessageRepository::find($id);
            if (!$existing) {
                json_error('Message not found.', 404);
            }
            $data = [
                'sender_type' => input('sender_type', $existing['sender_type']),
                'message_text' => input('message_text', $existing['message_text']),
                'message_date' => input('message_date', $existing['message_date']),
            ];
            $errors = MessageRepository::validate(array_merge($data, ['patient_id' => $existing['patient_id']]));
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            MessageRepository::update($id, $data);
            json_success(['message' => MessageRepository::find($id)]);
            break;

        case 'delete':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!MessageRepository::find($id)) {
                json_error('Message not found.', 404);
            }
            MessageRepository::delete($id);
            json_success(['message' => 'Message deleted.']);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('messages API', $e);
    json_error('An unexpected error occurred.', 500);
}
