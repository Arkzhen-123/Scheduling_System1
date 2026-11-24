<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Safely escape text before displaying it in HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Redirect the browser and stop execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Generate or retrieve the session's CSRF token.
 */
function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Generate a hidden CSRF field for POST forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}

/**
 * Verify a submitted CSRF token before processing a POST action.
 */
function verify_csrf_token(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || $submittedToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        return false;
    }

    return true;
}

/**
 * Check whether the current request uses POST.
 */
function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Return the current date and time.
 */
function current_datetime(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Safely retrieve a trimmed string from POST data.
 */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

/**
 * Store a one-time message for the next page.
 */
function set_flash_message(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'danger', 'warning', 'info'];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and remove a one-time message.
 */
function get_flash_message(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    if (
        !is_array($message)
        || !isset($message['type'], $message['message'])
        || !is_string($message['type'])
        || !is_string($message['message'])
    ) {
        return null;
    }

    return $message;
}

/**
 * Get the number of unread password-assistance requests.
 *
 * This is the shared notification count used by admin pages.
 * A count greater than zero means the mail notification dot
 * should be displayed.
 */
function admin_unread_mail_count(mysqli $conn): int
{
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS unread_count
             FROM password_assistance_requests
             WHERE is_read = 0"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return isset($row['unread_count'])
            ? (int) $row['unread_count']
            : 0;

    } catch (Throwable $exception) {
        error_log(
            'Unable to retrieve admin unread mail count: '
            . $exception->getMessage()
        );

        return 0;
    }
}