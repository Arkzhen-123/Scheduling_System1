<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Resolve a stored profile picture path to a public URL.
function admin_resolve_picture_url(string $path, string $baseUrl): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

// Fetch the active administrator record by ID.
function admin_find_active_by_id(mysqli $conn, int $adminId): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, employee_id, full_name, email, role, position, profile_picture, account_status
         FROM users
         WHERE id = ? AND role = 'admin' AND account_status = 'active'
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

// Mark a single password assistance request (and its notification) as read when clicked.
function admin_mark_assistance_read(mysqli $conn, int $requestId): void
{
    if ($requestId <= 0) {
        return;
    }

    $stmt = $conn->prepare('UPDATE password_assistance_requests SET is_read = 1 WHERE id = ? AND is_read = 0');
    if ($stmt) {
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();
    }

    $notifStmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE request_id = ? AND is_read = 0');
    if ($notifStmt) {
        $notifStmt->bind_param('i', $requestId);
        $notifStmt->execute();
        $notifStmt->close();
    }
}

// Mark all unread password assistance requests as read.
function admin_mark_all_assistance_read(mysqli $conn, int $adminId): void
{
    $conn->query('UPDATE password_assistance_requests SET is_read = 1 WHERE is_read = 0');

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0');
    if ($stmt) {
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch unread password assistance requests and total count using r.user_id.
function admin_fetch_unread_assistance(mysqli $conn, int $limit = 8): array
{
    $safeLimit = max(1, min(25, $limit));
    $unreadCount = 0;
    $items = [];

    $countRes = $conn->query('SELECT COUNT(*) AS total FROM password_assistance_requests WHERE is_read = 0');
    if ($countRes && ($row = $countRes->fetch_assoc())) {
        $unreadCount = (int) ($row['total'] ?? 0);
    }

    $listRes = $conn->query(
        "SELECT
            r.id,
            r.user_id,
            r.submitted_email,
            r.submitted_employee_id,
            r.message,
            r.is_read,
            r.created_at,
            u.full_name,
            u.employee_id
         FROM password_assistance_requests r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.is_read = 0
         ORDER BY r.created_at DESC
         LIMIT {$safeLimit}"
    );

    if ($listRes) {
        while ($item = $listRes->fetch_assoc()) {
            $items[] = $item;
        }
    }

    return [
        'count' => $unreadCount,
        'items' => $items,
    ];
}

// Require an authenticated active admin session and return shared layout variables.
function admin_require_portal_context(mysqli $conn, string $baseUrl = '/Scheduling_System'): array
{
    if (!is_logged_in()) {
        redirect($baseUrl . '/login.php');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $sessionUser = current_user();

    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        logout_user();
    }

    if (($sessionUser['role'] ?? '') !== 'admin') {
        redirect($baseUrl . '/staff/dashboard.php');
    }

    $adminId = (int) $sessionUser['id'];
    $admin = admin_find_active_by_id($conn, $adminId);

    if ($admin === null) {
        logout_user();
    }

    // Auto-mark notification as read when clicked and redirected to Staff Management.
    if (isset($_GET['read_request'])) {
        admin_mark_assistance_read($conn, (int) $_GET['read_request']);
    }

    // Handle "Mark all read" from the topbar mail dropdown.
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && (string) ($_POST['action'] ?? '') === 'mark_all_assistance_read'
    ) {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (verify_csrf_token($submittedToken)) {
            admin_mark_all_assistance_read($conn, $adminId);
        }

        redirect((string) ($_SERVER['REQUEST_URI'] ?? ($baseUrl . '/admin/dashboard.php')));
    }

    $adminName = (string) $admin['full_name'];
    $initialsParts = preg_split('/\s+/', trim($adminName)) ?: [];
    $initials = '';

    foreach ($initialsParts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    $initials = substr($initials ?: 'AD', 0, 2);
    $assistance = admin_fetch_unread_assistance($conn, 8);
    $csrfToken = csrf_token();

    return [
        'baseUrl' => $baseUrl,
        'today' => date('Y-m-d'),
        'adminId' => $adminId,
        'admin' => $admin,
        'adminName' => $adminName,
        'adminEmail' => (string) $admin['email'],
        'employeeId' => (string) ($admin['employee_id'] ?? ''),
        'position' => (string) ($admin['position'] ?? 'Administrator'),
        'initials' => $initials,
        'profilePictureUrl' => admin_resolve_picture_url((string) ($admin['profile_picture'] ?? ''), $baseUrl),
        'unreadMailCount' => $assistance['count'],
        'recentMailRequests' => $assistance['items'],
        'unreadAssistanceCount' => $assistance['count'],
        'assistanceRequests' => $assistance['items'],
        'csrfToken' => $csrfToken,
        'flash' => get_flash_message(),
    ];
}