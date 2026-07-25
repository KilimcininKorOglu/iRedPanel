<?php if (isset($paginatedResult) && $paginatedResult->totalPages() > 1): ?>
<nav class="row" style="margin-top: 1rem;">
  <div class="col" style="text-align: center;">
    <?php
    $baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $queryParams = $_GET;
    ?>

    <?php if ($paginatedResult->hasPreviousPage()): ?>
      <?php $queryParams['page'] = $paginatedResult->currentPage - 1; ?>
      <a href="<?= $e($baseUrl . '?' . http_build_query($queryParams)) ?>" class="button outline">&laquo; <?= $te('pagination.previous') ?></a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $paginatedResult->totalPages(); $i++): ?>
      <?php $queryParams['page'] = $i; ?>
      <?php if ($i === $paginatedResult->currentPage): ?>
        <strong style="padding: 0.5rem;"><?= $i ?></strong>
      <?php else: ?>
        <a href="<?= $e($baseUrl . '?' . http_build_query($queryParams)) ?>" style="padding: 0.5rem;"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($paginatedResult->hasNextPage()): ?>
      <?php $queryParams['page'] = $paginatedResult->currentPage + 1; ?>
      <a href="<?= $e($baseUrl . '?' . http_build_query($queryParams)) ?>" class="button outline"><?= $te('pagination.next') ?> &raquo;</a>
    <?php endif; ?>

    <span class="text-light" style="margin-left: 1rem;">
      <?= $te('pagination.total', ['count' => (int) $paginatedResult->totalCount]) ?>
    </span>
  </div>
</nav>
<?php endif; ?>
