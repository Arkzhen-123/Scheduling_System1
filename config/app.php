<?php
declare(strict_types=1);


define('APP_NAME', 'Restaurant Workforce System');

define('COMPANY_NAME', 'Mang Lechon Restaurant');
define('COMPANY_LOGO', 'assets/images/company-logo.png');
define('COMPANY_IMAGE', 'assets/images/company-image.jpg');

define('APP_ENV', 'development');

define('APP_TIMEZONE', 'Asia/Manila');

date_default_timezone_set(APP_TIMEZONE);

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}