<?php $pageTitle = $t('user.list_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('user.list_title') ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/domains"><?= $e($domain) ?></a> /
          <span class="text-light"><?= $te('user.list_title') ?></span>
        </div>
      </div>

      <?php if (!empty($supportsCreate)): ?>
      <div class="row">
        <div class="col">
          <a href="/<?= $e($domain) ?>/users/create" class="button primary outline"><?= $te('user.create') ?></a>
        </div>
      </div>
      <?php endif; ?>

      <?php
      $letters = range('A', 'Z');
      $baseUrl = '/' . urlencode($domain) . '/users';
      ?>
      <div style="margin: 0.5rem 0;">
        <a href="<?= $e($baseUrl) ?>" <?php if (empty($statusFilter)): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.all') ?></a> |
        <a href="<?= $e($baseUrl) ?>?status=active" <?php if (($statusFilter ?? '') === 'active'): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.active') ?></a> |
        <a href="<?= $e($baseUrl) ?>?status=disabled" <?php if (($statusFilter ?? '') === 'disabled'): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.disabled') ?></a>
      </div>
      <div style="margin: 0.5rem 0;">
        <a href="<?= $e($baseUrl) ?>" <?php if (empty($currentLetter)): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.all') ?></a>
        <?php foreach ($letters as $letter): ?>
          <a href="<?= $e($baseUrl) ?>?letter=<?= $e($letter) ?>"
             <?php if (($currentLetter ?? '') === $letter): ?>style="font-weight:bold"<?php endif; ?>><?= $letter ?></a>
        <?php endforeach; ?>
      </div>

      <form method="post" action="/<?= $e($domain) ?>/users/bulk">
        <?= $csrfField ?>

      <table class="striped">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll" onclick="document.querySelectorAll('input[name=\\'selectedUsers[]\\']').forEach(c=>c.checked=this.checked)" /></th>
            <?php
            $sortUrl = function(string $col) use ($baseUrl, $sortBy, $sortDir, $currentLetter) {
                $newDir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
                $params = ['sort' => $col, 'dir' => $newDir];
                if ($currentLetter) $params['letter'] = $currentLetter;
                return $baseUrl . '?' . http_build_query($params);
            };
            $sortIcon = function(string $col) use ($sortBy, $sortDir) {
                if ($sortBy !== $col) return '';
                return $sortDir === 'asc' ? ' &#9650;' : ' &#9660;';
            };
            ?>
            <th><a href="<?= $e($sortUrl('uid')) ?>"><?= $te('user.identifier') ?><?= $sortIcon('uid') ?></a></th>
            <th><a href="<?= $e($sortUrl('mailQuota')) ?>"><?= $te('user.quota_mb') ?><?= $sortIcon('mailQuota') ?></a></th>
            <th><?= $te('user.used') ?></th>
            <th><?= $te('admin.global_admin') ?></th>
            <th><a href="<?= $e($sortUrl('accountStatus')) ?>"><?= $te('common.status') ?><?= $sortIcon('accountStatus') ?></a></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <tr>
            <td><input type="checkbox" name="selectedUsers[]" value="<?= $e($user->uid) ?>" /></td>
            <td>
              <a href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/general"><?= $e($user->uid) ?></a>
            </td>
            <td><?= $e($user->mailQuota === 0 ? $t('common.unlimited') : $user->mailQuota) ?></td>
            <?php
              $email = $user->uid . '@' . $domain;
              $usedBytes = ($usedQuotas[$email]['bytes'] ?? 0);
              $usedMb = $usedBytes > 0 ? (int) ($usedBytes / 1048576) : 0;
            ?>
            <td><?= $e($usedMb) ?> MB</td>
            <td><?= $localize($user->domainGlobalAdmin) ?></td>
            <td><?= $localize($user->accountStatus) ?></td>
            <td>
              <a href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/general" class="button primary outline"><?= $te('common.edit') ?></a>
              <form method="post" action="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/delete" style="display:inline" data-confirm="<?= $te('user.delete_confirm', ['uid' => $user->uid]) ?>">
                <?= $csrfField ?>
                <button type="submit" class="button error outline"><?= $te('common.delete') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top: 0.5rem;">
        <select name="action" required>
          <option value=""><?= $te('common.bulk_action') ?></option>
          <option value="enable"><?= $te('common.enable_selected') ?></option>
          <option value="disable"><?= $te('common.disable_selected') ?></option>
          <option value="delete"><?= $te('common.delete_selected') ?></option>
        </select>
        <button type="submit" class="button outline" onclick="return this.form.action.value==='delete' ? confirm(<?= htmlspecialchars(json_encode($t('user.bulk_delete_confirm')), ENT_QUOTES) ?>) : true"><?= $te('common.apply') ?></button>
      </div>
      </form>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
