<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($companyName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($baseUrl) ?>/assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page" style="--login-bg-image: url('<?= e($backgroundImageUrl) ?>');">
    <main class="login-shell">
        <div class="login-card">
            <!-- Company logo and title -->
            <div class="login-brand">
                <img
                    class="login-brand-logo"
                    src="<?= e($logoUrl) ?>"
                    alt="<?= e($companyName) ?> logo"
                    onerror="this.style.display='none'"
                >
                <h1><?= e($companyName) ?></h1>
                <p>Workforce Attendance and Task Scheduling System</p>
            </div>

            <!-- One-time flash alert -->
            <?php if ($flashMessage !== null): ?>
                <?php
                $alertClass = match ($flashMessage['type']) {
                    'success' => 'success',
                    'error', 'danger' => 'danger',
                    'warning' => 'warning',
                    default => 'info',
                };
                ?>
                <div class="alert alert-<?= e($alertClass) ?> small" role="alert" data-auto-dismiss="true">
                    <?= e($flashMessage['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Validation error messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Main sign-in form -->
            <form method="post" action="<?= e($baseUrl) ?>/login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_action" value="login">

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        maxlength="254"
                        autocomplete="username"
                        required
                        value="<?= e($oldEmail) ?>"
                        placeholder="name@gmail.com"
                    >
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label mb-0">Password</label>
                        <button type="button" class="forgot-trigger-link" id="open-forgot-password">
                            Forgot password?
                        </button>
                    </div>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                        >
                        <button
                            type="button"
                            class="btn password-toggle-btn"
                            data-password-toggle
                            data-target="password"
                            aria-label="Show password"
                            title="Show password"
                            aria-pressed="false"
                        >
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login-submit mt-2">Sign In</button>
            </form>
        </div>

        <div class="login-footer-note">
            &copy; <?= date('Y') ?> <?= e($companyName) ?> &bull; <?= e($appName) ?>
        </div>
    </main>

    <!-- Password assistance popup -->
    <div class="forgot-overlay" id="forgot-overlay" hidden>
        <div class="forgot-panel" role="dialog" aria-modal="true" aria-labelledby="forgotPanelTitle">
            <div class="forgot-panel-header">
                <h2 id="forgotPanelTitle">Password Assistance</h2>
                <button type="button" class="btn-close" id="close-forgot-password" aria-label="Close"></button>
            </div>
            <p class="small text-muted mb-2">Select your account role to continue:</p>

            <div class="role-choice-grid">
                <button type="button" class="role-choice-btn" data-help-type="staff">Staff Account</button>
                <button type="button" class="role-choice-btn" data-help-type="admin">Admin Account</button>
            </div>

            <!-- Staff assistance request form -->
            <form method="post" action="<?= e($baseUrl) ?>/login.php" id="staff-help-form" hidden>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_action" value="staff_password_help">

                <div class="mb-2">
                    <label for="help_email" class="form-label small fw-bold">Registered Email Address</label>
                    <input type="email" class="form-control form-control-sm" id="help_email" name="help_email" maxlength="254" required>
                </div>
                <div class="mb-2">
                    <label for="help_employee_id" class="form-label small fw-bold">Employee ID (Optional)</label>
                    <input type="text" class="form-control form-control-sm" id="help_employee_id" name="help_employee_id" maxlength="50" placeholder="e.g., ML-CASH01">
                </div>
                <div class="mb-3">
                    <label for="help_message" class="form-label small fw-bold">Note for Administrator (Optional)</label>
                    <input type="text" class="form-control form-control-sm" id="help_message" name="help_message" maxlength="500" placeholder="Briefly describe your login issue">
                </div>
                <button type="submit" class="btn btn-login-submit btn-sm">Send Request to Admin</button>
            </form>

            <!-- Admin password reset email form -->
            <form method="post" action="<?= e($baseUrl) ?>/scripts/request_admin_password_reset.php" id="admin-help-form" hidden>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="mb-3">
                    <label for="admin_reset_email" class="form-label small fw-bold">Verified Administrator Email</label>
                    <input type="email" class="form-control form-control-sm" id="admin_reset_email" name="email" maxlength="254" required>
                    <div class="form-text">A one-time password reset link will be sent if your admin email is verified.</div>
                </div>
                <button type="submit" class="btn btn-login-submit btn-sm">Send Reset Link</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e($baseUrl) ?>/assets/js/app.js"></script>
</body>
</html>