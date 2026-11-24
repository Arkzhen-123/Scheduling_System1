<?php
declare(strict_types=1);

require_once __DIR__ . '/StaffPortalController.php';

// Process and render the staff profile page.
function handle_staff_profile_page(mysqli $conn, string $baseUrl = '/Scheduling_System'): void
{
    $context = staff_require_portal_context($conn, $baseUrl);
    extract($context);

    $profileUrl = $baseUrl . '/staff/profile.php';

    // Handle profile picture upload.
    if (is_post_request() && isset($_POST['upload_profile_picture'])) {
        if (!verify_csrf_token()) {
            set_flash_message('error', 'Your session token expired. Please refresh and try again.');
            redirect($profileUrl);
        }

        $newFilePath = null;

        try {
            if (
                !isset($_FILES['profile_picture'])
                || !is_array($_FILES['profile_picture'])
                || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK
            ) {
                throw new RuntimeException('Please select a valid image to upload.');
            }

            $file = $_FILES['profile_picture'];

            if (!is_uploaded_file($file['tmp_name'])) {
                throw new RuntimeException('The uploaded file is invalid.');
            }

            if ((int) $file['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException('The image must not exceed 2 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedTypes[$mimeType])) {
                throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
            }

            if (@getimagesize($file['tmp_name']) === false) {
                throw new RuntimeException('The uploaded file is not a valid image.');
            }

            $uploadDirectory = __DIR__ . '/../../assets/uploads/profiles/';

            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('The profile picture upload directory could not be created.');
            }

            $newFileName = 'staff_' . $staffId . '_' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
            $destination = $uploadDirectory . $newFileName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('The profile picture could not be saved.');
            }

            $newFilePath = 'assets/uploads/profiles/' . $newFileName;
            staff_update_profile_picture($conn, $staffId, $newFilePath);

            set_flash_message('success', 'Your profile picture has been updated.');
        } catch (Throwable $e) {
            if ($newFilePath !== null) {
                $newFileAbsolutePath = __DIR__ . '/../../' . $newFilePath;
                if (is_file($newFileAbsolutePath)) {
                    @unlink($newFileAbsolutePath);
                }
            }

            error_log('Staff profile picture upload failed: ' . $e->getMessage());
            set_flash_message(
                'error',
                $e instanceof RuntimeException ? $e->getMessage() : 'Your profile picture could not be updated.'
            );
        }

        redirect($profileUrl);
    }

    // Handle account password update.
    if (is_post_request() && isset($_POST['change_password'])) {
        if (!verify_csrf_token()) {
            set_flash_message('error', 'Your session token expired. Please refresh and try again.');
            redirect($profileUrl);
        }

        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password'])
            ? $_POST['current_password']
            : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password'])
            ? $_POST['new_password']
            : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
            ? $_POST['confirm_password']
            : '';

        if (strlen($newPassword) < 12) {
            set_flash_message('error', 'New password must be at least 12 characters long.');
        } elseif ($newPassword !== $confirmPassword) {
            set_flash_message('error', 'New passwords do not match.');
        } else {
            try {
                staff_change_password($conn, $staffId, $currentPassword, $newPassword);
                $_SESSION['user']['must_change_password'] = false;
                set_flash_message('success', 'Password successfully updated.');
            } catch (Throwable $e) {
                error_log('Staff password update failed: ' . $e->getMessage());
                set_flash_message(
                    'error',
                    $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update your password.'
                );
            }
        }

        redirect($profileUrl);
    }

    $page = 'profile';
    $pageTitle = 'My Profile';

    require __DIR__ . '/../../views/staff/profile.php';
}