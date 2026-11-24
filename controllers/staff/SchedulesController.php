<?php
declare(strict_types=1);

require_once __DIR__ . '/StaffPortalController.php';

// Process and render the paginated staff schedules page.
function handle_staff_schedules_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    $context = staff_require_portal_context($conn, $baseUrl);
    extract($context);

    $monthContext = staff_resolve_month_context((string) ($_GET['month'] ?? date('Y-m')));
    extract($monthContext);

    $allMonthSchedules = staff_get_month_schedules($conn, $staffId, $monthStart, $monthEnd);
    $requestedPage = (int) ($_GET['p'] ?? 1);
    $pagination = staff_paginate_items($allMonthSchedules, $requestedPage, 10);
    $paginatedSchedules = $pagination['items'];
    $paginationBaseUrl = $baseUrl . '/staff/schedules.php?month=' . urlencode($selectedMonth);
    $paginationLabel = 'schedule(s)';

    $page = 'schedules';
    $pageTitle = 'View Schedule';

    require __DIR__ . '/../../views/staff/schedules.php';
}