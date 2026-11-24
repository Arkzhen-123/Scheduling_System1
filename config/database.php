<?php
declare(strict_types=1);


require_once __DIR__ . '/app.php';


$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'restaurant_workforce_db';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        $dbPort
    );

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(500);

    if (APP_ENV === 'development') {
        exit('Database connection failed. Check your XAMPP MySQL service and database configuration.');
    }

    exit('A system error occurred. Please try again later.');
}