<?php
declare(strict_types=1);

// Load the staff dashboard controller and render the dashboard page.
require_once __DIR__ . '/../controllers/staff/DashboardController.php';

handle_staff_dashboard_page($conn, '/Scheduling_System');