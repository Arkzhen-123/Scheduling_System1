<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$baseUrl = '/Scheduling_System';
$errorMessage = '';
$token = '';
$tokenIsValid = false;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

/** Escape output for HTML. */
function reset_page_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Check that a reset token is unused, unexpired, and still belongs to the
 * active administrator whose registered email remains verified.
 */
function find_valid_admin_reset(mysqli $conn, string $tokenHash): ?array
{
    $stmt = $conn->prepare(
        "SELECT t.id AS token_id,
                t.user_id,
                t.expires_at,
                t.used_at,
                u.email,
                u.full_name
         FROM password_reset_tokens AS t
         INNER JOIN users AS u ON u.id = t.user_id
         INNER JOIN admin_email_verifications AS v
             ON v.admin_user_id = u.id
            AND v.verified_email = u.email
            AND v.verified_at IS NOT NULL
         WHERE t.token_hash = ?
           AND t.used_at IS NULL
           AND t.expires_at > NOW()
           AND u.role = 'admin'
           AND u.account_status = 'active'
         LIMIT 1"
    );

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $record ?: null;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token()) {
        $errorMessage = 'Your session expired or the security token was invalid. Please reopen the reset link.';
    }

    $token = isset($_POST['token']) && is_string($_POST['token'])
        ? trim($_POST['token'])
        : '';

    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';

    if ($errorMessage === '') {
        if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
            $errorMessage = 'This password-reset link is invalid or has expired. Request a new link.';
        } elseif (strlen($password) < 12) {
            $errorMessage = 'Your new password must contain at least 12 characters.';
        } elseif (strlen($password) > 72) {
            $errorMessage = 'Your password must not exceed 72 bytes. Please use a shorter password.';
        } elseif ($password !== $confirmPassword) {
            $errorMessage = 'The password confirmation does not match.';
        }
    }

    if ($errorMessage === '') {
        $tokenHash = hash('sha256', $token);
        $conn->begin_transaction();

        try {
            // Lock the token row to prevent two simultaneous submissions.
            $stmt = $conn->prepare(
                'SELECT id, user_id, expires_at, used_at
                 FROM password_reset_tokens
                 WHERE token_hash = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $resetToken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                $resetToken === null
                || $resetToken['used_at'] !== null
                || strtotime((string) $resetToken['expires_at']) <= time()
            ) {
                throw new RuntimeException('This password-reset link is invalid or has expired. Request a new link.');
            }

            $adminId = (int) $resetToken['user_id'];

            // Lock the account and confirm it is still an active administrator.
            $stmt = $conn->prepare(
                "SELECT id, email
                 FROM users
                 WHERE id = ?
                   AND role = 'admin'
                   AND account_status = 'active'
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin === null) {
                throw new RuntimeException('This administrator account is not eligible for password recovery.');
            }

            // Email verification must still match the current account email.
            $registeredEmail = (string) $admin['email'];
            $stmt = $conn->prepare(
                'SELECT id
                 FROM admin_email_verifications
                 WHERE admin_user_id = ?
                   AND verified_email = ?
                   AND verified_at IS NOT NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->bind_param('is', $adminId, $registeredEmail);
            $stmt->execute();
            $emailStillVerified = $stmt->get_result()->fetch_assoc() !== null;
            $stmt->close();

            if (!$emailStillVerified) {
                throw new RuntimeException('The administrator email is no longer verified. Contact the system administrator.');
            }

            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);

            if (!is_string($newPasswordHash) || $newPasswordHash === '') {
                throw new RuntimeException('The new password could not be secured. Please try again.');
            }

            $stmt = $conn->prepare(
                "UPDATE users
                 SET password_hash = ?,
                     must_change_password = 0
                 WHERE id = ? AND role = 'admin'"
            );
            $stmt->bind_param('si', $newPasswordHash, $adminId);
            $stmt->execute();
            $stmt->close();

            // Mark every outstanding reset link for this administrator as used.
            $stmt = $conn->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = COALESCE(used_at, NOW())
                 WHERE user_id = ? AND used_at IS NULL'
            );
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            set_flash_message('success', 'Your administrator password has been changed. You can now sign in using your new password.');
            redirect($baseUrl . '/login.php');
        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The password could not be reset. Please request a new link and try again.';

            if (!$e instanceof RuntimeException) {
                error_log('Admin password reset failed: ' . $e->getMessage());
            }
        }
    }
}

