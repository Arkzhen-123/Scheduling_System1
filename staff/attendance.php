<?php
declare(strict_types=1);

// Load the staff attendance controller and render the attendance page.
require_once __DIR__ . '/../controllers/staff/AttendanceController.php';

handle_staff_attendance_page($conn, '/Scheduling_System');