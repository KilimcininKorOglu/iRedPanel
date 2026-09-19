<?php
$pageTitle = $t('wblist.view_title', ['account' => $account ?? '']);
$wbLabel = fn(string $wb): string => $wb === 'W' ? $t('wblist.whitelist') : $t('wblist.blacklist');
$baseUrl = $account === '@.' ? '/amavisd/wblist' : '/amavisd/wblist/' . rawurlencode($account);
$filterUrl = fn(string $wb): string => $baseUrl . ($wb === '' ? '' : '?wb=' . $wb);
$sections = [
    'inbound' => ['wblist.inbound_title', 'wblist.sender', 'wblist.no_inbound', $inboundList],
    'outbound' => ['wblist.outbound_title', 'wblist.recipient', 'wblist.no_outbound', $outboundList],
];
?>
<div class="page-header">
  <div>
    <h1><?= $te('wblist.title') ?></h1>
    <div class="small text-body-secondary mt-1">
      <?= $te('spampolicy.account') ?>: <span class="text-body"><?= $e($account) ?></span>
      <?php if ($account === '@.'): ?>(<?= $te('wblist.global') ?>)<?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <ul class="nav nav-pills mb-3">
      <li class="nav-item"><a class="nav-link<?= ($wbFilter ?? '') === '' ? ' active' : '' ?>" href="<?= $e($filterUrl('')) ?>"><?= $te('common.all') ?></a></li>
      <li class="nav-item"><a class="nav-link<?= ($wbFilter ?? '') === 'W' ? ' active' : '' ?>" href="<?= $e($filterUrl('W')) ?>"><?= $te('wblist.whitelist') ?></a></li>
      <li class="nav-item"><a class="nav-link<?= ($wbFilter ?? '') === 'B' ? ' active' : '' ?>" href="<?= $e($filterUrl('B')) ?>"><?= $te('wblist.blacklist') ?></a></li>
    </ul>

    <form method="get" action="/amavisd/wblist" class="d-flex flex-wrap gap-2 mb-4">
      <input type="text" name="account" data-account-picker="single" data-types="user" class="form-control flex-grow-1 w-auto" value="<?= $e($account !== '@.' ? $account : '') ?>" placeholder="<?= $te('spampolicy.account_placeholder') ?>" aria-label="<?= $te('spampolicy.account') ?>" />
      <button type="submit" class="btn btn-outline-secondary"><?= $te('wblist.load_list') ?></button>
    </form>

    <?php foreach ($sections as $direction => [$titleKey, $columnKey, $emptyKey, $entries]): ?>
    <div class="card">
      <div class="card-header"><?= $te($titleKey) ?></div>
      <div class="card-body">
        <form method="post">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="direction" value="<?= $direction ?>" />
          <textarea name="sender" data-account-picker="multi" rows="3" class="form-control mb-2" placeholder="<?= $te('wblist.sender_placeholder') ?>" required aria-label="<?= $te($columnKey) ?>"></textarea>
          <div class="d-flex flex-wrap gap-2">
            <select name="wb" class="form-select w-auto" aria-label="<?= $te('common.type') ?>">
              <option value="W"><?= $te('wblist.whitelist') ?></option>
              <option value="B"><?= $te('wblist.blacklist') ?></option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('wblist.add') ?></button>
          </div>
        </form>
      </div>
      <?php if (!empty($entries)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te($columnKey) ?></th>
              <th><?= $te('common.type') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
              <td><?= $e($entry['sender']) ?></td>
              <td><span class="badge badge-status <?= $tone('wblist', (string) $entry['wb']) ?>"><?= $e($wbLabel($entry['wb'])) ?></span></td>
              <td>
                <form method="post" class="table-actions">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="remove" />
                  <input type="hidden" name="direction" value="<?= $direction ?>" />
                  <input type="hidden" name="sender" value="<?= $e($entry['sender']) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i><?= $te('wblist.remove') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        <form method="post" data-confirm="<?= $te('wblist.remove_all_confirm') ?>">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="removeAll" />
          <input type="hidden" name="direction" value="<?= $direction ?>" />
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('wblist.remove_all') ?></button>
        </form>
      </div>
      <?php else: ?>
      <div class="card-body pt-0 text-body-secondary"><?= $te($emptyKey) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <a href="/amavisd/quarantine"><i class="bi bi-arrow-left me-1"></i><?= $te('wblist.back') ?></a>
  </div>
</div>
