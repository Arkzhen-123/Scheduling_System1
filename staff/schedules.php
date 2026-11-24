<?php
declare(strict_types=1);

// Load the staff schedules controller and render the schedules page.
require_once __DIR__ . '/../controllers/staff/SchedulesController.php';

handle_staff_schedules_page($conn, '/Scheduling_System');