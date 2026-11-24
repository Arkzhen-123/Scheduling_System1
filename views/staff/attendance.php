<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/staff_header.php';
?>
<!-- Today's schedule check-in/out card -->
<section class="content-card">
    <div class="card-heading">
        <h2>Today's Schedule</h2>
        <span class="small muted"><?= e(date('F j, Y')) ?></span>
    </div>

    <?php if ($hasCurrentSchedule): ?>
        <div class="attendance-panel">
            <div class="attendance-state">
                <?= e($currentSchedule['task_description'] ?: 'Assigned Shift') ?>
            </div>

            <div class="attendance-time mb-3">
                Shift:
                <?= e(staff_format_time((string) $currentSchedule['start_time'])) ?>
                &ndash;
                <?= e(staff_format_time((string) $currentSchedule['end_time'])) ?>

                <?php if (!empty($currentSchedule['category_name'])): ?>
                    <br>Department: <?= e($currentSchedule['category_name']) ?>
                <?php endif; ?>

                <?php if (!empty($currentSchedule['work_location'])): ?>
                    <br>Work location: <?= e($currentSchedule['work_location']) ?>
                <?php endif; ?>
            </div>

            <?php if ($canCheckIn): ?>
                <form method="post" action="<?= e($attendanceUrl) ?>" onsubmit="return confirm('Confirm check-in for this shift?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="schedule_id" value="<?= (int) $currentSchedule['id'] ?>">
                    <input type="hidden" name="attendance_action" value="check_in">
                    <button class="btn btn-green" type="submit">✓ &nbsp; Check In</button>
                </form>
            <?php elseif ($canCheckOut): ?>
                <div class="small mb-3">
                    Check-in:
                    <strong><?= e(staff_format_time((string) $currentSchedule['check_in_time'])) ?></strong>
                </div>
                <form method="post" action="<?= e($attendanceUrl) ?>" onsubmit="return confirm('Confirm check-out for this shift?');">
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
        <div class="empty-state">You have no unfinished scheduled shift for today.</div>
    <?php endif; ?>
</section>

<!-- Monthly attendance counters -->
<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon">✓</span>
        <div class="stat-label">Present Days</div>
        <div class="stat-value text-success"><?= $totalPresentDays ?></div>
        <div class="stat-foot">For selected month</div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">✕</span>
        <div class="stat-label">Absent Days</div>
        <div class="stat-value text-danger"><?= $totalAbsentDays ?></div>
        <div class="stat-foot">Scheduled days missed</div>
    </div>

    <div class="stat-card">
        <span class="stat-icon">▤</span>
        <div class="stat-label">Scheduled Days</div>
        <div class="stat-value"><?= $totalScheduledDays ?></div>
        <div class="stat-foot">For selected month</div>
    </div>
</section>

