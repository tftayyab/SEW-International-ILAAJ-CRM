<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/DashboardStats.php';

require_editor();

try {
    json_success(['stats' => DashboardStats::all()]);
} catch (Throwable $e) {
    log_error('dashboard API', $e);
    json_error('Unable to load dashboard statistics.', 500);
}
