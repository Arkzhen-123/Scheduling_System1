<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(COMPANY_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($baseUrl) ?>/assets/css/portal.css" rel="stylesheet">
</head>
<body class="staff-portal">
    <!-- Top navigation bar -->
    <header class="topbar">
        <a class="brand" href="<?= e($baseUrl) ?>/staff/dashboard.php" aria-label="Mang Lechon staff dashboard">
            <img
                class="brand-logo"
                src="<?= e($baseUrl) ?>/assets/images/company-logo.png"
                alt="<?= e(COMPANY_NAME) ?> logo"
                onerror="this.style.display='none'"
            >
            <span>
                <span class="brand-name d-block"><?= e(COMPANY_NAME) ?></span>
                <span class="brand-subtitle">Workforce Management System</span>
            </span>
        </a>

        <div class="topbar-right">
            <div class="live-clock">
                <div id="liveDate"><?= e(date('l, F j, Y')) ?></div>
                <strong id="liveTime"><?= e(date('g:i:s A')) ?></strong>
            </div>

            <!-- Staff account menu -->
            <div class="topbar-account">
                <button
                    type="button"
                    class="topbar-account-trigger"
                    id="staffTopbarAccountTrigger"
                    aria-expanded="false"
                    aria-controls="staffTopbarAccountMenu"
                    title="Staff account menu"
                >
                    <span class="topbar-account-avatar">
                        <?php if ($profilePictureUrl !== ''): ?>
                            <img src="<?= e($profilePictureUrl) ?>" alt="Staff profile picture">
                        <?php else: ?>
                            <?= e($initials) ?>
                        <?php endif; ?>
                    </span>
                    <span class="topbar-account-details">
                        <span class="topbar-account-name"><?= e($staffName) ?></span>
                        <span class="topbar-account-position"><?= e($position ?: 'Staff Member') ?></span>
                    </span>
                    <span class="topbar-account-caret">⌄</span>
                </button>

                <div class="topbar-account-menu" id="staffTopbarAccountMenu" aria-label="Staff account options">
                    <a href="<?= e($baseUrl) ?>/staff/profile.php" class="<?= $page === 'profile' ? 'active' : '' ?>">
                        <span class="account-menu-icon">♙</span>
                        <span>My Profile</span>
                    </a>
                    <a href="<?= e($baseUrl) ?>/logout.php" class="logout-link">
                        <span class="account-menu-icon">↪</span>
                        <span>Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="app-layout" id="appLayout">
        <!-- Sidebar navigation -->
        <aside class="sidebar" id="staffSidebar" aria-label="Staff navigation">
            <div class="sidebar-top">
                <button
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    type="button"
                    aria-label="Collapse sidebar"
                    aria-expanded="true"
                    title="Collapse sidebar"
                >‹</button>
            </div>

            <div class="nav-label">Main Menu</div>

            <a class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>/staff/dashboard.php" title="Dashboard">
                <span class="sidebar-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a class="sidebar-link <?= $page === 'schedules' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>/staff/schedules.php" title="View Schedule">
                <span class="sidebar-icon">▤</span>
                <span>View Schedule</span>
            </a>

            <a class="sidebar-link <?= $page === 'attendance' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>/staff/attendance.php" title="Attendance">
                <span class="sidebar-icon">✓</span>
                <span>Attendance</span>
            </a>
        </aside>

        <!-- Main page content -->
        <main class="main-content">
            <div class="page-heading">
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <div class="muted small"><?= e(COMPANY_NAME) ?> &bull; Staff Workspace</div>
                </div>
            </div>

            <!-- One-time flash alert -->
            <?php if (is_array($flash) && !empty($flash['message'])): ?>
                <div class="alert alert-<?= e(($flash['type'] ?? '') === 'error' ? 'danger' : 'success') ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Temporary password reminder -->
            <?php if (!empty($staff['must_change_password']) && $page !== 'profile'): ?>
                <div class="alert alert-warning">
                    <strong>Temporary password:</strong>
                    Your account is marked as needing a password change.
                    Please go to <a href="<?= e($baseUrl) ?>/staff/profile.php">My Profile</a> to update it.
                </div>
            <?php endif; ?>