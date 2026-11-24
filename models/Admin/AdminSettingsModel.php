<?php
declare(strict_types=1);

// --- Shared Audit Helper ---

function admin_settings_write_audit(
    mysqli $conn,
    int $adminId,
    string $action,
    string $entityType,
    string $entityId,
    string $description,
    string $ipAddress
): void {
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

// --- Administrator Profile ---

function admin_update_profile_settings(
    mysqli $conn,
    int $adminId,
    string $fullName,
    string $position,
    ?string $newPicturePath,
    bool $removePicture,
    string $ipAddress
): array {
    $cleanName = trim($fullName);
    $cleanPosition = trim($position);

    if ($cleanName === '') {
        return ['ok' => false, 'message' => 'Full name is required.'];
    }

    $positionParam = $cleanPosition !== '' ? $cleanPosition : 'Administrator';

    if ($removePicture) {
        $stmt = $conn->prepare(
            "UPDATE users
             SET full_name = ?, position = ?, profile_picture = NULL
             WHERE id = ? AND role = 'admin'"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to update administrator profile.'];
        }
        $stmt->bind_param('ssi', $cleanName, $positionParam, $adminId);
    } elseif ($newPicturePath !== null && $newPicturePath !== '') {
        $stmt = $conn->prepare(
            "UPDATE users
             SET full_name = ?, position = ?, profile_picture = ?
             WHERE id = ? AND role = 'admin'"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to update administrator profile.'];
        }
        $stmt->bind_param('sssi', $cleanName, $positionParam, $newPicturePath, $adminId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE users
             SET full_name = ?, position = ?
             WHERE id = ? AND role = 'admin'"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to update administrator profile.'];
        }
        $stmt->bind_param('ssi', $cleanName, $positionParam, $adminId);
    }

    $stmt->execute();
    $stmt->close();

    admin_settings_write_audit(
        $conn,
        $adminId,
        'update_admin_profile',
        'users',
        (string) $adminId,
        'Administrator updated profile details.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Administrator profile updated.'];
}

// --- Email Verification ---

function admin_get_email_verification_state(mysqli $conn, int $adminId, string $currentEmail): array
{
    $normalizedCurrent = strtolower(trim($currentEmail));
    $state = [
        'isVerified' => false,
        'verifiedEmail' => '',
        'pendingEmail' => '',
        'expiresAt' => '',
        'verifiedAt' => '',
    ];

    $stmt = $conn->prepare(
        'SELECT verified_email, pending_email, expires_at, verified_at
         FROM admin_email_verifications
         WHERE admin_user_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return $state;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return $state;
    }

    $verifiedEmail = strtolower(trim((string) ($row['verified_email'] ?? '')));
    $pendingEmail = strtolower(trim((string) ($row['pending_email'] ?? '')));
    $verifiedAt = (string) ($row['verified_at'] ?? '');

    $state['verifiedEmail'] = $verifiedEmail;
    $state['pendingEmail'] = $pendingEmail;
    $state['expiresAt'] = (string) ($row['expires_at'] ?? '');
    $state['verifiedAt'] = $verifiedAt;
    $state['isVerified'] = (
        $verifiedEmail !== ''
        && $verifiedEmail === $normalizedCurrent
        && $verifiedAt !== ''
        && $pendingEmail === ''
    );

    return $state;
}

function admin_start_email_verification(
    mysqli $conn,
    int $adminId,
    string $targetEmail,
    string $ipAddress
): array {
    $email = strtolower(trim($targetEmail));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    $dupStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    if ($dupStmt) {
        $dupStmt->bind_param('si', $email, $adminId);
        $dupStmt->execute();
        $exists = $dupStmt->get_result()->fetch_assoc();
        $dupStmt->close();

        if ($exists) {
            return ['ok' => false, 'message' => 'That email address is already assigned to another account.'];
        }
    }

    $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = password_hash($plainCode, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        'INSERT INTO admin_email_verifications (
            admin_user_id, pending_email, code_hash, expires_at, attempts, last_sent_at
         ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE
            pending_email = VALUES(pending_email),
            code_hash = VALUES(code_hash),
            expires_at = VALUES(expires_at),
            attempts = 0,
            last_sent_at = NOW()'
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Unable to generate email verification code.'];
    }

    $stmt->bind_param('iss', $adminId, $email, $codeHash);
    $stmt->execute();
    $stmt->close();

    admin_settings_write_audit(
        $conn,
        $adminId,
        'request_admin_email_verification',
        'admin_email_verifications',
        (string) $adminId,
        'Administrator requested email verification code for ' . $email,
        $ipAddress
    );

    return [
        'ok' => true,
        'code' => $plainCode,
        'email' => $email,
        'message' => "A 6-digit verification code has been sent to {$email}.",
    ];
}

function admin_confirm_email_verification(
    mysqli $conn,
    int $adminId,
    string $submittedCode,
    string $ipAddress
): array {
    $code = trim($submittedCode);

    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'message' => 'Please enter the 6-digit verification code.'];
    }

    $stmt = $conn->prepare(
        'SELECT pending_email, code_hash, expires_at, attempts
         FROM admin_email_verifications
         WHERE admin_user_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Unable to verify code right now.'];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['pending_email']) || empty($row['code_hash'])) {
        return ['ok' => false, 'message' => 'No pending email verification found.'];
    }

    if ((int) ($row['attempts'] ?? 0) >= 5) {
        return ['ok' => false, 'message' => 'Too many failed attempts. Please request a new verification code.'];
    }

    $expiresAt = strtotime((string) ($row['expires_at'] ?? ''));
    if ($expiresAt === false || $expiresAt < time()) {
        return ['ok' => false, 'message' => 'That verification code has expired. Please request a new code.'];
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        $inc = $conn->prepare('UPDATE admin_email_verifications SET attempts = attempts + 1 WHERE admin_user_id = ?');
        if ($inc) {
            $inc->bind_param('i', $adminId);
            $inc->execute();
            $inc->close();
        }
        return ['ok' => false, 'message' => 'Invalid verification code. Please check your email and try again.'];
    }

    $verifiedEmail = strtolower(trim((string) $row['pending_email']));
    $conn->begin_transaction();

    try {
        $updUser = $conn->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'admin'");
        if (!$updUser) {
            throw new RuntimeException('Unable to update admin email.');
        }
        $updUser->bind_param('si', $verifiedEmail, $adminId);
        $updUser->execute();
        $updUser->close();

        $updVer = $conn->prepare(
            'UPDATE admin_email_verifications
             SET verified_email = ?,
                 pending_email = NULL,
                 code_hash = NULL,
                 expires_at = NULL,
                 attempts = 0,
                 verified_at = NOW()
             WHERE admin_user_id = ?'
        );
        if (!$updVer) {
            throw new RuntimeException('Unable to finalize email verification.');
        }
        $updVer->bind_param('si', $verifiedEmail, $adminId);
        $updVer->execute();
        $updVer->close();

        admin_settings_write_audit(
            $conn,
            $adminId,
            'verify_admin_email',
            'users',
            (string) $adminId,
            'Administrator verified email address ' . $verifiedEmail,
            $ipAddress
        );

        $conn->commit();

        return [
            'ok' => true,
            'email' => $verifiedEmail,
            'message' => 'Email address verified successfully.',
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Unable to complete email verification.'];
    }
}

