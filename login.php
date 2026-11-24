<?php
declare(strict_types=1);

// Load the authentication controller and handle the login page request.
require_once __DIR__ . '/controllers/AuthController.php';

handle_login_page($conn, '/Scheduling_System');