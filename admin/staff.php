<?php
declare(strict_types=1);

// Load the admin staff controller and render the Staff Management page.
require_once __DIR__ . '/../controllers/admin/StaffController.php';

handle_admin_staff_page($conn, '/Scheduling_System');