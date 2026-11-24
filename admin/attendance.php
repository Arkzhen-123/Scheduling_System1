<?php
declare(strict_types=1);

// Load the admin attendance controller and render the Attendance page.
require_once __DIR__ . '/../controllers/admin/AttendanceController.php';

handle_admin_attendance_page($conn, '/Scheduling_System');