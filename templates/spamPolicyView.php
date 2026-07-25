<?php $pageTitle = $t('spampolicy.view_title', ['account' => $account ?? '']); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('spampolicy.title') ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <p>
        <strong><?= $te('spampolicy.account') ?>:</strong> <?= $e($account) ?>
        <?php if ($account === '@.'): ?>(<?= $te('spampolicy.global_default') ?>)<?php endif; ?>
      </p>

      <form method="get" action="/amavisd/spam-policy" style="margin-bottom:1rem;">
        <div class="row">
          <div class="col-8">
            <input type="text" name="account" value="<?= $e($account !== '@.' ? $account : '') ?>" placeholder="<?= $te('spampolicy.account_placeholder') ?>" />
          </div>
          <div class="col-4">
            <button type="submit" class="button outline"><?= $te('spampolicy.load_policy') ?></button>
          </div>
        </div>
      </form>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="save" />

        <fieldset>
          <legend><?= $te('spampolicy.thresholds') ?></legend>

          <div class="row">
            <div class="col-4">
              <label for="spamTagLevel"><?= $te('spampolicy.tag_level') ?></label>
              <input id="spamTagLevel" type="number" step="0.1" name="spamTagLevel"
                value="<?= $e($policy->spamTagLevel ?? '') ?>" placeholder="e.g. 2.0" />
              <p class="text-light"><?= $te('spampolicy.tag_level_hint') ?></p>
            </div>
            <div class="col-4">
              <label for="spamTag2Level"><?= $te('spampolicy.tag2_level') ?></label>
              <input id="spamTag2Level" type="number" step="0.1" name="spamTag2Level"
                value="<?= $e($policy->spamTag2Level ?? '') ?>" placeholder="e.g. 6.2" />
              <p class="text-light"><?= $te('spampolicy.tag2_level_hint') ?></p>
            </div>
            <div class="col-4">
              <label for="spamKillLevel"><?= $te('spampolicy.kill_level') ?></label>
              <input id="spamKillLevel" type="number" step="0.1" name="spamKillLevel"
                value="<?= $e($policy->spamKillLevel ?? '') ?>" placeholder="e.g. 6.9" />
              <p class="text-light"><?= $te('spampolicy.kill_level_hint') ?></p>
            </div>
          </div>

          <div class="row">
            <div class="col-6">
              <label for="spamSubjectTag"><?= $te('spampolicy.subject_tag') ?></label>
              <input id="spamSubjectTag" type="text" name="spamSubjectTag"
                value="<?= $e($policy->spamSubjectTag ?? '') ?>" placeholder="e.g. [SPAM?]" />
            </div>
            <div class="col-6">
              <label for="spamSubjectTag2"><?= $te('spampolicy.subject_tag2') ?></label>
              <input id="spamSubjectTag2" type="text" name="spamSubjectTag2"
                value="<?= $e($policy->spamSubjectTag2 ?? '') ?>" placeholder="e.g. [SPAM]" />
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend><?= $te('spampolicy.bypass_delivery') ?></legend>

          <label><input type="checkbox" name="bypassVirusChecks" <?= ($policy->bypassVirusChecks ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.bypass_virus') ?></label>
          <label><input type="checkbox" name="bypassSpamChecks" <?= ($policy->bypassSpamChecks ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.bypass_spam') ?></label>
          <label><input type="checkbox" name="virusLover" <?= ($policy->virusLover ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.deliver_virus') ?></label>
          <label><input type="checkbox" name="spamLover" <?= ($policy->spamLover ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.deliver_spam') ?></label>
          <label><input type="checkbox" name="bannedFilesLover" <?= ($policy->bannedFilesLover ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.deliver_banned') ?></label>
          <label><input type="checkbox" name="badHeaderLover" <?= ($policy->badHeaderLover ?? false) ? 'checked' : '' ?> /> <?= $te('spampolicy.deliver_bad_header') ?></label>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('spampolicy.save_policy') ?></button>

        <?php if ($policy !== null): ?>
        <button type="submit" name="action" value="delete" class="button error outline"
          data-confirm="<?= $te('spampolicy.delete_confirm') ?>"><?= $te('spampolicy.delete_policy') ?></button>
        <?php endif; ?>
      </form>

      <?php if (!empty($policies)): ?>
      <hr />
      <h3><?= $te('spampolicy.all_policies') ?></h3>
      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('spampolicy.account') ?></th>
            <th><?= $te('spampolicy.col_tag') ?></th>
            <th><?= $te('spampolicy.col_tag2') ?></th>
            <th><?= $te('spampolicy.col_kill') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($policies as $entry): ?>
          <tr>
            <td><a href="/amavisd/spam-policy/<?= $e($entry['account']) ?>"><?= $e($entry['account']) ?></a></td>
            <td><?= $e($entry['policy']->spamTagLevel ?? '-') ?></td>
            <td><?= $e($entry['policy']->spamTag2Level ?? '-') ?></td>
            <td><?= $e($entry['policy']->spamKillLevel ?? '-') ?></td>
            <td><a href="/amavisd/spam-policy/<?= $e($entry['account']) ?>"><?= $te('common.edit') ?></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <p><a href="/amavisd/quarantine">&larr; <?= $te('spampolicy.back') ?></a></p>
    </div>
  </div>
</div>