function admin_cancel_email_verification(mysqli $conn, int $adminId): void
{
    $stmt = $conn->prepare(
        'UPDATE admin_email_verifications
         SET pending_email = NULL, code_hash = NULL, expires_at = NULL, attempts = 0
         WHERE admin_user_id = ?'
    );
    if ($stmt) {
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Password & Recovery Codes ---

function admin_change_own_password(
    mysqli $conn,
    int $adminId,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword,
    string $ipAddress
): array {
    if ($currentPassword === '' || strlen($newPassword) < 8) {
        return ['ok' => false, 'message' => 'New password must be at least 8 characters long.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'message' => 'New password and confirmation do not match.'];
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Unable to verify current password.'];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
        return ['ok' => false, 'message' => 'Your current password is incorrect.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ? AND role = 'admin'");
    if (!$upd) {
        return ['ok' => false, 'message' => 'Unable to update password.'];
    }

    $upd->bind_param('si', $newHash, $adminId);
    $upd->execute();
    $upd->close();

    admin_settings_write_audit(
        $conn,
        $adminId,
        'change_admin_password',
        'users',
        (string) $adminId,
        'Administrator updated account password.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Administrator password updated successfully.'];
}

function admin_count_active_recovery_codes(mysqli $conn, int $adminId): int
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM admin_recovery_codes
         WHERE admin_user_id = ? AND consumed_at IS NULL AND revoked_at IS NULL'
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function admin_regenerate_recovery_codes(mysqli $conn, int $adminId, string $ipAddress): array
{
    $conn->begin_transaction();

    try {
        $revoke = $conn->prepare(
            'UPDATE admin_recovery_codes
             SET revoked_at = NOW()
             WHERE admin_user_id = ? AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        if ($revoke) {
            $revoke->bind_param('i', $adminId);
            $revoke->execute();
            $revoke->close();
        }

        $insert = $conn->prepare(
            'INSERT INTO admin_recovery_codes (admin_user_id, code_hash) VALUES (?, ?)'
        );
        if (!$insert) {
            throw new RuntimeException('Unable to prepare recovery code insert.');
        }

        $plainCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $formatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
            $hash = password_hash($formatted, PASSWORD_DEFAULT);

            $insert->bind_param('is', $adminId, $hash);
            $insert->execute();
            $plainCodes[] = $formatted;
        }
        $insert->close();

        admin_settings_write_audit(
            $conn,
            $adminId,
            'regenerate_recovery_codes',
            'admin_recovery_codes',
            (string) $adminId,
            'Administrator generated 8 new account recovery codes.',
            $ipAddress
        );

        $conn->commit();

        return [
            'ok' => true,
            'codes' => $plainCodes,
            'message' => 'Generated 8 new recovery codes. Save them in a secure place now.',
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Unable to generate recovery codes.'];
    }
}

// --- Workforce Rules & Job Categories ---

function admin_get_workforce_settings(mysqli $conn): array
{
    $defaults = [
        'late_grace_minutes' => '15',
        'early_check_in_minutes' => '30',
        'default_shift_start' => '06:00',
        'default_shift_end' => '14:00',
    ];

    $result = $conn->query('SELECT setting_key, setting_value FROM system_settings');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = (string) $row['setting_key'];
            if (array_key_exists($key, $defaults) && $row['setting_value'] !== null) {
                $defaults[$key] = (string) $row['setting_value'];
            }
        }
    }

    return $defaults;
}

function admin_save_workforce_settings(mysqli $conn, int $adminId, array $input, string $ipAddress): array
{
    $lateGrace = max(0, min(120, (int) ($input['late_grace_minutes'] ?? 15)));
    $earlyCheckIn = max(0, min(180, (int) ($input['early_check_in_minutes'] ?? 30)));
    $defaultStart = preg_match('/^\d{2}:\d{2}$/', (string) ($input['default_shift_start'] ?? ''))
        ? (string) $input['default_shift_start']
        : '06:00';
    $defaultEnd = preg_match('/^\d{2}:\d{2}$/', (string) ($input['default_shift_end'] ?? ''))
        ? (string) $input['default_shift_end']
        : '14:00';

    $pairs = [
        'late_grace_minutes' => (string) $lateGrace,
        'early_check_in_minutes' => (string) $earlyCheckIn,
        'default_shift_start' => $defaultStart,
        'default_shift_end' => $defaultEnd,
    ];

    $stmt = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by)'
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Unable to save workforce settings.'];
    }

    foreach ($pairs as $key => $val) {
        $stmt->bind_param('ssi', $key, $val, $adminId);
        $stmt->execute();
    }
    $stmt->close();

    admin_settings_write_audit(
        $conn,
        $adminId,
        'update_system_settings',
        'system_settings',
        'workforce',
        'Administrator updated workforce attendance and shift defaults.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Workforce shift & attendance rules saved.'];
}

function admin_get_all_job_categories(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT id, category_name, description, is_active
         FROM job_categories
         ORDER BY is_active DESC, category_name ASC'
    );

    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function admin_manage_job_category(
    mysqli $conn,
    int $adminId,
    string $mode,
    string $name,
    string $description,
    int $categoryId,
    string $ipAddress
): array {
    if ($mode === 'add') {
        $cleanName = trim($name);
        $cleanDesc = trim($description);

        if ($cleanName === '') {
            return ['ok' => false, 'message' => 'Please enter a job category name.'];
        }

        $dup = $conn->prepare('SELECT id FROM job_categories WHERE category_name = ? LIMIT 1');
        if ($dup) {
            $dup->bind_param('s', $cleanName);
            $dup->execute();
            $exists = $dup->get_result()->fetch_assoc();
            $dup->close();

            if ($exists) {
                return ['ok' => false, 'message' => 'That job category already exists.'];
            }
        }

        $descParam = $cleanDesc !== '' ? $cleanDesc : null;
        $stmt = $conn->prepare('INSERT INTO job_categories (category_name, description, is_active) VALUES (?, ?, 1)');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to add job category.'];
        }

        $stmt->bind_param('ss', $cleanName, $descParam);
        $stmt->execute();
        $newId = (string) $conn->insert_id;
        $stmt->close();

        $auditDesc = 'Administrator added job category: ' . $cleanName;
        $flashMsg = "Added job category \"{$cleanName}\".";
    } elseif ($mode === 'toggle' && $categoryId > 0) {
        $stmt = $conn->prepare('UPDATE job_categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to update job category status.'];
        }
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $stmt->close();

        $newId = (string) $categoryId;
        $auditDesc = 'Administrator toggled active status for job category #' . $categoryId;
        $flashMsg = 'Job category availability updated.';
    } else {
        return ['ok' => false, 'message' => 'Invalid job category action.'];
    }

    admin_settings_write_audit(
        $conn,
        $adminId,
        'manage_job_category',
        'job_categories',
        $newId,
        $auditDesc,
        $ipAddress
    );

    return ['ok' => true, 'message' => $flashMsg];
}