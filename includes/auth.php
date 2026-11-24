<?php
declare(strict_types=1);


require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Check whether a user is logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user'])
        && is_array($_SESSION['user'])
        && isset(
            $_SESSION['user']['id'],
            $_SESSION['user']['role']
        );
}

/**
 * Return the logged-in user's session data.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return $_SESSION['user'];
}

/**
 * Require a logged-in user.
 * Redirect unauthenticated visitors to the login page.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash_message(
            'warning',
            'Please log in to continue.'
        );

        redirect('/Scheduling_System/login.php');
    }

    // Reject malformed session data.
    $user = current_user();

    if (
        $user === null
        || !in_array($user['role'], ['admin', 'staff'], true)
        || !filter_var($user['id'], FILTER_VALIDATE_INT)
        || (int) $user['id'] < 1
    ) {
        logout_user();
    }
}

/**
 * Require the specified system role.
 * Example: require_role('admin');
 */
function require_role(string $requiredRole): void
{
    require_login();

    if (!in_array($requiredRole, ['admin', 'staff'], true)) {
        http_response_code(400);
        exit('Invalid access requirement.');
    }

    $user = current_user();

    if ($user === null || $user['role'] !== $requiredRole) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

/**
 * Require an administrator.
 */
function require_admin(): void
{
    require_role('admin');
}

/**
 * Require a staff account.
 */
function require_staff(): void
{
    require_role('staff');
}

/**
 * Log out the current user and destroy the session.
 */
function logout_user(): never
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();

    redirect('/Scheduling_System/login.php');
}

/**
 * Refresh the session ID after successful authentication.
 * Call this after verifying the user's password.
 */
function regenerate_user_session(array $user): void
{
    if (
        !isset($user['id'], $user['role'])
        || !in_array($user['role'], ['admin', 'staff'], true)
        || (int) $user['id'] < 1
    ) {
        throw new InvalidArgumentException(
            'Invalid user data supplied for the session.'
        );
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'employee_id' => (string) ($user['employee_id'] ?? ''),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) $user['role'],
        'must_change_password' =>
            (bool) ($user['must_change_password'] ?? false),
    ];

    // Give the session a timestamp for inactivity checks.
    $_SESSION['last_activity'] = time();
}

/**
 * Enforce a session inactivity timeout.
 * The default timeout is 30 minutes.
 */
function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $timeoutSeconds = 1800;
    $lastActivity = $_SESSION['last_activity'] ?? null;

    if (
        !is_int($lastActivity)
        || (time() - $lastActivity) > $timeoutSeconds
    ) {
        set_flash_message(
            'warning',
            'Your session expired. Please log in again.'
        );

        logout_user();
    }

    $_SESSION['last_activity'] = time();
}

// Check inactivity whenever this file is included.
enforce_session_timeout();
