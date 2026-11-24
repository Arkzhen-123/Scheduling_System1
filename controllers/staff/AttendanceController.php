<?php
declare(strict_types=1);

require_once __DIR__ . '/StaffPortalController.php';

// Process and render the staff attendance calendar and paginated details table.
function handle_staff_attendance_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    $context = staff_require_portal_context($conn, $baseUrl);
    extract($context);

    $monthContext = staff_resolve_month_context((string) ($_GET['month'] ?? date('Y-m')));
    extract($monthContext);

    $attendanceUrl = $baseUrl . '/staff/attendance.php?month=' . urlencode($selectedMonth);

    if (is_post_request() && isset($_POST['attendance_action'])) {
        staff_handle_attendance_post($conn, $staffId, $today, $attendanceUrl);
    }

    $todaySchedules = staff_get_today_schedules($conn, $staffId, $today);
    $currentSchedule = null;

    foreach ($todaySchedules as $schedule) {
        if (empty($schedule['check_out_time'])) {
            $currentSchedule = $schedule;
            break;
        }
    }

    $hasCurrentSchedule = is_array($currentSchedule);
    $hasCheckedIn = $hasCurrentSchedule && !empty($currentSchedule['check_in_time']);
    $hasCheckedOut = $hasCurrentSchedule && !empty($currentSchedule['check_out_time']);
    $canCheckIn = $hasCurrentSchedule && !$hasCheckedIn;
    $canCheckOut = $hasCurrentSchedule && $hasCheckedIn && !$hasCheckedOut;

    $monthSchedules = staff_get_month_schedules($conn, $staffId, $monthStart, $monthEnd);
    $summary = staff_summarize_month_attendance($monthSchedules);
    extract($summary);

    $requestedPage = (int) ($_GET['p'] ?? 1);
    $pagination = staff_paginate_items($monthSchedules, $requestedPage, 10);
    $paginatedMonthSchedules = $pagination['items'];
    $paginationBaseUrl = $baseUrl . '/staff/attendance.php?month=' . urlencode($selectedMonth);
    $paginationLabel = 'record(s)';

    $previousMonthUrl = $baseUrl . '/staff/attendance.php?month=' . urlencode($previousMonth);
    $nextMonthUrl = $baseUrl . '/staff/attendance.php?month=' . urlencode($nextMonth);

    $page = 'attendance';
    $pageTitle = 'Attendance';

    require __DIR__ . '/../../views/staff/attendance.php';
}