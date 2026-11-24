<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$baseUrl = '/Scheduling_System';

function save_google_token_file(string $tokenFile, string $secureDirectory, array $token): void
{
    if (!is_dir($secureDirectory) || !is_writable($secureDirectory)) {
        throw new RuntimeException('The secure OAuth directory is not writable.');
    }

    $temporaryFile = tempnam($secureDirectory, 'gmail-token-');

    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create a temporary OAuth token file.');
    }

    try {
        @chmod($temporaryFile, 0600);
        $json = json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || file_put_contents($temporaryFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write refreshed OAuth credentials.');
        }

        @chmod($temporaryFile, 0600);

        if (!rename($temporaryFile, $tokenFile)) {
            throw new RuntimeException('Unable to replace the OAuth token file.');
        }

        @chmod($tokenFile, 0600);
    } finally {
        if (is_file($temporaryFile)) {
            @unlink($temporaryFile);
        }
    }
}

/** Send a plain-text password-reset email using the configured Gmail OAuth account. */
function send_password_reset_email(string $recipient, string $recipientName, string $resetUrl): void
{
    $oauth = require __DIR__ . '/../config/google_oauth.php';
    $client = $oauth['client'] ?? null;
    $tokenFile = $oauth['token_file'] ?? null;
    $secureDirectory = $oauth['secure_directory'] ?? null;

    if (
        !$client instanceof \Google\Client
        || !is_string($tokenFile)
        || !is_string($secureDirectory)
        || !is_file($tokenFile)
        || !is_readable($tokenFile)
    ) {
        throw new RuntimeException('Gmail OAuth is not configured or connected.');
    }

    $tokenContents = file_get_contents($tokenFile);
    $tokenData = $tokenContents !== false ? json_decode($tokenContents, true) : null;

    if (!is_array($tokenData) || empty($tokenData['refresh_token'])) {
        throw new RuntimeException('The Gmail OAuth refresh token is missing.');
    }

    $client->setAccessToken($tokenData);

    if ($client->isAccessTokenExpired()) {
        $refreshToken = (string) $tokenData['refresh_token'];
        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (!is_array($newToken) || isset($newToken['error'])) {
            throw new RuntimeException('Gmail access token refresh failed.');
        }

        if (empty($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }

        $tokenData = array_merge($tokenData, $newToken);
        $client->setAccessToken($tokenData);
        save_google_token_file($tokenFile, $secureDirectory, $tokenData);
    }

    $safeName = trim(preg_replace('/[\r\n]+/', ' ', $recipientName) ?? 'Administrator');
    if ($safeName === '') {
        $safeName = 'Administrator';
    }

    $subject = 'Mang Lechon Restaurant - Password Reset';
    $body = "Hello {$safeName},\r\n\r\n"
        . "We received a request to reset the password for your Mang Lechon Restaurant administrator account.\r\n\r\n"
        . "Use the one-time link below to choose a new password:\r\n"
        . $resetUrl . "\r\n\r\n"
        . "This link expires in 20 minutes and can only be used once. If you did not request this reset, you can ignore this email.\r\n\r\n"
        . "Mang Lechon Restaurant Workforce Management System";

    $rawMessage = "To: {$recipient}\r\n"
        . "Subject: {$subject}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $body;

    $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');
    $gmailMessage = new \Google\Service\Gmail\Message();
    $gmailMessage->setRaw($encodedMessage);

    $gmail = new \Google\Service\Gmail($client);
    $gmail->users_messages->send('me', $gmailMessage);
}

if (!is_post_request()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verify_csrf_token()) {
    set_flash_message('danger', 'Your session expired. Please try again.');
    redirect($baseUrl . '/login.php');
}

$email = strtolower(trim(post_string('email')));

// The same response is used for all submissions to avoid revealing whether
// an administrator account or verified email exists.
$genericMessage = 'If an active administrator account with a verified email exists, a password-reset link will be sent.';

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $adminId = 0;
    $registeredEmail = '';
    $adminName = '';
    $plainToken = '';
    $tokenHash = '';

    try {
        // Lock the matching admin row so simultaneous requests cannot both
        // pass the one-minute cooldown and create separate active links.
        $stmt = $conn->prepare(
            "SELECT id, full_name, email
             FROM users
             WHERE email = ?
               AND role = 'admin'
               AND account_status = 'active'
             LIMIT 1
             FOR UPDATE"
        );

        $conn->begin_transaction();
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($account !== null) {
            $adminId = (int) $account['id'];
            $registeredEmail = (string) $account['email'];
            $adminName = (string) $account['full_name'];

            $stmt = $conn->prepare(
                'SELECT id
                 FROM admin_email_verifications
                 WHERE admin_user_id = ?
                   AND verified_email = ?
                   AND verified_at IS NOT NULL
                 LIMIT 1'
            );
            $stmt->bind_param('is', $adminId, $registeredEmail);
            $stmt->execute();
            $verified = $stmt->get_result()->fetch_assoc() !== null;
            $stmt->close();

            if ($verified) {
                $stmt = $conn->prepare(
                    'SELECT id
                     FROM password_reset_tokens
                     WHERE user_id = ?
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                     LIMIT 1'
                );
                $stmt->bind_param('i', $adminId);
                $stmt->execute();
                $recentRequest = $stmt->get_result()->fetch_assoc() !== null;
                $stmt->close();

                if (!$recentRequest) {
                    $plainToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $plainToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + 20 * 60);

                    // Revoke older outstanding links before inserting the new one.
                    $stmt = $conn->prepare(
                        'UPDATE password_reset_tokens
                         SET used_at = NOW()
                         WHERE user_id = ? AND used_at IS NULL'
                    );
                    $stmt->bind_param('i', $adminId);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare(
                        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->bind_param('iss', $adminId, $tokenHash, $expiresAt);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // Preserve the original error for the server log.
        }

        error_log('Admin password-reset request preparation failed: ' . $e->getMessage());
        $plainToken = '';
        $tokenHash = '';
    }

    if ($plainToken !== '' && $tokenHash !== '') {
        // Use a trusted local URL. Never build a reset URL from the Host header.
        $resetUrl = 'http://localhost' . $baseUrl
            . '/reset_admin_password.php?token=' . rawurlencode($plainToken);

        try {
            send_password_reset_email($registeredEmail, $adminName, $resetUrl);
        } catch (Throwable $mailError) {
            // If delivery fails, immediately invalidate the token.
            try {
                $stmt = $conn->prepare(
                    'UPDATE password_reset_tokens
                     SET used_at = NOW()
                     WHERE token_hash = ? AND used_at IS NULL'
                );
                $stmt->bind_param('s', $tokenHash);
                $stmt->execute();
                $stmt->close();
            } catch (Throwable $databaseError) {
                error_log('Unable to invalidate undelivered admin reset token: ' . $databaseError->getMessage());
            }

            error_log('Admin password-reset email delivery failed: ' . $mailError->getMessage());
        }
    }
}

set_flash_message('success', $genericMessage);
redirect($baseUrl . '/login.php');
