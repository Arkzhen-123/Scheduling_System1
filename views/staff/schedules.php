<?php
declare(strict_types=1);

require __DIR__ . '/../layouts/staff_header.php';
?>
<section class="content-card">
    <div class="card-heading">
        <div>
            <h2>My Schedules</h2>
            <div class="small muted mt-1"><?= e($monthDate->format('F Y')) ?></div>
        </div>

        <div class="month-navigation">
            <a href="<?= e($baseUrl) ?>/staff/schedules.php?month=<?= e($previousMonth) ?>" aria-label="Previous month" title="Previous month">‹</a>
            <a href="<?= e($baseUrl) ?>/staff/schedules.php?month=<?= e($nextMonth) ?>" aria-label="Next month" title="Next month">›</a>
        </div>
    </div>

    <?php if ($paginatedSchedules): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift / Time</th>
                        <th>Task &amp; Department</th>
                        <th>Location / Notes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedSchedules as $schedule): ?>
                        <tr>
                            <td>
                                <?= e(date('M j, Y', strtotime((string) $schedule['schedule_date']))) ?>
                            </td>
                            <td>
                                <?= e(staff_format_time((string) $schedule['start_time'])) ?>
                                &ndash;
                                <?= e(staff_format_time((string) $schedule['end_time'])) ?>
                            </td>
                            <td>
                                <strong><?= e($schedule['task_description'] ?: 'Assigned shift') ?></strong>
                                <?php if (!empty($schedule['category_name'])): ?>
                                    <div class="small muted mt-1"><?= e($schedule['category_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($schedule['work_location'] ?: 'Not specified') ?>
                                <?php if (!empty($schedule['notes'])): ?>
                                    <div class="muted small mt-1"><?= e($schedule['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill"><?= e(ucfirst((string) $schedule['status'])) ?></span>
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