<!-- Monthly attendance calendar -->
<section class="content-card">
    <div class="card-heading">
        <div>
            <h2>Attendance Calendar</h2>
            <div class="small muted mt-1"><?= e($monthDate->format('F Y')) ?></div>
        </div>

        <div class="month-navigation">
            <a href="<?= e($previousMonthUrl) ?>" aria-label="Previous month" title="Previous month">‹</a>
            <a href="<?= e($nextMonthUrl) ?>" aria-label="Next month" title="Next month">›</a>
        </div>
    </div>

    <div class="calendar">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
            <div class="calendar-weekday"><?= $weekday ?></div>
        <?php endforeach; ?>

        <?php for ($blank = 1; $blank < $calendarFirstWeekday; $blank++): ?>
            <div class="calendar-day blank" aria-hidden="true"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $calendarDays; $day++): ?>
            <?php
            $dateString = $monthDate->format('Y-m') . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $dayData = $attendanceByDay[$day] ?? null;
            $dayStatus = $dayData['status'] ?? '';
            $isToday = $dateString === $today;

            $dayClass = 'calendar-day';
            if ($dayStatus === 'present') {
                $dayClass .= ' present';
            } elseif ($dayStatus === 'absent') {
                $dayClass .= ' absent';
            }
            if ($isToday) {
                $dayClass .= ' today';
            }

            $dayTitle = 'No scheduled shift';
            if ($dayData) {
                if ($dayData['present'] > 0) {
                    $dayTitle = 'Present on ' . $dayData['present'] . ' shift(s)';
                } elseif ($dayData['absent'] > 0) {
                    $dayTitle = 'Absent on ' . $dayData['absent'] . ' shift(s)';
                } else {
                    $dayTitle = 'Scheduled; attendance not recorded';
                }
            }
            ?>
            <div class="<?= e($dayClass) ?>" title="<?= e($dayTitle) ?>">
                <span class="day-number"><?= $day ?></span>

                <?php if ($dayData): ?>
                    <span class="day-result">
                        <?php if ($dayStatus === 'present'): ?>
                            Present
                        <?php elseif ($dayStatus === 'absent'): ?>
                            Absent
                        <?php else: ?>
                            Scheduled
                        <?php endif; ?>
                    </span>

                    <?php if ($dayData['total'] > 1): ?>
                        <span class="day-result"><?= (int) $dayData['total'] ?> shifts</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div class="calendar-legend">
        <span><span class="legend-dot legend-present"></span>Present</span>
        <span><span class="legend-dot legend-absent"></span>Absent</span>
        <span><span class="legend-dot legend-neutral"></span>Scheduled / Not yet recorded</span>
    </div>

    <p class="small muted mt-3 mb-0">
        Only dates with assigned schedules are included.
        A scheduled shift is marked absent after its scheduled end time if no check-in was recorded.
    </p>
</section>

<!-- Paginated attendance details table -->
<section class="content-card">
    <div class="card-heading">
        <h2>Attendance Details</h2>
        <span class="small muted"><?= e($monthDate->format('F Y')) ?></span>
    </div>

    <?php if ($paginatedMonthSchedules): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Task</th>
                        <th>Shift</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedMonthSchedules as $schedule): ?>
                        <?php
                        $recordStatus = (string) ($schedule['attendance_status'] ?? '');
                        $isPresent = !empty($schedule['check_in_time'])
                            || in_array($recordStatus, ['present', 'late', 'completed'], true);

                        $shiftEndTimestamp = strtotime($schedule['schedule_date'] . ' ' . $schedule['end_time']);
                        $shiftHasEnded = $shiftEndTimestamp !== false && $shiftEndTimestamp < time();

                        $isAbsent = $recordStatus === 'absent'
                            || (empty($schedule['check_in_time']) && $shiftHasEnded);

                        if ($isPresent) {
                            $displayStatus = $recordStatus === 'late'
                                ? 'Late'
                                : (!empty($schedule['check_out_time']) ? 'Completed' : 'Present');
                            $statusClass = '';
                        } elseif ($isAbsent) {
                            $displayStatus = 'Absent';
                            $statusClass = 'status-absent';
                        } else {
                            $displayStatus = 'Scheduled';
                            $statusClass = '';
                        }
                        ?>
                        <tr>
                            <td><?= e(date('M j, Y', strtotime((string) $schedule['schedule_date']))) ?></td>
                            <td><?= e($schedule['task_description'] ?: 'Assigned shift') ?></td>
                            <td>
                                <?= e(staff_format_time((string) $schedule['start_time'])) ?>
                                &ndash;
                                <?= e(staff_format_time((string) $schedule['end_time'])) ?>
                            </td>
                            <td><?= e(staff_format_time($schedule['check_in_time'] ? (string) $schedule['check_in_time'] : null)) ?></td>
                            <td><?= e(staff_format_time($schedule['check_out_time'] ? (string) $schedule['check_out_time'] : null)) ?></td>
                            <td>
                                <span class="status-pill <?= e($statusClass) ?>"><?= e($displayStatus) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php else: ?>
        <div class="empty-state">No schedules were found for this month.</div>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/../layouts/staff_footer.php';