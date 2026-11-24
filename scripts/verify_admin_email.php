<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$baseUrl = '/Scheduling_System';
$settingsUrl = $baseUrl . '/admin/settings.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token()) {
    set_flash_message(
        'danger',
        'Your session expired or the security token was invalid. Please try again.'
    );
    redirect($settingsUrl);
}

$adminId = (int) (current_user()['id'] ?? 0);
$code = trim((string) ($_POST['verification_code'] ?? ''));

if ($adminId < 1) {
    logout_user();
}

if (!preg_match('/^[0-9]{6}$/', $code)) {
    set_flash_message('danger', 'Enter a valid six-digit verification code.');
    redirect($settingsUrl);
}

try {
    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Get the current administrator email
    |--------------------------------------------------------------------------
    */

    $role = 'admin';
    $status = 'active';

    $stmt = $conn->prepare(
        'SELECT email
         FROM users
         WHERE id = ? AND role = ? AND account_status = ?
         LIMIT 1'
    );

    $stmt->bind_param('iss', $adminId, $role, $status);
    $stmt->execute();
    $stmt->bind_result($adminEmail);

    $adminFound = $stmt->fetch();
    $stmt->close();

    if (!$adminFound || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Administrator account could not be verified.');
    }

    /*
    |--------------------------------------------------------------------------
    | Lock the verification record
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        'SELECT pending_email, code_hash, expires_at, attempts
         FROM admin_email_verifications
         WHERE admin_user_id = ?
         FOR UPDATE'
    );

    $stmt->bind_param('i', $adminId);
    $stmt->execute();

    $stmt->bind_result(
        $pendingEmail,
        $codeHash,
        $expiresAt,
        $attempts
    );

    $recordFound = $stmt->fetch();
    $stmt->close();

    if (!$recordFound || empty($pendingEmail) || empty($codeHash)) {
        throw new RuntimeException(
            'No active verification code was found. Request a new code from Settings.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm the code belongs to the current registered email
    |--------------------------------------------------------------------------
    */

    if (strcasecmp((string) $pendingEmail, (string) $adminEmail) !== 0) {
        $stmt = $conn->prepare(
            'UPDATE admin_email_verifications
             SET pending_email = NULL,
                 code_hash = NULL,
                 expires_at = NULL,
                 attempts = 0,
                 verified_email = NULL,
                 verified_at = NULL
             WHERE admin_user_id = ?'
        );

        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        set_flash_message(
            'warning',
            'Your registered email address has changed. Request a new verification code.'
        );

        redirect($settingsUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Check expiration
    |--------------------------------------------------------------------------
    */

    $expiryTimestamp = strtotime((string) $expiresAt);

    if ($expiryTimestamp === false || $expiryTimestamp < time()) {
        $stmt = $conn->prepare(
            'UPDATE admin_email_verifications
             SET pending_email = NULL,
                 code_hash = NULL,
                 expires_at = NULL,
                 attempts = 0
             WHERE admin_user_id = ?'
        );

        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        set_flash_message(
            'warning',
            'Your verification code has expired. Please request a new one.'
        );

        redirect($settingsUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Limit incorrect attempts
    |--------------------------------------------------------------------------
    */

    $attempts = (int) $attempts;

    if ($attempts >= 5) {
        $stmt = $conn->prepare(
            'UPDATE admin_email_verifications
             SET pending_email = NULL,
                 code_hash = NULL,
                 expires_at = NULL,
                 attempts = 0
             WHERE admin_user_id = ?'
        );

        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        set_flash_message(
            'danger',
            'Too many incorrect attempts. Request a new verification code.'
        );

        redirect($settingsUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify the submitted code
    |--------------------------------------------------------------------------
    */

    if (!password_verify($code, (string) $codeHash)) {
        $newAttempts = $attempts + 1;

        if ($newAttempts >= 5) {
            $stmt = $conn->prepare(
                'UPDATE admin_email_verifications
                 SET attempts = ?,
                     pending_email = NULL,
                     code_hash = NULL,
                     expires_at = NULL
                 WHERE admin_user_id = ?'
            );

            $stmt->bind_param('ii', $newAttempts, $adminId);
        } else {
            $stmt = $conn->prepare(
                'UPDATE admin_email_verifications
                 SET attempts = ?
                 WHERE admin_user_id = ?'
            );

            $stmt->bind_param('ii', $newAttempts, $adminId);
        }

        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if ($newAttempts >= 5) {
            set_flash_message(
                'danger',
                'You entered the wrong code five times. Request a new verification code.'
            );
        } else {
            $remaining = 5 - $newAttempts;

            set_flash_message(
                'danger',
                'Incorrect verification code. You have ' .
                $remaining . ' attempt(s) remaining.'
            );
        }

        redirect($settingsUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Successful verification
    |--------------------------------------------------------------------------
    */

    $verifiedEmail = (string) $pendingEmail;

    $stmt = $conn->prepare(
        'UPDATE admin_email_verifications
         SET verified_email = ?,
             verified_at = NOW(),
             pending_email = NULL,
             code_hash = NULL,
             expires_at = NULL,
             attempts = 0
         WHERE admin_user_id = ?'
    );

    $stmt->bind_param('si', $verifiedEmail, $adminId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    set_flash_message(
        'success',
        'Your administrator email address has been successfully verified.'
    );

    redirect($settingsUrl);

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // The transaction may already have been committed.
    }

    error_log('Admin email verification failed: ' . $e->getMessage());

    set_flash_message(
        'danger',
        'Email verification could not be completed. Please try again.'
    );

    redirect($settingsUrl);
}
