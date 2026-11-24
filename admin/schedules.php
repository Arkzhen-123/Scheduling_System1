<?php
declare(strict_types=1);

// Load the admin schedules controller and render the Schedules page.
require_once __DIR__ . '/../controllers/admin/SchedulesController.php';

handle_admin_schedules_page($conn, '/Scheduling_System');