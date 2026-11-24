<?php
declare(strict_types=1);

// --- Shared Time & Audit Helpers ---

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

function admin_attendance_write_audit(
    mysqli $conn,
    int $adminId,
    string $entityId,
    string $description,
    string $ipAddress
): void {
    $action = 'update_attendance';
    $entityType = 'attendance';
    $safeIp = substr($ipAddress, 0, 45);

    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($stmt) {
        $stmt->bind_param('isssss', $adminId, $action, $entityType, $entityId, $description, $safeIp);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Attendance Logs Query ---

function admin_get_attendance_logs(mysqli $conn, array $filters, string $today): array
{
    $sql = "SELECT
                s.id AS schedule_id,
                s.staff_id,
                s.schedule_date,
                s.start_time,
                s.end_time,
                s.task_description,
                s.assigned_category_id,
                s.work_location,
                s.notes,
                s.status AS schedule_status,
                u.full_name AS staff_name,
                u.employee_id,
                u.position AS staff_position,
                jc.category_name,
                a.id AS attendance_id,
                a.check_in_time,
                a.check_out_time,
                a.attendance_status
            FROM schedules s
            INNER JOIN users u ON u.id = s.staff_id
            INNER JOIN job_categories jc ON jc.id = s.assigned_category_id
            LEFT JOIN attendance a
                ON a.schedule_id = s.id AND a.staff_id = s.staff_id
            WHERE s.status <> 'cancelled'";

    $types = '';
    $params = [];

    $staffId = (int) ($filters['staff_id'] ?? 0);
    $categoryId = (int) ($filters['category_id'] ?? 0);
    $search = trim((string) ($filters['search'] ?? ''));

    if ($staffId > 0) {
        $sql .= ' AND s.staff_id = ?';
        $types .= 'i';
        $params[] = $staffId;
    }

    if ($categoryId > 0) {
        $sql .= ' AND s.assigned_category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }

    if ($search !== '') {
        $sql .= ' AND (u.full_name LIKE ? OR u.employee_id LIKE ? OR s.task_description LIKE ? OR s.work_location LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY s.schedule_date DESC, s.start_time ASC, u.full_name ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $notes = (string) ($row['notes'] ?? '');
        $rawStatus = (string) ($row['attendance_status'] ?? '');
        $isOnLeave = stripos($notes, '[ON LEAVE]') !== false;
        $shiftEndTs = strtotime($row['schedule_date'] . ' ' . $row['end_time']);
        $shiftEnded = $shiftEndTs !== false && $shiftEndTs < time();

        if ($isOnLeave) {
            $effectiveStatus = 'on_leave';
        } elseif ($rawStatus === 'completed' || !empty($row['check_out_time'])) {
            $effectiveStatus = 'completed';
        } elseif ($rawStatus === 'late') {
            $effectiveStatus = 'late';
        } elseif ($rawStatus === 'present') {
            $effectiveStatus = 'present';
        } elseif ($rawStatus === 'absent') {
            $effectiveStatus = 'absent';
        } elseif ($rawStatus === 'incomplete') {
            $effectiveStatus = 'incomplete';
        } elseif ($shiftEnded && empty($row['check_in_time'])) {
            $effectiveStatus = 'absent';
        } else {
            $effectiveStatus = 'not_started';
        }

        $row['is_on_leave'] = $isOnLeave;
        $row['effective_status'] = $effectiveStatus;
        $row['is_today'] = ((string) $row['schedule_date']) === $today;
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

// --- Timestamp Normalization & Attendance Overrides ---

function admin_normalize_datetime_input(?string $value): ?string
{
    $clean = trim((string) $value);
    if ($clean === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', $clean);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        return $normalized . ':00';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized)) {
        return $normalized;
    }

    return null;
}

function admin_update_attendance_record(
    mysqli $conn,
    int $adminId,
    int $scheduleId,
    string $newStatus,
    ?string $checkInInput,
    ?string $checkOutInput,
    string $ipAddress
): array {
    $allowedStatuses = ['not_started', 'present', 'completed', 'late', 'absent', 'incomplete'];
    if ($scheduleId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        return ['ok' => false, 'message' => 'Invalid attendance status or shift selected.'];
    }

    $schedStmt = $conn->prepare(
        'SELECT
            s.id,
            s.staff_id,
            s.schedule_date,
            s.start_time,
            s.end_time,
            u.full_name AS staff_name,
            a.check_in_time AS existing_check_in,
            a.check_out_time AS existing_check_out
         FROM schedules s
         INNER JOIN users u ON u.id = s.staff_id
         LEFT JOIN attendance a
            ON a.schedule_id = s.id AND a.staff_id = s.staff_id
         WHERE s.id = ?
         LIMIT 1'
    );
    if (!$schedStmt) {
        return ['ok' => false, 'message' => 'Unable to load shift details.'];
    }

    $schedStmt->bind_param('i', $scheduleId);
    $schedStmt->execute();
    $shift = $schedStmt->get_result()->fetch_assoc();
    $schedStmt->close();

    if (!$shift) {
        return ['ok' => false, 'message' => 'Scheduled shift not found.'];
    }

    $staffId = (int) $shift['staff_id'];
    $schedDate = (string) $shift['schedule_date'];
    $defaultStartDt = $schedDate . ' ' . (string) $shift['start_time'];
    $defaultEndDt = $schedDate . ' ' . (string) $shift['end_time'];

    $checkInSql = admin_normalize_datetime_input($checkInInput);
    $checkOutSql = admin_normalize_datetime_input($checkOutInput);

    if ($newStatus === 'absent' || $newStatus === 'not_started') {
        $checkInSql = null;
        $checkOutSql = null;
    } elseif ($newStatus === 'present' || $newStatus === 'late' || $newStatus === 'incomplete') {
        if ($checkInSql === null) {
            $checkInSql = !empty($shift['existing_check_in'])
                ? (string) $shift['existing_check_in']
                : ($schedDate === date('Y-m-d') ? date('Y-m-d H:i:s') : $defaultStartDt);
        }
        if ($newStatus !== 'completed' && $checkOutInput === null) {
            $checkOutSql = !empty($shift['existing_check_out']) ? (string) $shift['existing_check_out'] : null;
        }
    } elseif ($newStatus === 'completed') {
        if ($checkInSql === null) {
            $checkInSql = !empty($shift['existing_check_in']) ? (string) $shift['existing_check_in'] : $defaultStartDt;
        }
        if ($checkOutSql === null) {
            $checkOutSql = !empty($shift['existing_check_out'])
                ? (string) $shift['existing_check_out']
                : ($schedDate === date('Y-m-d') ? date('Y-m-d H:i:s') : $defaultEndDt);
        }
    }

    if ($checkInSql !== null && $checkOutSql !== null && strtotime($checkOutSql) < strtotime($checkInSql)) {
        return ['ok' => false, 'message' => 'Check-out time cannot be earlier than check-in time.'];
    }

    $conn->begin_transaction();

    try {
        $upsert = $conn->prepare(
            'INSERT INTO attendance (schedule_id, staff_id, check_in_time, check_out_time, attendance_status)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                check_in_time = VALUES(check_in_time),
                check_out_time = VALUES(check_out_time),
                attendance_status = VALUES(attendance_status)'
        );
        if (!$upsert) {
            throw new RuntimeException('Unable to prepare attendance update.');
        }

        $upsert->bind_param('iisss', $scheduleId, $staffId, $checkInSql, $checkOutSql, $newStatus);
        $upsert->execute();
        $upsert->close();

        if ($newStatus === 'completed') {
            $updSched = $conn->prepare("UPDATE schedules SET status = 'completed' WHERE id = ? AND status = 'scheduled'");
            if ($updSched) {
                $updSched->bind_param('i', $scheduleId);
                $updSched->execute();
                $updSched->close();
            }
        }

        $label = ucwords(str_replace('_', ' ', $newStatus));
        admin_attendance_write_audit(
            $conn,
            $adminId,
            (string) $scheduleId,
            "Updated attendance for {$shift['staff_name']} ({$schedDate}) to {$label}.",
            $ipAddress
        );

        $conn->commit();

        return [
            'ok' => true,
            'message' => "Attendance for {$shift['staff_name']} on {$schedDate} updated to {$label}.",
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Unable to update attendance record. Please try again.'];
    }
}