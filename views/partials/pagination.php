<?php
declare(strict_types=1);

if (!isset($pagination) || ($pagination['totalItems'] ?? 0) === 0) {
    return;
}

$currentPage = (int) $pagination['currentPage'];
$totalPages = (int) $pagination['totalPages'];
$startItem = (int) $pagination['startItem'];
$endItem = (int) $pagination['endItem'];
$totalItems = (int) $pagination['totalItems'];
$separator = str_contains($paginationBaseUrl, '?') ? '&' : '?';

// Show a window of up to 5 page numbers around the active page.
$windowStart = max(1, $currentPage - 2);
$windowEnd = min($totalPages, $windowStart + 4);
$windowStart = max(1, $windowEnd - 4);
?>
<div class="table-pagination-bar">
    <div class="small muted">
        Showing <?= $startItem ?>–<?= $endItem ?> of <?= $totalItems ?> <?= e($paginationLabel ?? 'item(s)') ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Table pagination">
            <ul class="pagination pagination-sm">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= $currentPage > 1 ? e($paginationBaseUrl . $separator . 'p=' . ($currentPage - 1)) : '#' ?>"
                        aria-label="Previous page"
                    >Previous</a>
                </li>

                <?php for ($pageNumber = $windowStart; $pageNumber <= $windowEnd; $pageNumber++): ?>
                    <li class="page-item <?= $pageNumber === $currentPage ? 'active' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= e($paginationBaseUrl . $separator . 'p=' . $pageNumber) ?>"
                        ><?= $pageNumber ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= $currentPage < $totalPages ? e($paginationBaseUrl . $separator . 'p=' . ($currentPage + 1)) : '#' ?>"
                        aria-label="Next page"
                    >Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>