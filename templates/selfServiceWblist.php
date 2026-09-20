<?php
$pageTitle = $t('domain.pref_wblist');
$wbLabel = fn(string $wb): string => $wb === 'W' ? $t('wblist.whitelist') : $t('wblist.blacklist');
$sections = [
    'inbound' => ['wblist.inbound_title', 'wblist.sender', 'wblist.no_inbound', $inboundList],
    'outbound' => ['wblist.outbound_title', 'wblist.recipient', 'wblist.no_outbound', $outboundList],
];
?>
<div class="page-header">
  <div>
    <h1><?= $te('domain.pref_wblist') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <?php foreach ($sections as $direction => [$titleKey, $columnKey, $emptyKey, $entries]): ?>
    <div class="card">
      <div class="card-header"><?= $te($titleKey) ?></div><?= $help($titleKey) ?>
      <div class="card-body">
        <form method="post" class="d-flex flex-wrap gap-2">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="direction" value="<?= $direction ?>" />
          <input type="text" name="sender" class="form-control flex-grow-1 w-auto" placeholder="<?= $te('wblist.sender_placeholder') ?>" required aria-label="<?= $te($columnKey) ?>" />
          <select name="wb" class="form-select w-auto" aria-label="<?= $te('common.type') ?>">
            <option value="W"><?= $te('wblist.whitelist') ?></option>
            <option value="B"><?= $te('wblist.blacklist') ?></option>
          </select>
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('wblist.add') ?></button>
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
      <?php else: ?>
      <div class="card-body pt-0 text-body-secondary"><?= $te($emptyKey) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
