<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/StaffModel.php';

// Format a database time or datetime string into 12-hour format.
function staff_format_time(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '—' : date('g:i A', $timestamp);
}

// Resolve a stored profile picture path to a public URL.
function staff_resolve_picture_url(string $path, string $baseUrl): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

// Require an authenticated active staff session and return common view context.
function staff_require_portal_context(mysqli $conn, string $baseUrl = '/Scheduling_System'): array
{
    if (!is_logged_in()) {
        redirect($baseUrl . '/login.php');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $sessionUser = current_user();

    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        logout_user();
    }

    if (($sessionUser['role'] ?? '') !== 'staff') {
        redirect($baseUrl . '/admin/dashboard.php');
    }

    $staffId = (int) $sessionUser['id'];
    $staff = staff_find_active_by_id($conn, $staffId);

    if ($staff === null) {
        logout_user();
    }

    $staffName = (string) $staff['full_name'];
    $initialsParts = preg_split('/\s+/', trim($staffName)) ?: [];
    $initials = '';

    foreach ($initialsParts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    $initials = substr($initials ?: 'ST', 0, 2);

    return [
        'baseUrl' => $baseUrl,
        'today' => date('Y-m-d'),
        'staffId' => $staffId,
        'staff' => $staff,
        'staffName' => $staffName,
        'staffEmail' => (string) $staff['email'],
        'employeeId' => (string) ($staff['employee_id'] ?? ''),
        'position' => (string) ($staff['position'] ?? 'Staff'),
        'initials' => $initials,
        'profilePictureUrl' => staff_resolve_picture_url((string) ($staff['profile_picture'] ?? ''), $baseUrl),
        'csrfToken' => csrf_token(),
        'flash' => get_flash_message(),
    ];
}

// Validate a YYYY-MM query parameter and return calendar boundaries.
function staff_resolve_month_context(string $rawMonth): array
{
    $selectedMonth = preg_match('/^\d{4}-\d{2}$/', $rawMonth) ? $rawMonth : date('Y-m');
    $monthDate = DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth);

    if (!$monthDate || $monthDate->format('Y-m') !== $selectedMonth) {
        $monthDate = new DateTimeImmutable('first day of this month');
        $selectedMonth = $monthDate->format('Y-m');
    }

    return [
        'selectedMonth' => $selectedMonth,
        'monthDate' => $monthDate,
        'monthStart' => $monthDate->format('Y-m-01'),
        'monthEnd' => $monthDate->format('Y-m-t'),
        'calendarFirstWeekday' => (int) $monthDate->format('N'),
        'calendarDays' => (int) $monthDate->format('t'),
        'previousMonth' => $monthDate->modify('-1 month')->format('Y-m'),
        'nextMonth' => $monthDate->modify('+1 month')->format('Y-m'),
    ];
}

// Summarize monthly schedules into per-day status and monthly attendance totals.
function staff_summarize_month_attendance(array $monthSchedules): array
{
    $attendanceByDay = [];

    foreach ($monthSchedules as $row) {
        $day = (int) date('j', strtotime((string) $row['schedule_date']));

        if (!isset($attendanceByDay[$day])) {
            $attendanceByDay[$day] = [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'status' => 'scheduled',
            ];
        }

        $attendanceByDay[$day]['total']++;

        $recordStatus = (string) ($row['attendance_status'] ?? '');
        $isPresent = !empty($row['check_in_time'])
            || in_array($recordStatus, ['present', 'late', 'completed'], true);

        $shiftEndTimestamp = strtotime($row['schedule_date'] . ' ' . $row['end_time']);
        $shiftHasEnded = $shiftEndTimestamp !== false && $shiftEndTimestamp < time();

        $isAbsent = $recordStatus === 'absent'
            || (empty($row['check_in_time']) && $shiftHasEnded);

        if ($isPresent) {
            $attendanceByDay[$day]['present']++;
        } elseif ($isAbsent) {
            $attendanceByDay[$day]['absent']++;
        }
    }

    $totalPresentDays = 0;
    $totalAbsentDays = 0;

    foreach ($attendanceByDay as $day => $data) {
        if ($data['present'] > 0) {
            $attendanceByDay[$day]['status'] = 'present';
            $totalPresentDays++;
        } elseif ($data['absent'] > 0) {
            $attendanceByDay[$day]['status'] = 'absent';
            $totalAbsentDays++;
        }
    }

    return [
        'attendanceByDay' => $attendanceByDay,
        'totalPresentDays' => $totalPresentDays,
        'totalAbsentDays' => $totalAbsentDays,
        'totalScheduledDays' => count($attendanceByDay),
    ];
}

// Slice an array of items for table pagination and return pagination metadata.
function staff_paginate_items(array $items, int $requestedPage, int $perPage = 10): array
{
    $totalItems = count($items);
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($requestedPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    $paginatedItems = array_slice($items, $offset, $perPage);

    return [
        'items' => $paginatedItems,
        'currentPage' => $currentPage,
        'perPage' => $perPage,
        'totalItems' => $totalItems,
        'totalPages' => $totalPages,
        'startItem' => $totalItems > 0 ? $offset + 1 : 0,
        'endItem' => $totalItems > 0 ? min($offset + $perPage, $totalItems) : 0,
    ];
}

// Process a POST check-in or check-out submission and redirect back to the target URL.
function staff_handle_attendance_post(
    mysqli $conn,
    int $staffId,
    string $today,
    string $redirectUrl
): void {
    $action = post_string('attendance_action');
    $scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT);

    if (!verify_csrf_token()) {
        set_flash_message('error', 'Your session token expired. Please refresh and try again.');
        redirect($redirectUrl);
    }

    if (!$scheduleId || !in_array($action, ['check_in', 'check_out'], true)) {
        set_flash_message('error', 'Invalid attendance request.');
        redirect($redirectUrl);
    }

    try {
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $message = staff_record_attendance($conn, $staffId, (int) $scheduleId, $today, $action, $ipAddress);
        set_flash_message('success', $message);
    } catch (Throwable $e) {
        error_log('Staff attendance action failed: ' . $e->getMessage());
        set_flash_message(
            'error',
            $e instanceof RuntimeException ? $e->getMessage() : 'Attendance could not be updated. Please try again.'
        );
    }

    redirect($redirectUrl);
}