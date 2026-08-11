<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/MeetingRepository.php';

require_editor();

$action = input('action', 'list');

try {
    switch ($action) {
        case 'list':
            json_success(MeetingRepository::list([
                'q' => input('q'),
                'page' => (int) input('page', 1),
                'per_page' => (int) input('per_page', 20),
            ]));
            break;

        case 'get':
            $meeting = MeetingRepository::find((int) input('id'));
            if (!$meeting) {
                json_error('Meeting not found.', 404);
            }
            json_success(['meeting' => $meeting]);
            break;

        case 'create':
            require_csrf();
            $data = [
                'name' => input('name'),
                'meeting_date' => input('meeting_date'),
                'start_time' => input('start_time'),
                'end_time' => input('end_time'),
                'location' => input('location'),
                'description' => input('description'),
                'notes' => input('notes'),
                'worker_ids' => input('worker_ids', []) ?: [],
                'patient_ids' => input('patient_ids', []) ?: [],
            ];
            if (!is_array($data['worker_ids'])) {
                $data['worker_ids'] = [];
            }
            if (!is_array($data['patient_ids'])) {
                $data['patient_ids'] = [];
            }
            $errors = MeetingRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            $id = MeetingRepository::create($data);
            json_success(['id' => $id, 'meeting' => MeetingRepository::find($id)], 201);
            break;

        case 'update':
            require_csrf();
            $id = (int) input('id');
            if (!MeetingRepository::find($id)) {
                json_error('Meeting not found.', 404);
            }
            $data = [
                'name' => input('name'),
                'meeting_date' => input('meeting_date'),
                'start_time' => input('start_time'),
                'end_time' => input('end_time'),
                'location' => input('location'),
                'description' => input('description'),
                'notes' => input('notes'),
                'worker_ids' => input('worker_ids', []) ?: [],
                'patient_ids' => input('patient_ids', []) ?: [],
            ];
            if (!is_array($data['worker_ids'])) {
                $data['worker_ids'] = [];
            }
            if (!is_array($data['patient_ids'])) {
                $data['patient_ids'] = [];
            }
            $errors = MeetingRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            MeetingRepository::update($id, $data);
            json_success(['meeting' => MeetingRepository::find($id)]);
            break;

        case 'attendance':
            require_csrf();
            $meetingId = (int) input('meeting_id');
            $type = (string) input('type'); // worker | patient
            $personId = (int) input('person_id');
            $attended = !empty(input('attended'));
            if (!MeetingRepository::find($meetingId)) {
                json_error('Meeting not found.', 404);
            }
            if ($type === 'worker') {
                if (!MeetingRepository::setWorkerAttendance($meetingId, $personId, $attended)) {
                    json_error('Worker not on this meeting.', 404);
                }
            } elseif ($type === 'patient') {
                if (!MeetingRepository::setPatientAttendance($meetingId, $personId, $attended)) {
                    json_error('Patient not on this meeting.', 404);
                }
            } else {
                json_error('Invalid attendance type.');
            }
            json_success(['meeting' => MeetingRepository::find($meetingId)]);
            break;

        case 'attendance_bulk':
            require_csrf();
            $meetingId = (int) input('meeting_id');
            $type = (string) input('type'); // worker | patient
            $attended = !empty(input('attended'));
            $ids = input('person_ids');
            if (!is_array($ids)) {
                $ids = null;
            }
            if (!MeetingRepository::find($meetingId)) {
                json_error('Meeting not found.', 404);
            }
            if ($type === 'worker') {
                MeetingRepository::setWorkerAttendanceBulk($meetingId, $attended, $ids);
            } elseif ($type === 'patient') {
                MeetingRepository::setPatientAttendanceBulk($meetingId, $attended, $ids);
            } else {
                json_error('Invalid attendance type.');
            }
            json_success(['meeting' => MeetingRepository::find($meetingId)]);
            break;

        case 'delete':
            require_csrf();
            $id = (int) input('id');
            if (!MeetingRepository::find($id)) {
                json_error('Meeting not found.', 404);
            }
            MeetingRepository::delete($id);
            json_success(['message' => 'Meeting deleted.']);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('meetings API', $e);
    json_error('An unexpected error occurred.', 500);
}
