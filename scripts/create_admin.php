<?php
declare(strict_types=1);



if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden. Run this script from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

function ask(string $label): string
{
    echo $label;
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}


function generateUniqueAdminId(mysqli $conn): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $random = '';

        for ($i = 0; $i < 8; $i++) {
            $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $employeeId = 'ADM-' . $random;

        $stmt = $conn->prepare(
            'SELECT id FROM users WHERE employee_id = ? LIMIT 1'
        );

        $stmt->bind_param('s', $employeeId);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;

        $stmt->close();

        if (!$exists) {
            return $employeeId;
        }
    }

    throw new RuntimeException(
        'Could not generate a unique administrator ID. Please try again.'
    );
}

echo "\n";
echo "============================================\n";
echo " Mang Lechon Restaurant\n";
echo " Initial Administrator Setup\n";
echo "============================================\n\n";

try {
    // Stop if an administrator already exists.
    $result = $conn->query(
        "SELECT id FROM users WHERE role = 'admin' LIMIT 1"
    );

    if ($result->num_rows > 0) {
        exit(
            "An administrator account already exists.\n" .
            "No account was changed.\n"
        );
    }

    $fullName = ask('Administrator full name: ');
    $email = ask('Administrator email: ');

    if ($fullName === '' || strlen($fullName) > 150) {
        exit("Invalid name. Use 1 to 150 characters.\n");
    }

    if (
        $email === '' ||
        strlen($email) > 254 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        exit("Please enter a valid email address.\n");
    }

    echo "\nPassword requirements: at least 12 characters.\n";
    echo "Note: characters typed into this terminal may be visible.\n";

    $password = ask('Create administrator password: ');
    $confirmPassword = ask('Confirm administrator password: ');

    if (strlen($password) < 12) {
        exit("Password must be at least 12 characters long.\n");
    }

    if (!hash_equals($password, $confirmPassword)) {
        exit("Passwords do not match. No account was created.\n");
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        exit("Could not securely hash the password.\n");
    }

    $conn->begin_transaction();

    // Recheck after starting the transaction.
    $result = $conn->query(
        "SELECT id FROM users WHERE role = 'admin' LIMIT 1 FOR UPDATE"
    );

    if ($result->num_rows > 0) {
        $conn->rollback();
        exit("An administrator already exists. No account was changed.\n");
    }

    $employeeId = generateUniqueAdminId($conn);

    $role = 'admin';
    $status = 'active';
    $position = 'Administrator';
    $mustChangePassword = 0;

    $stmt = $conn->prepare(
        "INSERT INTO users
            (
                employee_id,
                full_name,
                email,
                password_hash,
                role,
                position,
                account_status,
                must_change_password
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'sssssssi',
        $employeeId,
        $fullName,
        $email,
        $passwordHash,
        $role,
        $position,
        $status,
        $mustChangePassword
    );

    $stmt->execute();
    $adminId = $conn->insert_id;
    $stmt->close();

    // Record account creation in the audit log.
    $action = 'initial_admin_created';
    $entityType = 'users';
    $entityId = (string) $adminId;
    $description = 'Initial administrator account created through CLI setup.';

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (actor_user_id, action, entity_type, entity_id, description)
         VALUES (?, ?, ?, ?, ?)"
    );

    $audit->bind_param(
        'issss',
        $adminId,
        $action,
        $entityType,
        $entityId,
        $description
    );

    $audit->execute();
    $audit->close();

    $conn->commit();

    echo "\nAdministrator account created successfully!\n";
    echo "Your administrator employee ID: {$employeeId}\n";
    echo "Email: {$email}\n";
    echo "\nSign in at:\n";
    echo "http://localhost/Scheduling_System/login.php\n\n";
    echo "Save your employee ID somewhere safe.\n";
    echo "Keep your password private.\n";

} catch (Throwable $exception) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction to roll back.
    }

    error_log(
        'Initial administrator setup failed: ' . $exception->getMessage()
    );

    echo "\nSetup failed. No successful setup was confirmed.\n";
    echo "Check your database connection and account details.\n";
    echo "If an email or employee ID is already registered, use a different email or retry.\n";

    exit(1);
}
