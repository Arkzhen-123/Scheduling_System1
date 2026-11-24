<?php
declare(strict_types=1);

if (!function_exists('admin_format_time')) {
    function admin_format_time(?string $timeValue): string
    {
        if ($timeValue === null || trim($timeValue) === '') {
            return '—';
        }

        $timestamp = strtotime($timeValue);
        return $timestamp !== false ? date('g:i A', $timestamp) : $timeValue;
    }
}

// Fetch key workforce KPI counters for the Dashboard stat cards.
function admin_get_dashboard_stats(mysqli $conn, string $today): array
{
    $stats = [
        'activeStaff' => 0,
        'todaySchedules' => 0,
        'presentToday' => 0,
        'totalMailRequests' => 0,
    ];

    $staffRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'staff' AND account_status = 'active'");
    if ($staffRes && ($row = $staffRes->fetch_assoc())) {
        $stats['activeStaff'] = (int) ($row['total'] ?? 0);
    }

    $mailRes = $conn->query('SELECT COUNT(*) AS total FROM password_assistance_requests WHERE is_read = 0');
    if ($mailRes && ($row = $mailRes->fetch_assoc())) {
        $stats['totalMailRequests'] = (int) ($row['total'] ?? 0);
    }

    $stmt = $conn->prepare(
        "SELECT
            a.check_in_time,
            a.attendance_status
         FROM schedules s
         LEFT JOIN attendance a
            ON a.schedule_id = s.id AND a.staff_id = s.staff_id
         WHERE s.schedule_date = ?
           AND s.status <> 'cancelled'"
    );

    if ($stmt) {
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $stats['todaySchedules']++;
            $attStatus = (string) ($row['attendance_status'] ?? '');

            if (!empty($row['check_in_time']) || in_array($attStatus, ['present', 'late', 'completed'], true)) {
                $stats['presentToday']++;
            }
        }

        $stmt->close();
    }

    return $stats;
}

// Fetch today's scheduled shifts for the Dashboard overview table.
function admin_get_today_schedules_overview(mysqli $conn, string $today, int $limit = 10): array
{
    $safeLimit = max(1, min(50, $limit));
    $stmt = $conn->prepare(
        "SELECT
            s.id AS schedule_id,
            s.start_time,
            s.end_time,
            s.task_description,
            s.work_location,
            s.notes,
            s.status AS schedule_status,
            u.full_name AS staff_name,
            u.employee_id,
            jc.category_name,
            a.check_in_time,
            a.check_out_time,
            a.attendance_status
         FROM schedules s
         INNER JOIN users u ON u.id = s.staff_id
         INNER JOIN job_categories jc ON jc.id = s.assigned_category_id
         LEFT JOIN attendance a
            ON a.schedule_id = s.id AND a.staff_id = s.staff_id
         WHERE s.schedule_date = ?
           AND s.status <> 'cancelled'
         ORDER BY s.start_time ASC, u.full_name ASC
         LIMIT {$safeLimit}"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $today);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}