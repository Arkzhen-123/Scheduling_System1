<?php
declare(strict_types=1);

// Fetch a user record by email address for login verification.
function user_find_by_email(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, employee_id, full_name, email, password_hash, role, position, account_status, must_change_password
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($user) ? $user : null;
}

// Update the user's last login timestamp and save an audit log entry.
function user_record_login(mysqli $conn, int $userId, string $ipAddress): void
{
    $stmt = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ? LIMIT 1');

    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    $action = 'login';
    $entityType = 'users';
    $entityId = (string) $userId;
    $description = 'User signed in to the system.';
    $safeIp = substr($ipAddress, 0, 45);

    $audit = $conn->prepare(
        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($audit) {
        $audit->bind_param('isssss', $userId, $action, $entityType, $entityId, $description, $safeIp);
        $audit->execute();
        $audit->close();
    }
}

// Save a staff password-assistance request and notify active administrators.
function user_create_password_assistance_request(
    mysqli $conn,
    string $email,
    string $employeeId,
    string $message
): void {
    $matchedUser = user_find_by_email($conn, $email);
    $userId = ($matchedUser !== null && ($matchedUser['role'] ?? '') === 'staff')
        ? (int) $matchedUser['id']
        : null;

    $cleanEmployeeId = $employeeId !== '' ? $employeeId : null;
    $cleanMessage = $message !== '' ? $message : null;
    $status = 'pending';
    $isRead = 0;

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'INSERT INTO password_assistance_requests
                (user_id, submitted_email, submitted_employee_id, message, status, is_read)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new RuntimeException('Unable to prepare password assistance request.');
        }

        $stmt->bind_param('issssi', $userId, $email, $cleanEmployeeId, $cleanMessage, $status, $isRead);
        $stmt->execute();
        $requestId = (int) $conn->insert_id;
        $stmt->close();

        $adminQuery = $conn->query("SELECT id FROM users WHERE role = 'admin' AND account_status = 'active'");

        if ($adminQuery) {
            $notifType = 'password_assistance';
            $notifTitle = 'Staff Password Assistance Request';
            $notifBody = 'A password assistance request was submitted for ' . $email . '.';

            $notifStmt = $conn->prepare(
                'INSERT INTO notifications
                    (recipient_user_id, request_id, notification_type, title, message, is_read)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            if ($notifStmt) {
                while ($adminRow = $adminQuery->fetch_assoc()) {
                    $recipientId = (int) $adminRow['id'];
                    $notifStmt->bind_param('iisssi', $recipientId, $requestId, $notifType, $notifTitle, $notifBody, $isRead);
                    $notifStmt->execute();
                }
                $notifStmt->close();
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

// Save an audit log entry when a user signs out.
function user_record_logout(mysqli $conn, int $userId, string $ipAddress): void
{
    $action = 'logout';
    $entityType = 'users';
    $entityId = (string) $userId;
    $description = 'User signed out of the system.';
    $safeIp = substr($ipAddress, 0, 45);

    $audit = $conn->prepare(
        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($audit) {
        $audit->bind_param('isssss', $userId, $action, $entityType, $entityId, $description, $safeIp);
        $audit->execute();
        $audit->close();
    }
}