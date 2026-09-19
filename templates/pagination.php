<?php if (isset($paginatedResult) && $paginatedResult->totalPages() > 1): ?>
<?php
$baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$queryParams = $_GET;
$pageUrl = function (int $page) use ($baseUrl, $queryParams, $e): string {
    $queryParams['page'] = $page;
    return $e($baseUrl . '?' . http_build_query($queryParams));
};
?>
<nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="<?= $te('pagination.total', ['count' => (int) $paginatedResult->totalCount]) ?>">
  <span class="small text-body-secondary"><?= $te('pagination.total', ['count' => (int) $paginatedResult->totalCount]) ?></span>
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item<?= $paginatedResult->hasPreviousPage() ? '' : ' disabled' ?>">
      <a class="page-link" href="<?= $pageUrl($paginatedResult->currentPage - 1) ?>">&laquo; <?= $te('pagination.previous') ?></a>
    </li>
    <?php for ($i = 1; $i <= $paginatedResult->totalPages(); $i++): ?>
    <li class="page-item<?= $i === $paginatedResult->currentPage ? ' active' : '' ?>">
      <a class="page-link" href="<?= $pageUrl($i) ?>"<?= $i === $paginatedResult->currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <li class="page-item<?= $paginatedResult->hasNextPage() ? '' : ' disabled' ?>">
      <a class="page-link" href="<?= $pageUrl($paginatedResult->currentPage + 1) ?>"><?= $te('pagination.next') ?> &raquo;</a>
    </li>
  </ul>
</nav>
<?php endif; ?>
