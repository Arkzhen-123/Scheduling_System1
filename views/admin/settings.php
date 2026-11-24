<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="settings-grid settings-compact">
    <!-- Left Column: Profile & Email Verification -->
    <div class="settings-column">
        <!-- 1. Administrator Profile Card -->
        <section class="settings-card">
            <div class="settings-card-header">
                <div>
                    <h2>Administrator Profile</h2>
                    <div class="settings-card-sub">Manage your display name, role title, and account photo</div>
                </div>
                <span class="warm-category-tag"><?= e($employeeId ?: 'ADMIN') ?></span>
            </div>

            <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="settings-avatar-strip">
                    <span class="settings-avatar-circle">
                        <?php if ($profilePictureUrl !== ''): ?>
                            <img src="<?= e($profilePictureUrl) ?>" alt="Admin avatar">
                        <?php else: ?>
                            <?= e($initials) ?>
                        <?php endif; ?>
                    </span>

                    <div class="flex-grow-1">
                        <label for="adminAvatarInput" class="form-label mb-1">Profile Photo (JPG, PNG, WebP &bull; Max 2MB)</label>
                        <input type="file" id="adminAvatarInput" name="profile_picture" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <?php if ($profilePictureUrl !== ''): ?>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_picture" value="1" id="removeAdminPic">
                                <label class="form-check-label small muted" for="removeAdminPic">Remove current photo</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label for="adminFullName" class="form-label">Full Name *</label>
                        <input type="text" id="adminFullName" name="full_name" class="form-control" value="<?= e($adminName) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="adminPosition" class="form-label">Position / Title</label>
                        <input type="text" id="adminPosition" name="position" class="form-control" value="<?= e($position) ?>" placeholder="Administrator">
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-green">Save Profile</button>
                </div>
            </form>
        </section>

        <!-- 2. Email Address & Verification Card -->
        <section class="settings-card">
            <div class="settings-card-header">
                <div>
                    <h2>Email Address &amp; Delivery</h2>
                    <div class="settings-card-sub">Used for administrator recovery and system notifications</div>
                </div>

                <?php if (!empty($emailState['isVerified'])): ?>
                    <span class="verified-badge-pill">&#10003; Verified</span>
                <?php endif; ?>
            </div>

            <!-- Update or verify email form -->
            <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="send_email_verification">

                <label for="adminEmailInput" class="form-label">Administrator Email</label>
                <div class="input-group input-group-sm">
                    <input
                        type="email"
                        id="adminEmailInput"
                        name="new_email"
                        class="form-control"
                        value="<?= e($emailState['pendingEmail'] !== '' ? $emailState['pendingEmail'] : $adminEmail) ?>"
                        required
                    >
                    <button type="submit" class="btn btn-outline-green">
                        <?= !empty($emailState['isVerified']) ? 'Change & Verify' : 'Send Code' ?>
                    </button>
                </div>
            </form>

            <!-- Verification prompt disappears completely once the email is verified -->
            <?php if (empty($emailState['isVerified'])): ?>
                <div class="email-verify-box">
                    <div class="fw-bold mb-1">
                        <?php if ($emailState['pendingEmail'] !== ''): ?>
                            Verification pending for <?= e($emailState['pendingEmail']) ?>
                        <?php else: ?>
                            Your administrator email address is not verified yet.
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        Enter the 6-digit code sent to your email to complete verification.
                    </div>

                    <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="confirm_email_verification">

                        <input
                            type="text"
                            name="verification_code"
                            class="form-control"
                            style="width: 135px; letter-spacing: 2px; font-weight: 700;"
                            maxlength="6"
                            pattern="\d{6}"
                            placeholder="000000"
                            required
                        >
                        <button type="submit" class="btn btn-green">Confirm Code</button>
                    </form>

                    <?php if ($emailState['pendingEmail'] !== '' && $emailState['verifiedEmail'] === strtolower($adminEmail)): ?>
                        <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="cancel_email_verification">
                            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none text-muted">
                                Cancel pending email change
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top" style="font-size: 11.5px;">
                <span class="muted">Google OAuth Mailer Token</span>
                <span class="fw-bold <?= $gmailTokenExists ? 'text-success' : 'text-muted' ?>">
                    <?= $gmailTokenExists ? 'Connected' : 'Not Connected' ?>
                </span>
            </div>
        </section>
    </div>

    <!-- Right Column: Security & Workforce Configuration -->
    <div class="settings-column">
        <!-- 3. Password & Recovery Codes Card -->
        <section class="settings-card">
            <div class="settings-card-header">
                <div>
                    <h2>Password &amp; Recovery Codes</h2>
                    <div class="settings-card-sub">Update your login credentials and backup access keys</div>
                </div>
            </div>

            <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="mb-2">
                    <label for="currentPasswordInput" class="form-label">Current Password *</label>
                    <input type="password" id="currentPasswordInput" name="current_password" class="form-control" required>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label for="newPasswordInput" class="form-label">New Password *</label>
                        <input type="password" id="newPasswordInput" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="confirmPasswordInput" class="form-label">Confirm Password *</label>
                        <input type="password" id="confirmPasswordInput" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-green">Update Password</button>
                </div>
            </form>

            <div class="mt-3 pt-2 border-top">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-bold" style="font-size: 12.5px; color: var(--green-dark);">Backup Recovery Codes</div>
                        <div class="muted" style="font-size: 11.5px;">
                            <?= (int) $activeRecoveryCodesCount ?> active single-use code(s) remaining
                        </div>
                    </div>

                    <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="regenerate_recovery_codes">
                        <button type="submit" class="btn btn-outline-green">
                            <?= $activeRecoveryCodesCount > 0 ? 'Regenerate' : 'Generate Codes' ?>
                        </button>
                    </form>
                </div>

                <?php if (!empty($generatedRecoveryCodes)): ?>
                    <div class="recovery-codes-box">
                        <div class="fw-bold" style="font-size: 11.5px; color: var(--green-dark);">
                            Copy and store these codes safely — they will not be shown again:
                        </div>
                        <div class="recovery-codes-grid">
                            <?php foreach ($generatedRecoveryCodes as $code): ?>
                                <div class="recovery-code-chip"><?= e((string) $code) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4. Workforce Rules & Job Categories Card -->
        <section class="settings-card">
            <div class="settings-card-header">
                <div>
                    <h2>Workforce Rules &amp; Job Categories</h2>
                    <div class="settings-card-sub">Configure attendance thresholds and active shift departments</div>
                </div>
            </div>

            <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php" class="mb-3 pb-3 border-bottom">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save_workforce_settings">

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label for="lateGraceInput" class="form-label">Late Grace Period (mins)</label>
                        <input
                            type="number"
                            id="lateGraceInput"
                            name="late_grace_minutes"
                            class="form-control"
                            min="0"
                            max="120"
                            value="<?= e($workforceSettings['late_grace_minutes']) ?>"
                        >
                    </div>
                    <div class="col-sm-6">
                        <label for="earlyCheckInInput" class="form-label">Early Check-In Window (mins)</label>
                        <input
                            type="number"
                            id="earlyCheckInInput"
                            name="early_check_in_minutes"
                            class="form-control"
                            min="0"
                            max="180"
                            value="<?= e($workforceSettings['early_check_in_minutes']) ?>"
                        >
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label for="defaultStartInput" class="form-label">Default Shift Start</label>
                        <input
                            type="time"
                            id="defaultStartInput"
                            name="default_shift_start"
                            class="form-control"
                            value="<?= e($workforceSettings['default_shift_start']) ?>"
                        >
                    </div>
                    <div class="col-sm-6">
                        <label for="defaultEndInput" class="form-label">Default Shift End</label>
                        <input
                            type="time"
                            id="defaultEndInput"
                            name="default_shift_end"
                            class="form-control"
                            value="<?= e($workforceSettings['default_shift_end']) ?>"
                        >
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-green">Save Rules</button>
                </div>
            </form>

            <!-- Job Categories Quick Manager -->
            <div>
                <label class="form-label">Shift Job Categories</label>
                <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="manage_category">
                    <input type="hidden" name="mode" value="add">

                    <input
                        type="text"
                        name="category_name"
                        class="form-control"
                        placeholder="New category name (e.g., Dining Service)"
                        required
                    >
                    <button type="submit" class="btn btn-outline-green text-nowrap">+ Add</button>
                </form>

                <?php if (!empty($allCategories)): ?>
                    <div class="settings-category-list">
                        <?php foreach ($allCategories as $cat): ?>
                            <?php $isActive = ((int) ($cat['is_active'] ?? 1)) === 1; ?>
                            <div class="settings-category-row <?= $isActive ? '' : 'inactive-cat' ?>">
                                <div>
                                    <strong><?= e((string) $cat['category_name']) ?></strong>
                                    <?php if (!empty($cat['description'])): ?>
                                        <span class="muted"> &middot; <?= e((string) $cat['description']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <form method="post" action="<?= e($baseUrl) ?>/admin/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="manage_category">
                                    <input type="hidden" name="mode" value="toggle">
                                    <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                    <button type="submit" class="btn btn-light btn-sm py-0 px-2" style="font-size: 11px;">
                                        <?= $isActive ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/admin_footer.php';