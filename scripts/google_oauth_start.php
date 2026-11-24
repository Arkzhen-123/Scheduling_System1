<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

try {
    $oauth = require __DIR__ . '/../config/google_oauth.php';

    /** @var \Google\Client $googleClient */
    $googleClient = $oauth['client'];

    // Generate a one-time state value to protect against CSRF.
    $state = bin2hex(random_bytes(32));

    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_started_at'] = time();

    $googleClient->setState($state);

    header('Location: ' . $googleClient->createAuthUrl());
    exit;

} catch (Throwable $e) {
    error_log('Google OAuth start failed: ' . $e->getMessage());

    set_flash_message(
        'danger',
        'Google authorization could not be started. Check the server configuration.'
    );

    redirect('/Scheduling_System/admin/settings.php');
}