<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/admin_header.php';
?>
<!-- Warm Sage & Cream Summary Strip -->
<section class="schedule-summary-strip">
    <div class="warm-summary-tile">
        <div class="warm-summary-label">Shifts in View</div>
        <div class="warm-summary-number"><?= (int) $summary['total'] ?></div>
    </div>

    <div class="warm-summary-tile sage-tint">
        <div class="warm-summary-label">Scheduled / Active</div>
        <div class="warm-summary-number"><?= (int) $summary['scheduled'] ?></div>
    </div>

    <div class="warm-summary-tile">
        <div class="warm-summary-label">Completed</div>
        <div class="warm-summary-number"><?= (int) $summary['completed'] ?></div>
    </div>

    <div class="warm-summary-tile honey-tint">
        <div class="warm-summary-label">On Leave / Cancelled</div>
        <div class="warm-summary-number"><?= (int) $summary['needsAttention'] ?></div>
    </div>
</section>

<!-- Main Schedules Card -->
<section class="content-card">
    <div class="card-heading">
        <div>
            <h2>Shift Roster &amp; Task Assignments</h2>
            <div class="small muted">Manage employee shift assignments and daily tasks</div>
        </div>

        <button
            type="button"
            class="btn btn-green btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#assignScheduleModal"
        >
            + Assign Shift
        </button>
    </div>

    <!-- Instant Filter Bar (Employee, Category, and Keyword) -->
    <form method="get" action="<?= e($baseUrl) ?>/admin/schedules.php" class="schedule-filter-panel auto-filter-form">
        <div class="schedule-filter-row">
            <div class="schedule-filter-field">
                <label for="filterStaff">Employee</label>
                <select id="filterStaff" name="staff_id" class="form-select form-select-sm" style="min-width: 190px;">
                    <option value="0">All Employees</option>
                    <?php foreach ($staffDirectory as $member): ?>
                        <option value="<?= (int) $member['id'] ?>" <?= $staffFilter === (int) $member['id'] ? 'selected' : '' ?>>
                            <?= e((string) $member['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="schedule-filter-field">
                <label for="filterCategory">Category</label>
                <select id="filterCategory" name="category_id" class="form-select form-select-sm" style="min-width: 185px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($jobCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoryFilter === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= e((string) $cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="schedule-filter-field flex-grow-1" style="max-width: 280px;">
                <label for="filterSearch">Keyword</label>
                <input
                    type="search"
                    id="filterSearch"
                    name="search"
                    value="<?= e($search) ?>"
                    class="form-control form-control-sm"
                    placeholder="Search task, station, or staff..."
                >
            </div>

            <?php if ($staffFilter > 0 || $categoryFilter > 0 || $search !== ''): ?>
                <div>
                    <a href="<?= e($baseUrl) ?>/admin/schedules.php" class="btn btn-light btn-sm">Reset</a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($schedules)): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Shift Hours</th>
                        <th>Role &amp; Task Assignment</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $sched): ?>
                        <?php
                        $schedId = (int) $sched['id'];
                        $schedDate = (string) $sched['schedule_date'];
                        $isToday = $schedDate === $today;
                        $isOnLeave = !empty($sched['is_on_leave']);
                        $rawStatus = (string) ($sched['status'] ?? 'scheduled');

                        if ($rawStatus === 'cancelled') {
                            $pillClass = 'cancelled';
                            $pillLabel = 'Cancelled';
                        } elseif ($isOnLeave) {
                            $pillClass = 'leave';
                            $pillLabel = 'On Leave';
                        } elseif ($rawStatus === 'completed' || !empty($sched['check_out_time'])) {
                            $pillClass = 'completed';
                            $pillLabel = 'Completed';
                        } elseif (!empty($sched['check_in_time'])) {
                            $pillClass = 'scheduled';
                            $pillLabel = ($sched['attendance_status'] ?? '') === 'late' ? 'Checked In (Late)' : 'Checked In';
                        } else {
                            $pillClass = 'scheduled';
                            $pillLabel = 'Scheduled';
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="shift-date-box <?= $isToday ? 'today-box' : '' ?>">
                                    <span class="shift-date-main"><?= e(date('M j, Y', strtotime($schedDate))) ?></span>
                                    <span class="shift-date-sub"><?= $isToday ? 'Today' : e(date('l', strtotime($schedDate))) ?></span>
                                </div>
                            </td>

                            <td>
                                <strong><?= e((string) $sched['staff_name']) ?></strong>
                                <div class="small muted mt-1">
                                    <?= e((string) $sched['employee_id']) ?>
                                    <?php if (!empty($sched['staff_position'])): ?>
                                        &middot; <?= e((string) $sched['staff_position']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <strong>
                                    <?= e(admin_format_time((string) $sched['start_time'])) ?>
                                    &ndash;
                                    <?= e(admin_format_time((string) $sched['end_time'])) ?>
                                </strong>
                                <?php if (!empty($sched['work_location'])): ?>
                                    <div class="small muted mt-1"><?= e((string) $sched['work_location']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="warm-category-tag"><?= e((string) $sched['category_name']) ?></span>
                                <div class="mt-1"><?= e((string) $sched['task_description']) ?></div>
                                <?php if (!empty($sched['notes'])): ?>
                                    <div class="warm-note-tag"><?= e((string) $sched['notes']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="warm-status-pill <?= e($pillClass) ?>"><?= e($pillLabel) ?></span>
                            </td>

                            <td class="text-end">
                                <div class="dropdown d-inline-block">
                                    <button
                                        type="button"
                                        class="row-action-toggle"
                                        data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}'
                                        aria-expanded="false"
                                        aria-label="Shift actions for <?= e((string) $sched['staff_name']) ?>"
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
                                                data-bs-target="#editScheduleModal"
                                                data-schedule-id="<?= $schedId ?>"
                                                data-staff-id="<?= (int) $sched['staff_id'] ?>"
                                                data-category-id="<?= (int) $sched['assigned_category_id'] ?>"
                                                data-schedule-date="<?= e($schedDate) ?>"
                                                data-start-time="<?= e(substr((string) $sched['start_time'], 0, 5)) ?>"
                                                data-end-time="<?= e(substr((string) $sched['end_time'], 0, 5)) ?>"
                                                data-work-location="<?= e((string) ($sched['work_location'] ?? '')) ?>"
                                                data-task-description="<?= e((string) ($sched['task_description'] ?? '')) ?>"
                                                data-notes="<?= e((string) ($sched['notes'] ?? '')) ?>"
                                                data-status="<?= e($rawStatus) ?>"
                                            >
                                                Edit Shift
                                            </button>
                                        </li>

                                        <li>
                                            <?php if ($rawStatus !== 'cancelled'): ?>
                                                <form method="post" action="<?= e($baseUrl) ?>/admin/schedules.php">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="change_state">
                                                    <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                    <input type="hidden" name="mode" value="cancel">
                                                    <button type="submit" class="dropdown-item action-warn">
                                                        Cancel Shift
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="<?= e($baseUrl) ?>/admin/schedules.php">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="change_state">
                                                    <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                    <input type="hidden" name="mode" value="restore">
                                                    <button type="submit" class="dropdown-item">
                                                        Restore Shift
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </li>

                                        <?php if (empty($sched['check_in_time'])): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form
                                                    method="post"
                                                    action="<?= e($baseUrl) ?>/admin/schedules.php"
                                                    onsubmit="return confirm('Permanently delete this shift?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="change_state">
                                                    <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                    <input type="hidden" name="mode" value="delete">
                                                    <button type="submit" class="dropdown-item action-danger">
                                                        Delete Shift
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
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
        <div class="empty-state">
            No shifts found for the selected filter. Click <strong>+ Assign Shift</strong> to schedule staff.
        </div>
    <?php endif; ?>
</section>

<!-- 1. Assign Shift Modal (Single Day or Date Range) -->
<div class="modal fade" id="assignScheduleModal" tabindex="-1" aria-labelledby="assignScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/schedules.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="assignScheduleModalLabel">Assign Staff Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body warm-modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="assign_schedule">

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Staff Member *</label>
                        <select name="staff_id" id="assignStaffSelect" class="form-select form-select-sm" required>
                            <option value="">Choose employee...</option>
                            <?php foreach ($activeStaff as $member): ?>
                                <?php $permCsv = implode(',', (array) ($member['permitted_category_ids'] ?? [])); ?>
                                <option
                                    value="<?= (int) $member['id'] ?>"
                                    data-primary="<?= (int) ($member['primary_category_id'] ?? 0) ?>"
                                    data-permitted="<?= e($permCsv) ?>"
                                >
                                    <?= e((string) $member['full_name']) ?> (<?= e((string) $member['employee_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Job Category *</label>
                        <select name="assigned_category_id" id="assignCategorySelect" class="form-select form-select-sm" required>
                            <option value="">Choose category...</option>
                            <?php foreach ($jobCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" data-base-name="<?= e((string) $cat['category_name']) ?>">
                                    <?= e((string) $cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-text small text-success mb-2" id="assignPermissionHint"></div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Start Date *</label>
                        <input type="date" id="assignStartDate" name="start_date" class="form-control form-control-sm" value="<?= e($today) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">End Date (Same day or range) *</label>
                        <input type="date" id="assignEndDate" name="end_date" class="form-control form-control-sm" value="<?= e($today) ?>" required>
                    </div>
                </div>

                <!-- Weekday filter shown automatically when End Date > Start Date -->
                <div class="mb-2" id="weekdaySelectorRow" style="display: none;">
                    <label class="form-label small fw-bold mb-1">Days of Week in Range (leave all unchecked for every day)</label>
                    <div class="weekday-pill-group">
                        <?php
                        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                        foreach ($days as $num => $label):
                        ?>
                            <label class="weekday-pill-option">
                                <input type="checkbox" name="weekdays[]" value="<?= $num ?>">
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">Start Time *</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" value="06:00" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">End Time *</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" value="14:00" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">Work Station</label>
                        <input type="text" name="work_location" class="form-control form-control-sm" placeholder="e.g., Main Kitchen">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Task Description *</label>
                    <textarea
                        name="task_description"
                        class="form-control form-control-sm"
                        rows="2"
                        required
                        placeholder="Describe assigned shift duties..."
                    ></textarea>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Notes (Optional)</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional shift remarks">
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="allow_unpermitted" value="1" id="allowUnpermittedCheck">
                    <label class="form-check-label small muted" for="allowUnpermittedCheck">
                        Allow assignment even if category is not yet in employee's Shift Roles
                    </label>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Edit Existing Shift Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/schedules.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="editScheduleModalLabel">Edit Shift Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body warm-modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="schedule_id" id="editScheduleId" value="">

                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Staff Member *</label>
                        <select name="staff_id" id="editStaffSelect" class="form-select form-select-sm" required>
                            <?php foreach ($staffDirectory as $member): ?>
                                <option value="<?= (int) $member['id'] ?>">
                                    <?= e((string) $member['full_name']) ?> (<?= e((string) $member['employee_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label small fw-bold mb-1">Job Category *</label>
                        <select name="assigned_category_id" id="editCategorySelect" class="form-select form-select-sm" required>
                            <?php foreach ($jobCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e((string) $cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">Shift Date *</label>
                        <input type="date" id="editScheduleDate" name="schedule_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">Start Time *</label>
                        <input type="time" id="editStartTime" name="start_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold mb-1">End Time *</label>
                        <input type="time" id="editEndTime" name="end_time" class="form-control form-control-sm" required>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-sm-7">
                        <label class="form-label small fw-bold mb-1">Work Station</label>
                        <input type="text" id="editWorkLocation" name="work_location" class="form-control form-control-sm">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label small fw-bold mb-1">Shift Status</label>
                        <select id="editScheduleStatus" name="status" class="form-select form-select-sm">
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Task Description *</label>
                    <textarea id="editTaskDescription" name="task_description" class="form-control form-control-sm" rows="2" required></textarea>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Notes</label>
                    <input type="text" id="editScheduleNotes" name="notes" class="form-control form-control-sm">
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Update Shift</button>
            </div>
        </form>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/admin_footer.php';