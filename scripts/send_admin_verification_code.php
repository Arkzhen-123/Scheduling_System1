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

if ($adminId < 1) {
    logout_user();
}

$codeWasStored = false;

try {
    /*
    |--------------------------------------------------------------------------
    | 1. Load the active administrator account
    |--------------------------------------------------------------------------
    */

    $role = 'admin';
    $status = 'active';

    $stmt = $conn->prepare(
        'SELECT email, full_name
         FROM users
         WHERE id = ?
           AND role = ?
           AND account_status = ?
         LIMIT 1'
    );

    $stmt->bind_param('iss', $adminId, $role, $status);
    $stmt->execute();
    $stmt->bind_result($adminEmail, $adminName);

    $adminFound = $stmt->fetch();
    $stmt->close();

    if (
        !$adminFound
        || !is_string($adminEmail)
        || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException(
            'The administrator account email is invalid.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Load Google OAuth configuration
    |--------------------------------------------------------------------------
    */

    $oauth = require __DIR__ . '/../config/google_oauth.php';

    /** @var \Google\Client $googleClient */
    $googleClient = $oauth['client'];

    $tokenFile = $oauth['token_file'];

    if (!is_file($tokenFile) || !is_readable($tokenFile)) {
        throw new RuntimeException(
            'Google Gmail has not been connected yet.'
        );
    }

    $tokenContents = file_get_contents($tokenFile);

    if ($tokenContents === false) {
        throw new RuntimeException(
            'The Google token file could not be read.'
        );
    }

    $tokenData = json_decode($tokenContents, true);

    if (
        !is_array($tokenData)
        || empty($tokenData['access_token'])
    ) {
        throw new RuntimeException(
            'The saved Google token is invalid.'
        );
    }

    $googleClient->setAccessToken($tokenData);

    /*
     * Refresh the access token when necessary.
     */

    if ($googleClient->isAccessTokenExpired()) {
        $refreshToken = $tokenData['refresh_token'] ?? '';

        if (!is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException(
                'The Google connection has expired. Please reconnect Gmail.'
            );
        }

        $newToken = $googleClient->fetchAccessTokenWithRefreshToken(
            $refreshToken
        );

        if (
            !is_array($newToken)
            || isset($newToken['error'])
        ) {
            throw new RuntimeException(
                'Google could not refresh the Gmail connection.'
            );
        }

        $tokenData = array_merge($tokenData, $newToken);

        // Preserve the refresh token if Google does not return a new one.
        if (empty($tokenData['refresh_token'])) {
            $tokenData['refresh_token'] = $refreshToken;
        }

        $googleClient->setAccessToken($tokenData);

        $savedToken = file_put_contents(
            $tokenFile,
            json_encode(
                $tokenData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            LOCK_EX
        );

        if ($savedToken === false) {
            throw new RuntimeException(
                'The refreshed Google token could not be saved.'
            );
        }

        @chmod($tokenFile, 0600);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Create or load the administrator verification record
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'INSERT INTO admin_email_verifications (admin_user_id)
         VALUES (?)
         ON DUPLICATE KEY UPDATE admin_user_id = VALUES(admin_user_id)'
    );

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();

    /*
     * Lock the record to prevent simultaneous code requests
     * from creating conflicting verification codes.
     */

    $stmt = $conn->prepare(
        'SELECT verified_email, verified_at, last_sent_at
         FROM admin_email_verifications
         WHERE admin_user_id = ?
         FOR UPDATE'
    );

    $stmt->bind_param('i', $adminId);
    $stmt->execute();

    $stmt->bind_result(
        $verifiedEmail,
        $verifiedAt,
        $lastSentAt
    );

    $recordFound = $stmt->fetch();
    $stmt->close();

    if (!$recordFound) {
        throw new RuntimeException(
            'The administrator verification record could not be loaded.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Check whether the email is already verified
    |--------------------------------------------------------------------------
    */

    if (
        $verifiedAt !== null
        && $verifiedEmail !== null
        && strcasecmp($verifiedEmail, $adminEmail) === 0
    ) {
        $conn->commit();

        set_flash_message(
            'success',
            'Your administrator email is already verified.'
        );

        redirect($settingsUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Invalidate verification if the registered email changed
    |--------------------------------------------------------------------------
    */

    if (
        $verifiedEmail !== null
        && strcasecmp($verifiedEmail, $adminEmail) !== 0
    ) {
        $stmt = $conn->prepare(
            'UPDATE admin_email_verifications
             SET verified_email = NULL,
                 verified_at = NULL,
                 pending_email = NULL,
                 code_hash = NULL,
                 expires_at = NULL,
                 attempts = 0
             WHERE admin_user_id = ?'
        );

        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Apply the 60-second resend cooldown
    |--------------------------------------------------------------------------
    */

    if ($lastSentAt !== null) {
        $lastSentTimestamp = strtotime($lastSentAt);

        if ($lastSentTimestamp !== false) {
            $elapsed = time() - $lastSentTimestamp;

            if ($elapsed < 60) {
                $remaining = 60 - $elapsed;

                $conn->commit();

                set_flash_message(
                    'warning',
                    'Please wait ' . $remaining .
                    ' seconds before requesting another code.'
                );

                redirect($settingsUrl);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Generate and securely store the verification code
    |--------------------------------------------------------------------------
    */

    $verificationCode = (string) random_int(100000, 999999);

    $codeHash = password_hash(
        $verificationCode,
        PASSWORD_DEFAULT
    );

    if (!is_string($codeHash)) {
        throw new RuntimeException(
            'The verification code could not be secured.'
        );
    }

    // The verification code expires after five minutes.
    $codeExpiry = date(
        'Y-m-d H:i:s',
        time() + 300
    );

    /*
     * pending_email = email currently waiting for verification.
     * verified_email remains NULL until the correct code is entered.
     */

    $stmt = $conn->prepare(
        'UPDATE admin_email_verifications
         SET pending_email = ?,
             code_hash = ?,
             expires_at = ?,
             attempts = 0,
             last_sent_at = NOW()
         WHERE admin_user_id = ?'
    );

    $stmt->bind_param(
        'sssi',
        $adminEmail,
        $codeHash,
        $codeExpiry,
        $adminId
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    $codeWasStored = true;

    /*
    |--------------------------------------------------------------------------
    | 8. Send the verification code using Gmail API
    |--------------------------------------------------------------------------
    */

    $subject = 'Mang Lechon Restaurant - Email Verification';

    $messageBody =
        "Hello {$adminName},\r\n\r\n" .
        "You requested to verify your administrator email address " .
        "for the Mang Lechon Restaurant Workforce Management System.\r\n\r\n" .
        "Your verification code is: {$verificationCode}\r\n\r\n" .
        "This code expires in 5 minutes. You have a limited number of attempts.\r\n\r\n" .
        "If you did not request this code, you can safely ignore this email.\r\n\r\n" .
        "Mang Lechon Restaurant Workforce Management System";

    $rawMessage =
        'To: ' . $adminEmail . "\r\n" .
        'Subject: ' . $subject . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n" .
        "\r\n" .
        $messageBody;

    $encodedMessage = rtrim(
        strtr(base64_encode($rawMessage), '+/', '-_'),
        '='
    );

    $gmailMessage = new \Google\Service\Gmail\Message();
    $gmailMessage->setRaw($encodedMessage);

    $gmailService = new \Google\Service\Gmail($googleClient);

    $gmailService->users_messages->send(
        'me',
        $gmailMessage
    );

    unset(
        $verificationCode,
        $messageBody,
        $rawMessage,
        $encodedMessage
    );

    set_flash_message(
        'success',
        'A six-digit verification code has been sent to your registered administrator email.'
    );

    redirect($settingsUrl);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | 9. Handle errors safely
    |--------------------------------------------------------------------------
    */

    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // There may be no active transaction to roll back.
    }

    /*
     * Only invalidate the code if it was actually stored.
     * This prevents unrelated Google/configuration errors from
     * unnecessarily clearing an existing verification request.
     */

    if ($codeWasStored) {
        try {
            $stmt = $conn->prepare(
                'UPDATE admin_email_verifications
                 SET pending_email = NULL,
                     code_hash = NULL,
                     expires_at = NULL,
                     attempts = 0,
                     last_sent_at = NULL
                 WHERE admin_user_id = ?'
            );

            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $stmt->close();

        } catch (Throwable $ignored) {
            // Do not expose internal database errors to the browser.
        }
    }

    error_log(
        'Admin verification email failed: ' . $e->getMessage()
    );

    set_flash_message(
        'danger',
        'The verification email could not be sent. Check the Gmail connection and server configuration, then try again.'
    );

    redirect($settingsUrl);
}