<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/admin_header.php';
?>
<!-- Welcome banner -->
<section class="welcome-card">
    <div>
        <h2>Welcome back, <?= e(explode(' ', trim($adminName))[0] ?: 'Admin') ?>!</h2>
        <p>Here is today's workforce and scheduling overview for <?= e(COMPANY_NAME) ?>.</p>
    </div>
    <div class="welcome-date">
        <strong><?= e(date('D, M j')) ?></strong>
        <span><?= e(date('Y')) ?> &bull; Administrator Portal</span>
    </div>
</section>

<!-- Key workforce statistics -->
<section class="stat-grid admin-stat-grid">
    <div class="stat-card">
        <span class="stat-icon">&#9823;</span>
        <div class="stat-label">Active Staff</div>
        <div class="stat-value"><?= (int) $stats['activeStaff'] ?></div>
        <div class="stat-foot">Active employee accounts</div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">&#9636;</span>
        <div class="stat-label">Today's Shifts</div>
        <div class="stat-value"><?= (int) $stats['todaySchedules'] ?></div>
        <div class="stat-foot">Scheduled for <?= e(date('M j, Y')) ?></div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">&#10003;</span>
        <div class="stat-label">Present Today</div>
        <div class="stat-value text-success"><?= (int) $stats['presentToday'] ?></div>
        <div class="stat-foot">Checked in for today's shifts</div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">&#9993;</span>
        <div class="stat-label">Mail Requests</div>
        <div class="stat-value"><?= (int) $stats['totalMailRequests'] ?></div>
        <div class="stat-foot">Password-assistance requests received</div>
    </div>
</section>

<div class="content-grid">
    <!-- Today's scheduled shifts table -->
    <div>
        <section class="content-card">
            <div class="card-heading">
                <h2>Today's Schedule Overview</h2>
                <a class="btn btn-outline-green btn-sm" href="<?= e($baseUrl) ?>/admin/schedules.php">
                    Manage Schedules
                </a>
            </div>

            <?php if (!empty($todaySchedules)): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Shift</th>
                                <th>Task &amp; Location</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todaySchedules as $schedule): ?>
                                <?php
                                $recordStatus = (string) ($schedule['attendance_status'] ?? '');
                                $shiftEndTimestamp = strtotime($today . ' ' . $schedule['end_time']);
                                $shiftHasEnded = $shiftEndTimestamp !== false && $shiftEndTimestamp < time();

                                if (!empty($schedule['check_out_time'])) {
                                    $badgeLabel = 'Completed';
                                    $badgeClass = '';
                                } elseif (!empty($schedule['check_in_time'])) {
                                    $badgeLabel = $recordStatus === 'late' ? 'Late' : 'Checked in';
                                    $badgeClass = '';
                                } elseif ($recordStatus === 'absent' || $shiftHasEnded) {
                                    $badgeLabel = 'Absent';
                                    $badgeClass = 'status-absent';
                                } else {
                                    $badgeLabel = 'Scheduled';
                                    $badgeClass = '';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) $schedule['staff_name']) ?></strong>
                                        <div class="small muted mt-1">
                                            <?= e((string) ($schedule['employee_id'] ?? '')) ?>
                                            <?php if (!empty($schedule['category_name'])): ?>
                                                &middot; <?= e((string) $schedule['category_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= e(admin_format_time((string) $schedule['start_time'])) ?>
                                        &ndash;
                                        <?= e(admin_format_time((string) $schedule['end_time'])) ?>
                                    </td>
                                    <td>
                                        <?= e((string) ($schedule['task_description'] ?: 'Assigned shift')) ?>
                                        <?php if (!empty($schedule['work_location'])): ?>
                                            <div class="small muted mt-1"><?= e((string) $schedule['work_location']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-pill <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No shifts are scheduled for today.</div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Right column: quick navigation and recent assistance requests -->
    <div>
        <section class="content-card">
            <div class="card-heading">
                <h2>Quick Actions</h2>
            </div>

            <div class="quick-action-list">
                <a class="quick-action-link" href="<?= e($baseUrl) ?>/admin/staff.php">
                    <span>Add or Manage Staff</span>
                    <span>&#8250;</span>
                </a>
                <a class="quick-action-link" href="<?= e($baseUrl) ?>/admin/schedules.php">
                    <span>Assign Shift Schedules</span>
                    <span>&#8250;</span>
                </a>
                <a class="quick-action-link" href="<?= e($baseUrl) ?>/admin/attendance.php">
                    <span>Review Attendance Logs</span>
                    <span>&#8250;</span>
                </a>
                <a class="quick-action-link" href="<?= e($baseUrl) ?>/admin/settings.php">
                    <span>System &amp; Gmail Settings</span>
                    <span>&#8250;</span>
                </a>
            </div>
        </section>

        <section class="content-card">
            <div class="card-heading">
                <h2>Recent Assistance Requests</h2>
                <span class="small muted"><?= (int) ($unreadMailCount ?? 0) ?> unread</span>
            </div>

            <?php if (!empty($recentMailRequests)): ?>
                <?php foreach ($recentMailRequests as $mail): ?>
                    <?php
                    $requestName = ($mail['full_name'] ?? '') !== ''
                        ? (string) $mail['full_name']
                        : ((string) ($mail['submitted_email'] ?? 'Account assistance request'));
                    $requestDate = (string) ($mail['created_at'] ?? '');
                    ?>
                    <div class="profile-detail">
                        <div class="value"><?= e($requestName) ?></div>
                        <div class="label mt-1">
                            <?= e((string) ($mail['submitted_email'] ?? '')) ?>
                            <?php if ($requestDate !== ''): ?>
                                &middot; <?= e($requestDate) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">You're all caught up. No requests found.</div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
require __DIR__ . '/../layouts/admin_footer.php';