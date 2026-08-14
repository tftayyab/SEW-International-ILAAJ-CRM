<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/SystemState.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';

require_any_role();

$action = input('action', 'get');

try {
    switch ($action) {
        case 'get':
            $state = SystemState::getState();
            $patient = null;
            if ($state['active_patient_id']) {
                $patient = PatientRepository::find($state['active_patient_id']);
                if ($patient && !empty($patient['profile_image_url'])) {
                    $patient['profile_display_url'] = drive_display_url($patient['profile_image_url']);
                }
            }
            json_success(['state' => $state, 'patient' => $patient]);
            break;

        case 'set':
            require_editor();
            require_csrf();
            $id = input('patient_id');
            $patientId = ($id === null || $id === '' || $id === 'null') ? null : (int) $id;
            $state = SystemState::setActivePatient($patientId);
            json_success(['state' => $state]);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('active_patient API', $e);
    json_error($e->getMessage() ?: 'An unexpected error occurred.', 500);
}
