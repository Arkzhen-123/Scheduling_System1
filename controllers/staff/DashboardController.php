<?php
declare(strict_types=1);

require_once __DIR__ . '/StaffPortalController.php';

// Process and render the staff dashboard page.
function handle_staff_dashboard_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    $context = staff_require_portal_context($conn, $baseUrl);
    extract($context);

    $dashboardUrl = $baseUrl . '/staff/dashboard.php';

    if (is_post_request() && isset($_POST['attendance_action'])) {
        staff_handle_attendance_post($conn, $staffId, $today, $dashboardUrl);
    }

    $todaySchedules = staff_get_today_schedules($conn, $staffId, $today);
    $currentSchedule = null;

    foreach ($todaySchedules as $schedule) {
        if (empty($schedule['check_out_time'])) {
            $currentSchedule = $schedule;
            break;
        }
    }

    $monthContext = staff_resolve_month_context(date('Y-m'));
    $monthSchedules = staff_get_month_schedules(
        $conn,
        $staffId,
        $monthContext['monthStart'],
        $monthContext['monthEnd']
    );
    $summary = staff_summarize_month_attendance($monthSchedules);
    extract($summary);

    $hasCurrentSchedule = is_array($currentSchedule);
    $hasCheckedIn = $hasCurrentSchedule && !empty($currentSchedule['check_in_time']);
    $hasCheckedOut = $hasCurrentSchedule && !empty($currentSchedule['check_out_time']);
    $canCheckIn = $hasCurrentSchedule && !$hasCheckedIn;
    $canCheckOut = $hasCurrentSchedule && $hasCheckedIn && !$hasCheckedOut;

    $page = 'dashboard';
    $pageTitle = 'Dashboard';

    require __DIR__ . '/../../views/staff/dashboard.php';
}