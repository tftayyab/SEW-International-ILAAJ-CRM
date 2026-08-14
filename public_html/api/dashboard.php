<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/DashboardStats.php';

require_any_role();

try {
    $stats = DashboardStats::all([
        'period' => input('period', 'all'),
        'year' => input('year'),
        'from' => input('from'),
        'to' => input('to'),
    ]);
    json_success(['stats' => $stats]);
} catch (Throwable $e) {
    log_error('dashboard API', $e);
    json_error('Unable to load dashboard statistics.', 500);
}
