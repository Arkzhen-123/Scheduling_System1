<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/admin_header.php';
?>
<!-- Uncovered / On-Leave Shift Takeover Notice -->
<?php if (!empty($onLeaveOrUncoveredShifts)): ?>
    <div class="takeover-banner">
        <div>
            <strong><?= count($onLeaveOrUncoveredShifts) ?> shift(s) need coverage</strong>
            <div class="small">
                An employee is marked On Leave or Absent for an upcoming/active shift. Reassign coverage so operations stay staffed.
            </div>
        </div>
        <button
            type="button"
            class="btn btn-warning btn-sm fw-bold"
            data-bs-toggle="modal"
            data-bs-target="#shiftTakeoverModal"
            data-schedule-id="<?= (int) $onLeaveOrUncoveredShifts[0]['id'] ?>"
        >
            Assign Shift Takeover
        </button>
    </div>
<?php endif; ?>

<!-- Main Staff Directory Card -->
<section class="content-card">
    <div class="staff-toolbar">
        <!-- Instant Search & Filters -->
        <form method="get" action="<?= e($baseUrl) ?>/admin/staff.php" class="staff-filter-group auto-filter-form">
            <input
                type="search"
                name="search"
                value="<?= e($search) ?>"
                class="form-control form-control-sm"
                style="width: 220px;"
                placeholder="Search name, ID, email..."
            >

            <select name="status" class="form-select form-select-sm" style="width: 155px;" aria-label="Filter by status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present Today</option>
                <option value="on_leave" <?= $statusFilter === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <select name="category" class="form-select form-select-sm" style="width: 180px;" aria-label="Filter by shift category">
                <option value="0">All Shift Categories</option>
                <?php foreach ($jobCategories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $categoryFilter === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e((string) $cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($search !== '' || $statusFilter !== 'all' || $categoryFilter > 0): ?>
                <a href="<?= e($baseUrl) ?>/admin/staff.php" class="btn btn-light btn-sm">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Primary actions -->
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-green btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#shiftTakeoverModal"
                <?= empty($takeoverShifts) ? 'disabled' : '' ?>
            >
                Shift Takeover
            </button>

            <button
                type="button"
                class="btn btn-green btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#createStaffModal"
            >
                + Add Staff
            </button>
        </div>
    </div>

    <?php if (!empty($staffList)): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Allowed Shift Categories</th>
                        <th>Today's Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $staff): ?>
                        <?php
                        $staffId = (int) $staff['id'];
                        $displayStatus = (string) ($staff['display_status'] ?? 'active');
                        [$statusLabel, $statusClass] = match ($displayStatus) {
                            'present' => ['Present', 'status-present'],
                            'late' => ['Late', ''],
                            'on_leave' => ['On Leave', 'status-leave'],
                            'absent' => ['Absent', 'status-absent'],
                            'scheduled' => ['Scheduled', ''],
                            'inactive' => ['Inactive', 'status-inactive'],
                            default => ['Active', ''],
                        };

                        $permittedIdsCsv = implode(',', (array) ($staff['permitted_category_ids'] ?? []));
                        $primaryCatId = (int) ($staff['primary_category_id'] ?? 0);
                        $todaySchedId = (int) ($staff['today_schedule_id'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $staff['full_name']) ?></strong>
                                <div class="small muted mt-1">
                                    <?= e((string) $staff['employee_id']) ?>
                                    &middot; <?= e((string) $staff['email']) ?>
                                    <?php if (!empty($staff['position'])): ?>
                                        &middot; <span><?= e((string) $staff['position']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($staff['permitted_categories'])): ?>
                                    <div class="perm-pill-list">
                                        <?php foreach ($staff['permitted_categories'] as $cat): ?>
                                            <?php $isPrimary = ((int) $cat['id']) === $primaryCatId; ?>
                                            <span class="perm-pill <?= $isPrimary ? 'primary' : '' ?>" title="<?= $isPrimary ? 'Primary role' : 'Permitted shift category' ?>">
                                                <?= e((string) $cat['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="small muted">No shift categories assigned</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status-pill <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                <?php if ($todaySchedId > 0): ?>
                                    <div class="small muted mt-1">
                                        <?= e(admin_format_time((string) $staff['today_start_time'])) ?>
                                        &ndash;
                                        <?= e(admin_format_time((string) $staff['today_end_time'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <div class="dropdown d-inline-block">
                                    <button
                                        type="button"
                                        class="row-action-toggle"
                                        data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}'
                                        aria-expanded="false"
                                        aria-label="Actions for <?= e((string) $staff['full_name']) ?>"
                                        title="Actions"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                            <circle cx="8" cy="3" r="1.4"/>
                                            <circle cx="8" cy="8" r="1.4"/>
                                            <circle cx="8" cy="13" r="1.4"/>
                                        </svg>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end row-action-menu">
                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item"
                                                data-bs-toggle="modal"
                                                data-bs-target="#staffPermissionsModal"
                                                data-staff-id="<?= $staffId ?>"
                                                data-staff-name="<?= e((string) $staff['full_name']) ?>"
                                                data-position="<?= e((string) ($staff['position'] ?? '')) ?>"
                                                data-primary-category="<?= $primaryCatId ?>"
                                                data-permitted-categories="<?= e($permittedIdsCsv) ?>"
                                            >
                                                Shift Roles
                                            </button>
                                        </li>

                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item"
                                                data-bs-toggle="modal"
                                                data-bs-target="#staffStatusModal"
                                                data-staff-id="<?= $staffId ?>"
                                                data-staff-name="<?= e((string) $staff['full_name']) ?>"
                                                data-default-action="<?= $todaySchedId > 0 ? 'on_leave' : 'active' ?>"
                                            >
                                                Change Status
                                            </button>
                                        </li>

                                        <?php if ($todaySchedId > 0 && empty($staff['today_check_in'])): ?>
                                            <li>
                                                <button
                                                    type="button"
                                                    class="dropdown-item action-warn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#shiftTakeoverModal"
                                                    data-schedule-id="<?= $todaySchedId ?>"
                                                >
                                                    Shift Takeover
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item"
                                                data-bs-toggle="modal"
                                                data-bs-target="#resetStaffPasswordModal"
                                                data-staff-id="<?= $staffId ?>"
                                                data-staff-name="<?= e((string) $staff['full_name']) ?>"
                                            >
                                                Reset Temp Password
                                            </button>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item action-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteStaffModal"
                                                data-staff-id="<?= $staffId ?>"
                                                data-staff-name="<?= e((string) $staff['full_name']) ?>"
                                                data-employee-id="<?= e((string) $staff['employee_id']) ?>"
                                            >
                                                Delete Employee
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php else: ?>
        <div class="empty-state">No staff accounts matched your current filter.</div>
    <?php endif; ?>
</section>

<!-- 1. Compact Create Staff Account Modal -->
<div class="modal fade" id="createStaffModal" tabindex="-1" aria-labelledby="createStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="createStaffModalLabel">Create Staff Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="create_staff">

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Full Name *</label>
                        <input type="text" name="full_name" class="form-control form-control-sm" required placeholder="e.g., Juan Dela Cruz">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-sm" required placeholder="name@example.com">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Job Title / Position</label>
                        <input type="text" name="position" class="form-control form-control-sm" placeholder="e.g., Cook, Cashier">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Primary Shift Category</label>
                        <select name="primary_category_id" class="form-select form-select-sm">
                            <option value="0">Select primary role...</option>
                            <?php foreach ($jobCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e((string) $cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Permitted Shift Categories</label>
                    <div class="category-check-grid">
                        <?php foreach ($jobCategories as $cat): ?>
                            <label class="category-check-card">
                                <input type="checkbox" name="permitted_categories[]" value="<?= (int) $cat['id'] ?>">
                                <span><?= e((string) $cat['category_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Temporary Password *</label>
                    <div class="input-group input-group-sm">
                        <input
                            type="text"
                            id="createTempPasswordInput"
                            name="temp_password"
                            class="form-control"
                            value="<?= e($suggestedTempPassword) ?>"
                            minlength="8"
                            required
                        >
                        <button type="button" class="btn btn-outline-secondary" data-generate-password="createTempPasswordInput">
                            Generate
                        </button>
                    </div>
                    <div class="form-text small">Staff will be required to change this password on first login.</div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Shift Permissions Modal (Who can take which shifts) -->
<div class="modal fade" id="staffPermissionsModal" tabindex="-1" aria-labelledby="staffPermissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="staffPermissionsModalLabel">
                    Shift Permissions &mdash; <span id="permStaffName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="staff_id" id="permStaffId" value="">

                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Position / Title</label>
                        <input type="text" name="position" id="permPositionInput" class="form-control form-control-sm">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Primary Shift Category</label>
                        <select name="primary_category_id" id="permPrimaryCategory" class="form-select form-select-sm">
                            <option value="0">None</option>
                            <?php foreach ($jobCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e((string) $cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="form-label small fw-bold mb-1">Allowed Shift Job Categories</label>
                <div class="category-check-grid">
                    <?php foreach ($jobCategories as $cat): ?>
                        <label class="category-check-card">
                            <input type="checkbox" name="permitted_categories[]" value="<?= (int) $cat['id'] ?>">
                            <span><?= e((string) $cat['category_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-text small mt-2">
                    Select every department/category this employee is authorized to take shifts or cover takeovers for.
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Status & Leave Modal (Present, Absent, On Leave, Active/Inactive) -->
<div class="modal fade" id="staffStatusModal" tabindex="-1" aria-labelledby="staffStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="staffStatusModalLabel">
                    Update Status &mdash; <span id="statusStaffName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="staff_id" id="statusStaffId" value="">

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Status Action</label>
                    <select name="status_action" id="statusActionSelect" class="form-select form-select-sm" required>
                        <option value="on_leave">On Leave (Flags scheduled shift(s) for takeover)</option>
                        <option value="present">Present (Mark shift as checked in / present)</option>
                        <option value="absent">Absent (Mark shift as absent)</option>
                        <option value="active">Account Active</option>
                        <option value="inactive">Account Inactive (Suspend login)</option>
                    </select>
                </div>

                <div class="row g-2 mb-2" id="statusDateRow">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Shift / Start Date</label>
                        <input type="date" name="target_date" class="form-control form-control-sm" value="<?= e($today) ?>">
                    </div>
                    <div class="col-sm-6" id="leaveEndCol">
                        <label class="form-label small fw-bold mb-1">Leave End Date</label>
                        <input type="date" name="leave_end_date" class="form-control form-control-sm" value="<?= e($today) ?>">
                    </div>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Note / Reason (Optional)</label>
                    <input type="text" name="status_reason" class="form-control form-control-sm" placeholder="e.g., Sick leave, Emergency leave">
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Apply Status</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Shift Takeover Modal -->
<div class="modal fade" id="shiftTakeoverModal" tabindex="-1" aria-labelledby="shiftTakeoverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="shiftTakeoverModalLabel">Shift Takeover / Coverage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="takeover_shift">

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Select Shift to Cover *</label>
                    <select name="schedule_id" id="takeoverScheduleSelect" class="form-select form-select-sm" required>
                        <?php foreach ($takeoverShifts as $shift): ?>
                            <?php
                            $prefix = !empty($shift['is_on_leave']) ? '[ON LEAVE] ' : '';
                            $shiftLabel = sprintf(
                                '%s%s (%s %s–%s) — %s',
                                $prefix,
                                (string) $shift['staff_name'],
                                (string) $shift['schedule_date'],
                                admin_format_time((string) $shift['start_time']),
                                admin_format_time((string) $shift['end_time']),
                                (string) $shift['category_name']
                            );
                            ?>
                            <option
                                value="<?= (int) $shift['id'] ?>"
                                data-staff-id="<?= (int) $shift['staff_id'] ?>"
                                data-category-id="<?= (int) $shift['assigned_category_id'] ?>"
                                data-category-name="<?= e((string) $shift['category_name']) ?>"
                            >
                                <?= e($shiftLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small text-success fw-semibold" id="takeoverCategoryHint"></div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Replacement Employee *</label>
                    <select name="new_staff_id" id="takeoverNewStaffSelect" class="form-select form-select-sm" required>
                        <option value="">Choose replacement staff...</option>
                        <?php foreach ($allActiveStaff as $candidate): ?>
                            <?php if (($candidate['account_status'] ?? '') !== 'active') continue; ?>
                            <?php
                            $baseLabel = $candidate['full_name'] . ' (' . $candidate['employee_id'] . ')';
                            $permCsv = implode(',', (array) ($candidate['permitted_category_ids'] ?? []));
                            ?>
                            <option
                                value="<?= (int) $candidate['id'] ?>"
                                data-permitted="<?= e($permCsv) ?>"
                                data-base-label="<?= e($baseLabel) ?>"
                            >
                                <?= e($baseLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Takeover Reason</label>
                    <input type="text" name="takeover_reason" class="form-control form-control-sm" value="Covering employee leave">
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Confirm Takeover</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Reset Temporary Password Modal -->
<div class="modal fade" id="resetStaffPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="staff_id" id="resetPwStaffId" value="">

                <p class="small mb-2">Set new temporary password for <strong id="resetPwStaffName"></strong>:</p>
                <div class="input-group input-group-sm">
                    <input
                        type="text"
                        id="resetTempPwInput"
                        name="new_temp_password"
                        class="form-control"
                        value="<?= e($suggestedTempPassword) ?>"
                        minlength="8"
                        required
                    >
                    <button type="button" class="btn btn-outline-secondary" data-generate-password="resetTempPwInput">New</button>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Delete Employee Modal (Retired / Fired) -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/staff.php" class="modal-content">
            <div class="modal-header danger-modal-header">
                <h5 class="modal-title">Remove Employee Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_staff">
                <input type="hidden" name="staff_id" id="deleteStaffId" value="">

                <p class="small mb-2">
                    You are about to permanently remove <strong id="deleteStaffName"></strong> (<span id="deleteStaffEmpId"></span>) and their schedule/attendance records. This cannot be undone.
                </p>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Reason for Removal *</label>
                    <select name="separation_reason" class="form-select form-select-sm" required>
                        <option value="Retired">Retired</option>
                        <option value="Fired / Terminated">Fired / Terminated</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Remarks (Optional)</label>
                    <input type="text" name="separation_notes" class="form-control form-control-sm" placeholder="Optional separation note for audit log">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm">Delete Employee</button>
            </div>
        </form>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/admin_footer.php';