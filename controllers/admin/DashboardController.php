<?php
declare(strict_types=1);

require_once __DIR__ . '/AdminPortalController.php';
require_once __DIR__ . '/../../models/Admin/AdminDashboardModel.php';

// Process and render the administrator dashboard page.
function handle_admin_dashboard_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    $context = admin_require_portal_context($conn, $baseUrl);
    extract($context);

    $stats = admin_get_dashboard_stats($conn, $today);
    $todaySchedules = admin_get_today_schedules_overview($conn, $today, 10);

    $page = 'dashboard';
    $pageTitle = 'Dashboard';

    require __DIR__ . '/../../views/admin/dashboard.php';
}