<?php
declare(strict_types=1);

// Load the admin settings controller and render the Settings page.
require_once __DIR__ . '/../controllers/admin/SettingsController.php';

handle_admin_settings_page($conn, '/Scheduling_System');