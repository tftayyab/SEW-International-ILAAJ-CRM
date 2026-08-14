<?php
/**
 * Auth API — login / logout / current session.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$action = input('action', request_method() === 'GET' ? 'me' : 'login');
$method = request_method();

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                json_error('POST required.', 405);
            }
            require_csrf();
            $user = attempt_login((string) input('username', ''), (string) input('password', ''));
            if (!$user) {
                json_error('Invalid username or password.', 401);
            }
            json_success([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                ],
                'token' => auth_read_token(),
                'redirect' => base_url('index.php'),
            ]);
            break;

        case 'logout':
            auth_clear();
            json_success(['redirect' => base_url('pages/login.php')]);
            break;

        case 'me':
            require_login();
            $user = current_user();
            json_success([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => current_role(),
                ],
            ]);
            break;

        default:
            json_error('Unknown action.', 404);
    }
} catch (Throwable $e) {
    log_error('auth API', $e);
    json_error('Unable to complete sign-in.', 500);
}
