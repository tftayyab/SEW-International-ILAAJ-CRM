<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';
require_once ROOT_PATH . '/lib/SystemState.php';

require_any_role();

$action = input('action', 'list');
$method = request_method();

try {
    switch ($action) {
        case 'list':
            $result = PatientRepository::search([
                'q' => input('q'),
                'name' => input('name'),
                'mother_name' => input('mother_name'),
                'number' => input('number'),
                'country' => input('country'),
                'city' => input('city'),
                'occupation' => input('occupation'),
                'exact_number' => input('exact_number'),
                'sort' => input('sort', 'last_activity'),
                'dir' => input('dir', 'DESC'),
                'page' => (int) input('page', 1),
                'per_page' => (int) input('per_page', 20),
            ]);
            foreach ($result['data'] as &$row) {
                if (!empty($row['profile_image_url'])) {
                    $row['profile_display_url'] = drive_display_url($row['profile_image_url']);
                }
            }
            json_success($result);
            break;

        case 'get':
            $id = (int) input('id');
            $patient = PatientRepository::find($id);
            if (!$patient) {
                json_error('Patient not found.', 404);
            }
            if (!empty($patient['profile_image_url'])) {
                $patient['profile_display_url'] = drive_display_url($patient['profile_image_url']);
            }
            json_success(['patient' => $patient]);
            break;

        case 'by_number':
            $number = trim_str((string) input('number', ''));
            if ($number === '') {
                json_error('Number is required.');
            }
            $patients = PatientRepository::findByNumber($number);
            json_success(['patients' => $patients, 'count' => count($patients)]);
            break;

        case 'create':
            require_editor();
            require_csrf();
            $data = [
                'name' => input('name'),
                'mother_name' => input('mother_name'),
                'number' => input('number'),
                'country' => input('country'),
                'city' => input('city'),
                'occupation' => input('occupation'),
                'notes' => input('notes'),
            ];
            $errors = PatientRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            $id = PatientRepository::create($data);
            json_success(['id' => $id, 'patient' => PatientRepository::find($id)], 201);
            break;

        case 'update':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!PatientRepository::find($id)) {
                json_error('Patient not found.', 404);
            }
            $data = [
                'name' => input('name'),
                'mother_name' => input('mother_name'),
                'number' => input('number'),
                'country' => input('country'),
                'city' => input('city'),
                'occupation' => input('occupation'),
                'notes' => input('notes'),
            ];
            $errors = PatientRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            PatientRepository::update($id, $data);
            json_success(['patient' => PatientRepository::find($id)]);
            break;

        case 'delete':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            $confirm = (string) input('confirm_phrase', '');
            if ($confirm !== 'DELETE THIS PATIENT') {
                json_error('You must type DELETE THIS PATIENT exactly to confirm deletion.');
            }
            if (!PatientRepository::find($id)) {
                json_error('Patient not found.', 404);
            }
            PatientRepository::delete($id);
            json_success(['message' => 'Patient permanently deleted.']);
            break;

        case 'set_active':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!PatientRepository::find($id)) {
                json_error('Patient not found.', 404);
            }
            $state = SystemState::setActivePatient($id);
            json_success(['state' => $state]);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('patients API', $e);
    json_error('An unexpected error occurred.', 500);
}
