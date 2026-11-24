<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/UserModel.php';

// Return the dashboard path for the given user role.
function resolve_role_dashboard_url(string $role, string $baseUrl): string
{
    return $role === 'admin'
        ? $baseUrl . '/admin/dashboard.php'
        : $baseUrl . '/staff/dashboard.php';
}

// Process login page requests and render the login view.
function handle_login_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    if (is_logged_in()) {
        $activeUser = current_user();
        $role = (string) ($activeUser['role'] ?? 'staff');
        redirect(resolve_role_dashboard_url($role, $baseUrl));
    }

    $errors = [];
    $oldEmail = '';

    if (is_post_request()) {
        if (!verify_csrf_token()) {
            set_flash_message('danger', 'Your session expired. Please refresh and try again.');
            redirect($baseUrl . '/login.php');
        }

        $formAction = post_string('form_action');

        // Handle staff password assistance request submission.
        if ($formAction === 'staff_password_help') {
            $helpEmail = strtolower(post_string('help_email'));
            $helpEmployeeId = post_string('help_employee_id');
            $helpMessage = post_string('help_message');

            if ($helpEmail === '' || !filter_var($helpEmail, FILTER_VALIDATE_EMAIL) || strlen($helpEmail) > 254) {
                set_flash_message('danger', 'Please enter a valid email address for your password assistance request.');
                redirect($baseUrl . '/login.php');
            }

            if (strlen($helpEmployeeId) > 50 || mb_strlen($helpMessage) > 2000) {
                set_flash_message('danger', 'Your request details exceeded the allowed length.');
                redirect($baseUrl . '/login.php');
            }

            try {
                user_create_password_assistance_request($conn, $helpEmail, $helpEmployeeId, $helpMessage);
                set_flash_message('success', 'Your password assistance request has been sent to the administrator.');
            } catch (Throwable $e) {
                error_log('Staff password assistance submission failed: ' . $e->getMessage());
                set_flash_message('danger', 'Unable to submit your request right now. Please try again later.');
            }

            redirect($baseUrl . '/login.php');
        }

        // Handle user sign-in submission.
        $oldEmail = strtolower(post_string('email'));
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

        if ($oldEmail === '' || !filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Please enter your password.';
        }

        if (!$errors) {
            try {
                $user = user_find_by_email($conn, $oldEmail);

                if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
                    $errors[] = 'Invalid email address or password.';
                } elseif (($user['account_status'] ?? '') !== 'active') {
                    $errors[] = 'Your account is currently inactive. Please contact the administrator.';
                } else {
                    regenerate_user_session($user);
                    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                    user_record_login($conn, (int) $user['id'], $ipAddress);

                    redirect(resolve_role_dashboard_url((string) $user['role'], $baseUrl));
                }
            } catch (Throwable $e) {
                error_log('Login authentication error: ' . $e->getMessage());
                $errors[] = 'Unable to sign in right now. Please try again later.';
            }
        }
    }

    // Prepare view variables.
    $pageTitle = 'Sign In';
    $companyName = COMPANY_NAME;
    $appName = APP_NAME;
    $logoUrl = $baseUrl . '/' . ltrim(COMPANY_LOGO, '/');
    $backgroundImageUrl = $baseUrl . '/' . ltrim(COMPANY_IMAGE, '/');
    $csrfToken = csrf_token();
    $flashMessage = get_flash_message();

    require __DIR__ . '/../views/auth/login.php';
}

// Record the logout event, clear the active session, and redirect to login.
function handle_logout_request(mysqli $conn, string $baseUrl = '/Scheduling_System'): never
{
    if (is_logged_in()) {
        $activeUser = current_user();
        $userId = (int) ($activeUser['id'] ?? 0);

        if ($userId > 0) {
            try {
                $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                user_record_logout($conn, $userId, $ipAddress);
            } catch (Throwable $e) {
                error_log('Logout audit log failed: ' . $e->getMessage());
            }
        }
    }

    logout_user();
}