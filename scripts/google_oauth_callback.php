<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$settingsUrl = '/Scheduling_System/admin/settings.php';

try {
    // Confirm that this callback belongs to a recent authorization attempt.
    $savedState = $_SESSION['google_oauth_state'] ?? '';
    $savedTime = $_SESSION['google_oauth_started_at'] ?? 0;

    $returnedState = $_GET['state'] ?? '';

    // Make the authorization state one-time use.
    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_started_at']
    );

    if (
        !is_string($savedState)
        || $savedState === ''
        || !is_string($returnedState)
        || !hash_equals($savedState, $returnedState)
        || !is_int($savedTime)
        || (time() - $savedTime) > 600
    ) {
        throw new RuntimeException(
            'The authorization session is invalid or has expired.'
        );
    }

    if (isset($_GET['error'])) {
        throw new RuntimeException(
            'Google authorization was cancelled or denied.'
        );
    }

    $authorizationCode = $_GET['code'] ?? '';

    if (
        !is_string($authorizationCode)
        || $authorizationCode === ''
    ) {
        throw new RuntimeException(
            'Google did not return an authorization code.'
        );
    }

    $oauth = require __DIR__ . '/../config/google_oauth.php';

    /** @var \Google\Client $googleClient */
    $googleClient = $oauth['client'];

    $tokenFile = $oauth['token_file'];
    $secureDirectory = $oauth['secure_directory'];

    $token = $googleClient->fetchAccessTokenWithAuthCode(
        $authorizationCode
    );

    if (
        !is_array($token)
        || isset($token['error'])
    ) {
        throw new RuntimeException(
            'Google could not issue an access token.'
        );
    }

    // Preserve an existing refresh token if Google does not return a new one.
    if (is_file($tokenFile)) {
        $existingContents = file_get_contents($tokenFile);

        if ($existingContents !== false) {
            $existingToken = json_decode(
                $existingContents,
                true
            );

            if (
                is_array($existingToken)
                && !empty($existingToken['refresh_token'])
                && empty($token['refresh_token'])
            ) {
                $token['refresh_token'] = $existingToken['refresh_token'];
            }
        }
    }

    if (empty($token['refresh_token'])) {
        throw new RuntimeException(
            'Google did not provide a refresh token. Please authorize again.'
        );
    }

    if (
        !is_dir($secureDirectory)
        || !is_writable($secureDirectory)
    ) {
        throw new RuntimeException(
            'The secure folder does not exist or is not writable.'
        );
    }

    $json = json_encode(
        $token,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if (!is_string($json)) {
        throw new RuntimeException(
            'The Google token could not be encoded.'
        );
    }

    // Write to a temporary file first to avoid leaving a partially written token.
    $temporaryFile = tempnam($secureDirectory, 'google-token-');

    if ($temporaryFile === false) {
        throw new RuntimeException(
            'A temporary token file could not be created.'
        );
    }

    $bytesWritten = file_put_contents(
        $temporaryFile,
        $json,
        LOCK_EX
    );

    if (
        $bytesWritten === false
        || $bytesWritten !== strlen($json)
    ) {
        @unlink($temporaryFile);

        throw new RuntimeException(
            'The Google token could not be saved.'
        );
    }

    if (!@rename($temporaryFile, $tokenFile)) {
        @unlink($temporaryFile);

        throw new RuntimeException(
            'The Google token file could not be finalized.'
        );
    }

    // Best-effort file permission restriction.
    @chmod($tokenFile, 0600);

    set_flash_message(
        'success',
        'Google authorization was completed. The Gmail connection token was saved securely.'
    );

    redirect($settingsUrl);

} catch (Throwable $e) {
    error_log('Google OAuth callback failed: ' . $e->getMessage());

    set_flash_message(
        'danger',
        'Google authorization failed. Please check your OAuth configuration and try again.'
    );

    redirect($settingsUrl);
}