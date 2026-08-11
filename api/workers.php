<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/MeetingRepository.php';

require_any_role();

$action = input('action', 'list');

try {
    switch ($action) {
        case 'list':
            require_editor();
            $workers = WorkerRepository::all(input('q') ? (string) input('q') : null);
            json_success(['workers' => $workers]);
            break;

        case 'get':
            require_editor();
            $worker = WorkerRepository::find((int) input('id'));
            if (!$worker) {
                json_error('Worker not found.', 404);
            }
            json_success(['worker' => $worker]);
            break;

        case 'create':
            require_editor();
            require_csrf();
            $data = ['name' => input('name'), 'phone' => input('phone')];
            $errors = WorkerRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            $id = WorkerRepository::create($data);
            json_success(['id' => $id, 'worker' => WorkerRepository::find($id)], 201);
            break;

        case 'update':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!WorkerRepository::find($id)) {
                json_error('Worker not found.', 404);
            }
            $data = ['name' => input('name'), 'phone' => input('phone')];
            $errors = WorkerRepository::validate($data);
            if ($errors) {
                json_error(implode(' ', $errors), 422, ['errors' => $errors]);
            }
            WorkerRepository::update($id, $data);
            json_success(['worker' => WorkerRepository::find($id)]);
            break;

        case 'delete':
            require_editor();
            require_csrf();
            $id = (int) input('id');
            if (!WorkerRepository::find($id)) {
                json_error('Worker not found.', 404);
            }
            WorkerRepository::delete($id);
            json_success(['message' => 'Worker deleted.']);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('workers API', $e);
    json_error('An unexpected error occurred.', 500);
}