if ($token === '') {
    $token = isset($_GET['token']) && is_string($_GET['token'])
        ? trim($_GET['token'])
        : '';
}

if (preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    try {
        $tokenHash = hash('sha256', $token);
        $tokenIsValid = find_valid_admin_reset($conn, $tokenHash) !== null;
    } catch (Throwable $e) {
        error_log('Admin reset-link validation failed: ' . $e->getMessage());
        $tokenIsValid = false;
    }
}

if (!$tokenIsValid && $errorMessage === '') {
    $errorMessage = 'This password-reset link is invalid, expired, or has already been used. Request a new link.';
}

$pageTitle = 'Reset Administrator Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= reset_page_escape($pageTitle) ?> | <?= reset_page_escape(COMPANY_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --green: #2e7d32; --green-dark: #1b5e20; --green-light: #e8f5e9; }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; display: flex; flex-direction: column;
            background: #f4f8f4; color: #26332a; font-family: Arial, Helvetica, sans-serif;
        }
        .reset-header, .reset-footer { background: #fff; border-color: #dce8dd; }
        .reset-header { border-bottom: 1px solid #dce8dd; padding: 16px 0; }
        .brand { display: flex; align-items: center; gap: 12px; color: #245c31; text-decoration: none; font-weight: 700; }
        .brand img { width: 46px; height: 46px; object-fit: contain; }
        .brand-subtitle { display: block; color: #718075; font-size: .8rem; font-weight: 400; }
        main { flex: 1; display: grid; place-items: center; padding: 36px 16px; }
        .reset-card {
            width: 100%; max-width: 470px; padding: 32px; background: #fff;
            border: 1px solid #dce8dd; border-radius: 16px;
            box-shadow: 0 16px 45px rgba(31, 69, 38, .10);
        }
        .reset-card h1 { font-size: 1.55rem; font-weight: 750; }
        .reset-card .form-control { min-height: 46px; border-color: #d5dfd6; }
        .reset-card .form-control:focus { border-color: #66bb6a; box-shadow: 0 0 0 .2rem rgba(46,125,50,.14); }
        .btn-reset { width: 100%; min-height: 46px; background: var(--green); border-color: var(--green); color: #fff; font-weight: 650; }
        .btn-reset:hover { background: var(--green-dark); border-color: var(--green-dark); color: #fff; }
        .reset-footer { border-top: 1px solid #dce8dd; padding: 15px; text-align: center; color: #718075; font-size: .8rem; }
        @media (max-width: 480px) { .reset-card { padding: 24px 20px; } }
    </style>
</head>
<body>
<header class="reset-header">
    <div class="container">
        <a class="brand" href="<?= reset_page_escape($baseUrl) ?>/login.php">
            <?php $logoFile = __DIR__ . '/assets/images/company-logo.png'; ?>
            <?php if (is_file($logoFile)): ?>
                <img src="<?= reset_page_escape($baseUrl) ?>/assets/images/company-logo.png" alt="Mang Lechon Restaurant logo">
            <?php endif; ?>
            <span>
                <?= reset_page_escape(COMPANY_NAME) ?>
                <span class="brand-subtitle">Workforce Management System</span>
            </span>
        </a>
    </div>
</header>
<main>
    <section class="reset-card" aria-labelledby="reset-title">
        <div class="mb-4">
            <h1 id="reset-title" class="mb-2">Set a New Password</h1>
            <p class="text-secondary mb-0">Choose a new password for your administrator account.</p>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= reset_page_escape($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($tokenIsValid): ?>
            <form method="post" action="<?= reset_page_escape($baseUrl) ?>/reset_admin_password.php" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= reset_page_escape($token) ?>">

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">New password</label>
                    <input type="password" class="form-control" id="password" name="password"
                        minlength="12" maxlength="72" autocomplete="new-password" required>
                    <div class="form-text">Use at least 12 characters. Maximum: 72 bytes.</div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm new password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                        minlength="12" maxlength="72" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-reset">Change Password</button>
            </form>
        <?php else: ?>
            <p class="text-secondary">For security, this link cannot be reused. Return to the login page and request a new password-reset link.</p>
            <a class="btn btn-reset d-flex align-items-center justify-content-center text-decoration-none"
                href="<?= reset_page_escape($baseUrl) ?>/login.php">Return to Login</a>
        <?php endif; ?>
    </section>
</main>
<footer class="reset-footer">
    &copy; <?= date('Y') ?> <?= reset_page_escape(COMPANY_NAME) ?>
</footer>
</body>
</html>
