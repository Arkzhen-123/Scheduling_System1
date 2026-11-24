<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/staff_header.php';
?>
<!-- Staff account details -->
<section class="content-card">
    <div class="card-heading">
        <h2>My Profile</h2>
        <span class="status-pill">Active Account</span>
    </div>

    <div class="profile-top">
        <div class="profile-avatar">
            <?php if ($profilePictureUrl !== ''): ?>
                <img src="<?= e($profilePictureUrl) ?>" alt="Profile picture">
            <?php else: ?>
                <?= e($initials) ?>
            <?php endif; ?>
        </div>

        <div>
            <div class="profile-name"><?= e($staffName) ?></div>
            <div class="profile-position"><?= e($position ?: 'Staff Member') ?></div>
            <div class="small muted mt-1">Employee ID: <?= e($employeeId ?: 'Not assigned') ?></div>
        </div>
    </div>

    <div class="profile-detail">
        <div class="label">Full Name</div>
        <div class="value"><?= e($staffName) ?></div>
    </div>

    <div class="profile-detail">
        <div class="label">Employee ID</div>
        <div class="value"><?= e($employeeId ?: 'Not assigned') ?></div>
    </div>

    <div class="profile-detail">
        <div class="label">Email Address</div>
        <div class="value"><?= e($staffEmail) ?></div>
    </div>

    <div class="profile-detail">
        <div class="label">Position</div>
        <div class="value"><?= e($position ?: 'Not assigned') ?></div>
    </div>

    <div class="profile-detail">
        <div class="label">Account Status</div>
        <div class="value"><span class="status-pill">Active</span></div>
    </div>
</section>

<div class="content-grid">
    <!-- Change password form -->
    <div>
        <section class="content-card">
            <div class="card-heading">
                <h2>Change Password</h2>
            </div>

            <div class="profile-upload">
                <form method="post" action="<?= e($baseUrl) ?>/staff/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-bold">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-bold">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="12">
                        <div class="form-text">Must be at least 12 characters long.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label fw-bold">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="12">
                    </div>

                    <button class="btn btn-green" type="submit">Update Password</button>
                </form>
            </div>
        </section>
    </div>

    <!-- Profile picture upload form -->
    <div>
        <section class="content-card">
            <div class="card-heading">
                <h2>Profile Picture</h2>
            </div>

            <div class="profile-upload">
                <form method="post" action="<?= e($baseUrl) ?>/staff/profile.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="upload_profile_picture" value="1">

                    <label for="profilePictureInput" class="form-label fw-bold">Choose a new profile picture</label>
                    <input
                        type="file"
                        class="form-control"
                        id="profilePictureInput"
                        name="profile_picture"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        required
                    >
                    <div class="form-text mb-3">
                        Allowed formats: JPG, PNG, and WEBP. Maximum file size: 2 MB.
                    </div>

                    <button class="btn btn-green" type="submit">Upload Profile Picture</button>
                </form>
            </div>
        </section>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/staff_footer.php';