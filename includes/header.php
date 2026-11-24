<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) && is_string($pageTitle)
    ? $pageTitle
    : APP_NAME;

$user = function_exists('current_user')
    ? current_user()
    : null;

$flashMessage = get_flash_message();

$baseUrl = '/Scheduling_System';
$logoPath = $baseUrl . '/' . trim(COMPANY_LOGO, '/');
$homeUrl = $user !== null
    ? (($user['role'] ?? '') === 'admin'
        ? $baseUrl . '/admin/dashboard.php'
        : $baseUrl . '/staff/dashboard.php')
    : $baseUrl . '/index.php';
$loginUrl = $baseUrl . '/login.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="Restaurant Workforce Attendance and Task Scheduling System">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($baseUrl) ?>/assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e($homeUrl) ?>">
                <?php if (is_file(__DIR__ . '/../' . COMPANY_LOGO)): ?>
                    <img src="<?= e($logoPath) ?>" alt="<?= e(COMPANY_NAME) ?> logo" width="36" height="36" class="rounded">
                <?php endif; ?>
                <span><?= e(COMPANY_NAME) ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation"
                aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <?php if ($user !== null): ?>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e($baseUrl) ?>/admin/dashboard.php">Admin Dashboard</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <?= e($user['full_name'] ?? 'Administrator') ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e($baseUrl) ?>/admin/settings.php">Settings</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item text-danger" href="<?= e($baseUrl) ?>/logout.php">Logout</a>
                                    </li>
                                </ul>
                            </li>
                        <?php elseif (($user['role'] ?? '') === 'staff'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e($baseUrl) ?>/staff/dashboard.php">My Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-outline-light btn-sm" href="<?= e($baseUrl) ?>/logout.php">Log Out</a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= e($loginUrl) ?>">Log In</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <?php if ($flashMessage !== null): ?>
            <?php
            $alertTypes = [
                'success' => 'success',
                'error' => 'danger',
                'warning' => 'warning',
                'info' => 'info',
            ];
            $alertClass = $alertTypes[$flashMessage['type']] ?? 'info';
            ?>
            <div class="alert alert-<?= e($alertClass) ?> alert-dismissible fade show" role="alert">
                <?= e($flashMessage['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>