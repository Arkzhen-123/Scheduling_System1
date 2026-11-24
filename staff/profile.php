<?php
declare(strict_types=1);

// Load the staff profile controller and render the profile page.
require_once __DIR__ . '/../controllers/staff/ProfileController.php';

handle_staff_profile_page($conn, '/Scheduling_System');