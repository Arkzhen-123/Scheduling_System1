<?php
declare(strict_types=1);

// Fetch active staff account details by user ID.
function staff_find_active_by_id(mysqli $conn, int $staffId): ?array
{
    $stmt = $conn->prepare(
        'SELECT
            id,
            employee_id,
            full_name,
            email,
            role,
            position,
            account_status,
            must_change_password,
            profile_picture
         FROM users
         WHERE id = ?
           AND role = ?
           AND account_status = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $role = 'staff';
    $activeStatus = 'active';
    $stmt->bind_param('iss', $staffId, $role, $activeStatus);
    $stmt->execute();

    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($staff) ? $staff : null;
}

// Fetch all non-cancelled schedules assigned to the staff member for today.
function staff_get_today_schedules(mysqli $conn, int $staffId, string $today): array
{
    $stmt = $conn->prepare(
        'SELECT
            s.id,
            s.schedule_date,
            s.start_time,
            s.end_time,
            s.task_description,
            s.work_location,
            s.notes,
            s.status,
            jc.category_name,
            a.id AS attendance_id,
            a.check_in_time,
            a.check_out_time,
            a.attendance_status
         FROM schedules s
         LEFT JOIN job_categories jc
            ON jc.id = s.assigned_category_id
         LEFT JOIN attendance a
            ON a.schedule_id = s.id
            AND a.staff_id = s.staff_id
         WHERE s.staff_id = ?
           AND s.schedule_date = ?
           AND s.status <> ?
         ORDER BY s.start_time ASC'
    );

    if (!$stmt) {
        return [];
    }

    $cancelledStatus = 'cancelled';
    $stmt->bind_param('iss', $staffId, $today, $cancelledStatus);
    $stmt->execute();

    $schedules = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    $stmt->close();

    return $schedules;
}

// Fetch all non-cancelled schedules and attendance records within a month range.
function staff_get_month_schedules(
    mysqli $conn,
    int $staffId,
    string $monthStart,
    string $monthEnd
): array {
    $stmt = $conn->prepare(
        'SELECT
            s.id,
            s.schedule_date,
            s.start_time,
            s.end_time,
            s.task_description,
            s.work_location,
            s.notes,
            s.status,
            jc.category_name,
            a.check_in_time,
            a.check_out_time,
            a.attendance_status
         FROM schedules s
         LEFT JOIN job_categories jc
            ON jc.id = s.assigned_category_id
         LEFT JOIN attendance a
            ON a.schedule_id = s.id
            AND a.staff_id = s.staff_id
         WHERE s.staff_id = ?
           AND s.schedule_date BETWEEN ? AND ?
           AND s.status <> ?
         ORDER BY s.schedule_date ASC, s.start_time ASC'
    );

    if (!$stmt) {
        return [];
    }

    $cancelledStatus = 'cancelled';
    $stmt->bind_param('isss', $staffId, $monthStart, $monthEnd, $cancelledStatus);
    $stmt->execute();

    $schedules = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    $stmt->close();

    return $schedules;
}

// Record a staff check-in or check-out transaction and write an audit log entry.
function staff_record_attendance(
    mysqli $conn,
    int $staffId,
    int $scheduleId,
    string $today,
    string $action,
    string $ipAddress
): string {
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'SELECT id, schedule_date, start_time, end_time, status
             FROM schedules
             WHERE id = ?
               AND staff_id = ?
               AND schedule_date = ?
               AND status = ?
             LIMIT 1
             FOR UPDATE'
        );

        if (!$stmt) {
            throw new RuntimeException('Unable to verify scheduled shift.');
        }

        $scheduledStatus = 'scheduled';
        $stmt->bind_param('iiss', $scheduleId, $staffId, $today, $scheduledStatus);
        $stmt->execute();
        $validSchedule = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$validSchedule) {
            throw new RuntimeException('You do not have a valid scheduled shift for this attendance action.');
        }

        $stmt = $conn->prepare(
            'SELECT id, check_in_time, check_out_time
             FROM attendance
             WHERE schedule_id = ?
               AND staff_id = ?
             LIMIT 1
             FOR UPDATE'
        );

        if (!$stmt) {
            throw new RuntimeException('Unable to load attendance record.');
        }

        $stmt->bind_param('ii', $scheduleId, $staffId);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($action === 'check_in') {
            if ($attendance && !empty($attendance['check_in_time'])) {
                throw new RuntimeException('You have already checked in for this shift.');
            }

            $scheduledStart = strtotime($today . ' ' . $validSchedule['start_time']);
            $attendanceStatus = ($scheduledStart !== false && time() > $scheduledStart) ? 'late' : 'present';

            if ($attendance) {
                $attendanceId = (int) $attendance['id'];
                $stmt = $conn->prepare(
                    'UPDATE attendance
                     SET check_in_time = NOW(),
                         attendance_status = ?
                     WHERE id = ?
                       AND staff_id = ?
                       AND check_in_time IS NULL'
                );

                if (!$stmt) {
                    throw new RuntimeException('Unable to update attendance record.');
                }

                $stmt->bind_param('sii', $attendanceStatus, $attendanceId, $staffId);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('The attendance record could not be updated.');
                }

                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO attendance (schedule_id, staff_id, check_in_time, attendance_status)
                     VALUES (?, ?, NOW(), ?)'
                );

                if (!$stmt) {
                    throw new RuntimeException('Unable to create attendance record.');
                }

                $stmt->bind_param('iis', $scheduleId, $staffId, $attendanceStatus);
                $stmt->execute();
                $attendanceId = (int) $conn->insert_id;
                $stmt->close();
            }

            $logAction = 'attendance_check_in';
            $logDescription = $attendanceStatus === 'late'
                ? 'Staff checked in late for a scheduled shift.'
                : 'Staff checked in for a scheduled shift.';
            $successMessage = $attendanceStatus === 'late'
                ? 'You have checked in. Your attendance is marked as late.'
                : 'You have successfully checked in.';
        } else {
            if (!$attendance || empty($attendance['check_in_time']) || !empty($attendance['check_out_time'])) {
                throw new RuntimeException('No active check-in was found for this shift.');
            }

            $attendanceId = (int) $attendance['id'];
            $stmt = $conn->prepare(
                "UPDATE attendance
                 SET check_out_time = NOW(),
                     attendance_status = 'completed'
                 WHERE id = ?
                   AND staff_id = ?
                   AND check_out_time IS NULL"
            );

            if (!$stmt) {
                throw new RuntimeException('Unable to update check-out record.');
            }

            $stmt->bind_param('ii', $attendanceId, $staffId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('The attendance record could not be checked out.');
            }

            $stmt->close();

            $logAction = 'attendance_check_out';
            $logDescription = 'Staff checked out from a scheduled shift.';
            $successMessage = 'You have successfully checked out.';
        }

        $entityType = 'attendance';
        $entityId = (string) $attendanceId;
        $safeIp = substr($ipAddress, 0, 45);

        $stmt = $conn->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $stmt->bind_param('isssss', $staffId, $logAction, $entityType, $entityId, $logDescription, $safeIp);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        return $successMessage;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

// Update the staff member's profile picture path in the database.
function staff_update_profile_picture(mysqli $conn, int $staffId, string $relativePath): void
{
    $role = 'staff';
    $stmt = $conn->prepare('UPDATE users SET profile_picture = ? WHERE id = ? AND role = ? LIMIT 1');

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare profile picture update.');
    }

    $stmt->bind_param('sis', $relativePath, $staffId, $role);
    $stmt->execute();
    $stmt->close();
}

// Verify the current password and update the staff member's password hash.
function staff_change_password(
    mysqli $conn,
    int $staffId,
    string $currentPassword,
    string $newPassword
): void {
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');

    if (!$stmt) {
        throw new RuntimeException('Unable to verify current password.');
    }

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
        throw new RuntimeException('Current password is incorrect.');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $mustChange = 0;

    $update = $conn->prepare('UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ? LIMIT 1');

    if (!$update) {
        throw new RuntimeException('Unable to save your new password.');
    }

    $update->bind_param('sii', $newHash, $mustChange, $staffId);
    $update->execute();
    $update->close();
}