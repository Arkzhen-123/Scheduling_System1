<?php
declare(strict_types=1);

// Load the admin dashboard controller and render the dashboard page.
require_once __DIR__ . '/../controllers/admin/DashboardController.php';

handle_admin_dashboard_page($conn, '/Scheduling_System');