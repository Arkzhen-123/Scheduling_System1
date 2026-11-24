<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/staff_header.php';
?>
<!-- Welcome banner -->
<section class="welcome-card">
    <div>
        <h2>Welcome back, <?= e(explode(' ', trim($staffName))[0] ?: 'there') ?>!</h2>
        <p>Ready for another great day at <?= e(COMPANY_NAME) ?>?</p>
    </div>
    <div class="welcome-date">
        <strong><?= e(date('D, M j')) ?></strong>
        <span><?= e(date('Y')) ?> &bull; Your personal workspace</span>
    </div>
</section>

<!-- Quick stat cards -->
<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon">▤</span>
        <div class="stat-label">Today's Shifts</div>
        <div class="stat-value"><?= count($todaySchedules) ?></div>
        <div class="stat-foot">Scheduled for today</div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">◷</span>
        <div class="stat-label">Today's Check-in</div>
        <div class="stat-value" style="font-size:21px">
            <?= $hasCheckedIn ? e(staff_format_time((string) $currentSchedule['check_in_time'])) : '—' ?>
        </div>
        <div class="stat-foot">
            <?php if ($hasCheckedOut): ?>
                Shift completed
            <?php elseif ($hasCheckedIn): ?>
                You are checked in
            <?php else: ?>
                Not checked in yet
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">♙</span>
        <div class="stat-label">Your Position</div>
        <div class="stat-value" style="font-size:21px"><?= e($position ?: 'Staff') ?></div>
        <div class="stat-foot">Employee ID: <?= e($employeeId ?: 'Not assigned') ?></div>
    </div>
</section>

<div class="content-grid">
    <div>
        <!-- Current shift action panel -->
        <section class="content-card">
            <div class="card-heading">
                <h2>Current Schedule</h2>
                <span class="small muted"><?= e(date('F j, Y')) ?></span>
            </div>

            <?php if ($hasCurrentSchedule): ?>
                <div class="attendance-panel">
                    <div class="attendance-state">
                        <?= e($currentSchedule['task_description'] ?: 'Assigned Shift') ?>
                    </div>

                    <div class="attendance-time mb-3">
                        <?= e(staff_format_time((string) $currentSchedule['start_time'])) ?>
                        to
                        <?= e(staff_format_time((string) $currentSchedule['end_time'])) ?>

                        <?php if (!empty($currentSchedule['category_name'])): ?>
                            <br>Department: <strong><?= e($currentSchedule['category_name']) ?></strong>
                        <?php endif; ?>

                        <?php if (!empty($currentSchedule['work_location'])): ?>
                            <br>Work location: <?= e($currentSchedule['work_location']) ?>
                        <?php endif; ?>

                        <?php if (!empty($currentSchedule['notes'])): ?>
                            <br>Notes: <?= e($currentSchedule['notes']) ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($canCheckIn): ?>
                        <form method="post" action="<?= e($baseUrl) ?>/staff/dashboard.php" onsubmit="return confirm('Confirm check-in for this shift?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int) $currentSchedule['id'] ?>">
                            <input type="hidden" name="attendance_action" value="check_in">
                            <button class="btn btn-green" type="submit">✓ &nbsp; Check In</button>
                        </form>
                    <?php elseif ($canCheckOut): ?>
                        <div class="small mb-3">
                            Check-in time:
                            <strong><?= e(staff_format_time((string) $currentSchedule['check_in_time'])) ?></strong>
                        </div>
                        <form method="post" action="<?= e($baseUrl) ?>/staff/dashboard.php" onsubmit="return confirm('Confirm check-out for this shift?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int) $currentSchedule['id'] ?>">
                            <input type="hidden" name="attendance_action" value="check_out">
                            <button class="btn btn-outline-green" type="submit">↪ &nbsp; Check Out</button>
                        </form>
                    <?php else: ?>
                        <div class="attendance-state text-success">✓ Attendance completed</div>
                        <div class="attendance-time">
                            Check-in: <?= e(staff_format_time((string) $currentSchedule['check_in_time'])) ?>
                            <br>
                            Check-out: <?= e(staff_format_time((string) $currentSchedule['check_out_time'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size:26px;margin-bottom:8px">▤</div>
                    <strong class="d-block mb-1">No available shift</strong>
                    You do not have an unfinished scheduled shift for today.
                </div>
            <?php endif; ?>

            <p class="small muted mt-3 mb-0">
                Check-in and check-out are available only for your assigned schedule.
            </p>
        </section>

        <!-- Today's schedule table -->
        <section class="content-card">
            <div class="card-heading">
                <h2>Today's Schedule Overview</h2>
                <span class="small muted"><?= count($todaySchedules) ?> shift(s)</span>
            </div>

            <?php if ($todaySchedules): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Shift</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todaySchedules as $schedule): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($schedule['task_description'] ?: 'Assigned shift') ?></strong>
                                        <?php if (!empty($schedule['category_name'])): ?>
                                            <div class="small muted mt-1"><?= e($schedule['category_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e(staff_format_time((string) $schedule['start_time'])) ?>
                                        &ndash;
                                        <?= e(staff_format_time((string) $schedule['end_time'])) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($schedule['check_out_time'])): ?>
                                            <span class="status-pill">Completed</span>
                                        <?php elseif (!empty($schedule['check_in_time'])): ?>
                                            <span class="status-pill">Checked in</span>
                                        <?php else: ?>
                                            <span class="status-pill">Not started</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No shifts have been assigned for today.</div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Monthly attendance summary sidebar card -->
    <div>
        <section class="content-card">
            <div class="card-heading">
                <h2>Attendance Summary</h2>
            </div>

            <div class="profile-detail">
                <div class="label">Present Days This Month</div>
                <div class="value text-success"><?= $totalPresentDays ?> day(s)</div>
            </div>

            <div class="profile-detail">
                <div class="label">Absent Scheduled Days</div>
                <div class="value text-danger"><?= $totalAbsentDays ?> day(s)</div>
            </div>

            <div class="profile-detail">
                <div class="label">Total Scheduled Days</div>
                <div class="value"><?= $totalScheduledDays ?> day(s)</div>
            </div>

            <a class="btn btn-outline-green w-100 mt-3" href="<?= e($baseUrl) ?>/staff/attendance.php">
                View Attendance Calendar
            </a>
        </section>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/staff_footer.php';