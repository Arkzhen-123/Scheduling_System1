<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/admin_header.php';
?>
<!-- Warm Sage & Cream Summary Strip -->
<section class="schedule-summary-strip">
    <div class="warm-summary-tile">
        <div class="warm-summary-label">Total Shift Logs</div>
        <div class="warm-summary-number"><?= (int) $summary['total'] ?></div>
    </div>

    <div class="warm-summary-tile sage-tint">
        <div class="warm-summary-label">Present / Completed</div>
        <div class="warm-summary-number"><?= (int) $summary['presentCompleted'] ?></div>
    </div>

    <div class="warm-summary-tile honey-tint">
        <div class="warm-summary-label">Late Check-Ins</div>
        <div class="warm-summary-number"><?= (int) $summary['late'] ?></div>
    </div>

    <div class="warm-summary-tile">
        <div class="warm-summary-label">Absent / On Leave</div>
        <div class="warm-summary-number"><?= (int) $summary['absentOrLeave'] ?></div>
    </div>
</section>

<!-- Main Attendance Card -->
<section class="content-card">
    <div class="card-heading">
        <div>
            <h2>Workforce Attendance Logs</h2>
            <div class="small muted">Monitor check-ins, check-outs, and shift attendance records</div>
        </div>
    </div>

    <!-- Instant Filter Bar (Employee, Category, and Keyword) -->
    <form method="get" action="<?= e($baseUrl) ?>/admin/attendance.php" class="schedule-filter-panel auto-filter-form">
        <div class="schedule-filter-row">
            <div class="schedule-filter-field">
                <label for="filterAttendanceStaff">Employee</label>
                <select id="filterAttendanceStaff" name="staff_id" class="form-select form-select-sm" style="min-width: 190px;">
                    <option value="0">All Employees</option>
                    <?php foreach ($staffDirectory as $member): ?>
                        <option value="<?= (int) $member['id'] ?>" <?= $staffFilter === (int) $member['id'] ? 'selected' : '' ?>>
                            <?= e((string) $member['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="schedule-filter-field">
                <label for="filterAttendanceCategory">Category</label>
                <select id="filterAttendanceCategory" name="category_id" class="form-select form-select-sm" style="min-width: 185px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($jobCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoryFilter === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= e((string) $cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="schedule-filter-field flex-grow-1" style="max-width: 280px;">
                <label for="filterAttendanceSearch">Keyword</label>
                <input
                    type="search"
                    id="filterAttendanceSearch"
                    name="search"
                    value="<?= e($search) ?>"
                    class="form-control form-control-sm"
                    placeholder="Search task, station, or staff..."
                >
            </div>

            <?php if ($staffFilter > 0 || $categoryFilter > 0 || $search !== ''): ?>
                <div>
                    <a href="<?= e($baseUrl) ?>/admin/attendance.php" class="btn btn-light btn-sm">Reset</a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($attendanceRecords)): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Scheduled Shift</th>
                        <th>Check-In / Check-Out</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendanceRecords as $rec): ?>
                        <?php
                        $schedId = (int) $rec['schedule_id'];
                        $schedDate = (string) $rec['schedule_date'];
                        $isToday = !empty($rec['is_today']);
                        $effectiveStatus = (string) ($rec['effective_status'] ?? 'not_started');

                        [$pillClass, $pillLabel] = match ($effectiveStatus) {
                            'present' => ['scheduled', 'Present'],
                            'completed' => ['completed', 'Completed'],
                            'late' => ['late', 'Late'],
                            'on_leave' => ['leave', 'On Leave'],
                            'absent' => ['cancelled', 'Absent'],
                            'incomplete' => ['late', 'Incomplete'],
                            default => ['pending', 'Pending'],
                        };

                        $checkInVal = !empty($rec['check_in_time'])
                            ? date('Y-m-d\TH:i', strtotime((string) $rec['check_in_time']))
                            : '';
                        $checkOutVal = !empty($rec['check_out_time'])
                            ? date('Y-m-d\TH:i', strtotime((string) $rec['check_out_time']))
                            : '';
                        $shiftSummary = date('M j, Y', strtotime($schedDate))
                            . ' (' . admin_format_time((string) $rec['start_time'])
                            . ' – ' . admin_format_time((string) $rec['end_time']) . ')';
                        $modalStatusDefault = in_array($effectiveStatus, ['present', 'completed', 'late', 'absent', 'incomplete', 'not_started'], true)
                            ? $effectiveStatus
                            : 'absent';
                        ?>
                        <tr>
                            <td>
                                <div class="shift-date-box <?= $isToday ? 'today-box' : '' ?>">
                                    <span class="shift-date-main"><?= e(date('M j, Y', strtotime($schedDate))) ?></span>
                                    <span class="shift-date-sub"><?= $isToday ? 'Today' : e(date('l', strtotime($schedDate))) ?></span>
                                </div>
                            </td>

                            <td>
                                <strong><?= e((string) $rec['staff_name']) ?></strong>
                                <div class="small muted mt-1">
                                    <?= e((string) $rec['employee_id']) ?>
                                    <?php if (!empty($rec['staff_position'])): ?>
                                        &middot; <?= e((string) $rec['staff_position']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <span class="warm-category-tag"><?= e((string) $rec['category_name']) ?></span>
                                <div class="mt-1 fw-semibold" style="font-size: 13px;">
                                    <?= e(admin_format_time((string) $rec['start_time'])) ?>
                                    &ndash;
                                    <?= e(admin_format_time((string) $rec['end_time'])) ?>
                                </div>
                                <?php if (!empty($rec['work_location'])): ?>
                                    <div class="small muted"><?= e((string) $rec['work_location']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="attendance-time-pair">
                                    <span>
                                        <strong>In:</strong>
                                        <?= !empty($rec['check_in_time']) ? e(admin_format_time((string) $rec['check_in_time'])) : '—' ?>
                                    </span>
                                    <span>
                                        <strong>Out:</strong>
                                        <?= !empty($rec['check_out_time']) ? e(admin_format_time((string) $rec['check_out_time'])) : '—' ?>
                                    </span>
                                </div>
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
                                        aria-label="Attendance actions for <?= e((string) $rec['staff_name']) ?>"
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
                                                data-bs-target="#editAttendanceModal"
                                                data-schedule-id="<?= $schedId ?>"
                                                data-staff-name="<?= e((string) $rec['staff_name']) ?>"
                                                data-shift-label="<?= e($shiftSummary) ?>"
                                                data-status="<?= e($modalStatusDefault) ?>"
                                                data-check-in="<?= e($checkInVal) ?>"
                                                data-check-out="<?= e($checkOutVal) ?>"
                                            >
                                                Edit Time &amp; Status
                                            </button>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <li>
                                            <form method="post" action="<?= e($baseUrl) ?>/admin/attendance.php">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_attendance">
                                                <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                <input type="hidden" name="attendance_status" value="present">
                                                <button type="submit" class="dropdown-item">Mark Present</button>
                                            </form>
                                        </li>

                                        <li>
                                            <form method="post" action="<?= e($baseUrl) ?>/admin/attendance.php">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_attendance">
                                                <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                <input type="hidden" name="attendance_status" value="late">
                                                <button type="submit" class="dropdown-item action-warn">Mark Late</button>
                                            </form>
                                        </li>

                                        <li>
                                            <form method="post" action="<?= e($baseUrl) ?>/admin/attendance.php">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_attendance">
                                                <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                <input type="hidden" name="attendance_status" value="completed">
                                                <button type="submit" class="dropdown-item">Mark Completed</button>
                                            </form>
                                        </li>

                                        <li>
                                            <form method="post" action="<?= e($baseUrl) ?>/admin/attendance.php">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_attendance">
                                                <input type="hidden" name="schedule_id" value="<?= $schedId ?>">
                                                <input type="hidden" name="attendance_status" value="absent">
                                                <button type="submit" class="dropdown-item action-danger">Mark Absent</button>
                                            </form>
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
        <div class="empty-state">No attendance logs matched your current filter.</div>
    <?php endif; ?>
</section>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= e($baseUrl) ?>/admin/attendance.php" class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title" id="editAttendanceModalLabel">
                    Attendance Record &mdash; <span id="attendanceStaffLabel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body warm-modal-body">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_attendance">
                <input type="hidden" name="schedule_id" id="attendanceScheduleId" value="">

                <div class="small muted mb-2" id="attendanceShiftLabel"></div>

                <div class="mb-2">
                    <label for="attendanceStatusSelect" class="form-label small fw-bold mb-1">Attendance Status *</label>
                    <select id="attendanceStatusSelect" name="attendance_status" class="form-select form-select-sm" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="completed">Completed</option>
                        <option value="absent">Absent</option>
                        <option value="incomplete">Incomplete</option>
                        <option value="not_started">Not Started (Pending)</option>
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6">
                        <label for="attendanceCheckInInput" class="form-label small fw-bold mb-1">Check-In Time</label>
                        <input type="datetime-local" id="attendanceCheckInInput" name="check_in_time" class="form-control form-control-sm">
                    </div>
                    <div class="col-sm-6">
                        <label for="attendanceCheckOutInput" class="form-label small fw-bold mb-1">Check-Out Time</label>
                        <input type="datetime-local" id="attendanceCheckOutInput" name="check_out_time" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="form-text small mt-1">
                    Leave timestamps blank to auto-fill from the scheduled shift when marking Present, Late, or Completed.
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm">Save Attendance</button>
            </div>
        </form>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/admin_footer.php';