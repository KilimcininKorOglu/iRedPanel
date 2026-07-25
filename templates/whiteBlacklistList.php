<?php
$pageTitle = $t('wblist.view_title', ['account' => $account ?? '']);
$wbLabel = fn(string $wb): string => $wb === 'W' ? $t('wblist.whitelist') : $t('wblist.blacklist');
?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('wblist.title') ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <p>
        <strong><?= $te('spampolicy.account') ?>:</strong> <?= $e($account) ?>
        <?php if ($account === '@.'): ?>(<?= $te('wblist.global') ?>)<?php endif; ?>
      </p>

      <form method="get" action="/amavisd/wblist" style="margin-bottom:1rem;">
        <div class="row">
          <div class="col-8">
            <input type="text" name="account" value="<?= $e($account !== '@.' ? $account : '') ?>" placeholder="<?= $te('spampolicy.account_placeholder') ?>" />
          </div>
          <div class="col-4">
            <button type="submit" class="button outline"><?= $te('wblist.load_list') ?></button>
          </div>
        </div>
      </form>

      <!-- Inbound -->
      <h3><?= $te('wblist.inbound_title') ?></h3>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="add" />
        <input type="hidden" name="direction" value="inbound" />
        <div class="row">
          <div class="col-5">
            <input type="text" name="sender" placeholder="<?= $te('wblist.sender_placeholder') ?>" required />
          </div>
          <div class="col-3">
            <select name="wb">
              <option value="W"><?= $te('wblist.whitelist') ?></option>
              <option value="B"><?= $te('wblist.blacklist') ?></option>
            </select>
          </div>
          <div class="col-4">
            <button type="submit" class="button primary outline"><?= $te('wblist.add') ?></button>
          </div>
        </div>
      </form>

      <?php if (!empty($inboundList)): ?>
      <table class="striped" style="margin-top:1rem;">
        <thead>
          <tr>
            <th><?= $te('wblist.sender') ?></th>
            <th><?= $te('common.type') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inboundList as $entry): ?>
          <tr>
            <td><?= $e($entry['sender']) ?></td>
            <td><?= $e($wbLabel($entry['wb'])) ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="direction" value="inbound" />
                <input type="hidden" name="sender" value="<?= $e($entry['sender']) ?>" />
                <button type="submit" class="button error outline"><?= $te('wblist.remove') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-light"><?= $te('wblist.no_inbound') ?></p>
      <?php endif; ?>

      <hr />

      <!-- Outbound -->
      <h3><?= $te('wblist.outbound_title') ?></h3>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="add" />
        <input type="hidden" name="direction" value="outbound" />
        <div class="row">
          <div class="col-5">
            <input type="text" name="sender" placeholder="<?= $te('wblist.sender_placeholder') ?>" required />
          </div>
          <div class="col-3">
            <select name="wb">
              <option value="W"><?= $te('wblist.whitelist') ?></option>
              <option value="B"><?= $te('wblist.blacklist') ?></option>
            </select>
          </div>
          <div class="col-4">
            <button type="submit" class="button primary outline"><?= $te('wblist.add') ?></button>
          </div>
        </div>
      </form>

      <?php if (!empty($outboundList)): ?>
      <table class="striped" style="margin-top:1rem;">
        <thead>
          <tr>
            <th><?= $te('wblist.recipient') ?></th>
            <th><?= $te('common.type') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($outboundList as $entry): ?>
          <tr>
            <td><?= $e($entry['sender']) ?></td>
            <td><?= $e($wbLabel($entry['wb'])) ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="direction" value="outbound" />
                <input type="hidden" name="sender" value="<?= $e($entry['sender']) ?>" />
                <button type="submit" class="button error outline"><?= $te('wblist.remove') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-light"><?= $te('wblist.no_outbound') ?></p>
      <?php endif; ?>

      <p><a href="/amavisd/quarantine">&larr; <?= $te('wblist.back') ?></a></p>
    </div>
  </div>
</div>
