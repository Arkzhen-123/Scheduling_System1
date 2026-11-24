<?php
declare(strict_types=1);

// Load the authentication controller and process the logout request.
require_once __DIR__ . '/controllers/AuthController.php';

handle_logout_request($conn, '/Scheduling_System');