<?php
$pageTitle = $t('spampolicy.view_title', ['account' => $account ?? '']);
$thresholds = [
    'spamTagLevel' => ['spampolicy.tag_level', 'spampolicy.tag_level_hint', 'e.g. 2.0'],
    'spamTag2Level' => ['spampolicy.tag2_level', 'spampolicy.tag2_level_hint', 'e.g. 6.2'],
    'spamKillLevel' => ['spampolicy.kill_level', 'spampolicy.kill_level_hint', 'e.g. 6.9'],
];
$switches = [
    'bypassVirusChecks' => 'spampolicy.bypass_virus',
    'bypassSpamChecks' => 'spampolicy.bypass_spam',
    'bypassBannedChecks' => 'spampolicy.bypass_banned',
    'bypassHeaderChecks' => 'spampolicy.bypass_header',
    'virusLover' => 'spampolicy.deliver_virus',
    'spamLover' => 'spampolicy.deliver_spam',
    'bannedFilesLover' => 'spampolicy.deliver_banned',
    'badHeaderLover' => 'spampolicy.deliver_bad_header',
];
$quarantines = [
    'spamQuarantine' => 'spampolicy.quarantine_spam',
    'virusQuarantine' => 'spampolicy.quarantine_virus',
    'bannedQuarantine' => 'spampolicy.quarantine_banned',
    'badHeaderQuarantine' => 'spampolicy.quarantine_bad_header',
];
$quarantineChoices = ['default' => 'spampolicy.quarantine_default', 'yes' => 'spampolicy.quarantine_on', 'no' => 'spampolicy.quarantine_off'];
$quarantineValue = static fn (?bool $stored): string => $stored === null ? 'default' : ($stored ? 'yes' : 'no');
?>
<div class="page-header">
  <div>
    <h1><?= $te('spampolicy.title') ?></h1>
    <div class="small text-body-secondary mt-1">
      <?= $te('spampolicy.account') ?>: <span class="text-body"><?= $e($account) ?></span>
      <?php if ($account === '@.'): ?>(<?= $te('spampolicy.global_default') ?>)<?php endif; ?>
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
    <form method="get" action="/amavisd/spam-policy" class="d-flex flex-wrap gap-2 mb-4">
      <input type="text" name="account" data-account-picker="single" data-types="user" class="form-control flex-grow-1 w-auto" value="<?= $e($account !== '@.' ? $account : '') ?>" placeholder="<?= $te('spampolicy.account_placeholder') ?>" aria-label="<?= $te('spampolicy.account') ?>" />
      <button type="submit" class="btn btn-outline-secondary"><?= $te('spampolicy.load_policy') ?></button>
    </form>

    <form method="post">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="save" />

      <div class="card">
        <div class="card-header"><?= $te('spampolicy.thresholds') ?></div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <?php foreach ($thresholds as $field => [$labelKey, $hintKey, $placeholder]): ?>
            <div class="col-md-4">
              <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label>
              <input id="<?= $field ?>" type="number" step="0.1" name="<?= $field ?>" class="form-control" value="<?= $e($policy->$field ?? '') ?>" placeholder="<?= $e($placeholder) ?>" />
              <div class="form-text"><?= $te($hintKey) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="spamSubjectTag" class="form-label"><?= $te('spampolicy.subject_tag') ?></label>
              <input id="spamSubjectTag" type="text" name="spamSubjectTag" class="form-control" value="<?= $e($policy->spamSubjectTag ?? '') ?>" placeholder="e.g. [SPAM?]" />
            </div>
            <div class="col-md-6">
              <label for="spamSubjectTag2" class="form-label"><?= $te('spampolicy.subject_tag2') ?></label>
              <input id="spamSubjectTag2" type="text" name="spamSubjectTag2" class="form-control" value="<?= $e($policy->spamSubjectTag2 ?? '') ?>" placeholder="e.g. [SPAM]" />
            </div>
          </div>
          <div class="form-check form-switch mt-3">
            <input type="checkbox" class="form-check-input" id="alwaysInsertXSpamHeaders" name="alwaysInsertXSpamHeaders" <?= ($policy?->alwaysInsertXSpamHeaders() ?? false) ? 'checked' : '' ?> />
            <label class="form-check-label" for="alwaysInsertXSpamHeaders"><?= $te('spampolicy.always_insert_x_spam_headers') ?></label>
          </div>
          <div class="form-text"><?= $te('spampolicy.always_insert_hint') ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= $te('spampolicy.quarantine') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($quarantines as $field => $labelKey): ?>
            <div class="col-md-6">
              <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label>
              <select id="<?= $field ?>" name="<?= $field ?>" class="form-select">
                <?php foreach ($quarantineChoices as $choice => $choiceKey): ?>
                <option value="<?= $choice ?>"<?= $quarantineValue($policy->$field ?? null) === $choice ? ' selected' : '' ?>><?= $te($choiceKey) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= $te('spampolicy.bypass_delivery') ?></div>
        <div class="card-body">
          <div class="row g-2">
            <?php foreach ($switches as $field => $labelKey): ?>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="<?= $field ?>" name="<?= $field ?>" <?= ($policy->$field ?? false) ? 'checked' : '' ?> />
                <label class="form-check-label" for="<?= $field ?>"><?= $te($labelKey) ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><?= $te('spampolicy.save_policy') ?></button>
        <?php if ($policy !== null): ?>
        <button type="submit" name="action" value="delete" class="btn btn-outline-danger" data-confirm="<?= $te('spampolicy.delete_confirm') ?>"><i class="bi bi-trash3 me-1"></i><?= $te('spampolicy.delete_policy') ?></button>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!empty($policies)): ?>
    <div class="card">
      <div class="card-header"><?= $te('spampolicy.all_policies') ?></div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('spampolicy.account') ?></th>
              <th><?= $te('spampolicy.col_tag') ?></th>
              <th><?= $te('spampolicy.col_tag2') ?></th>
              <th><?= $te('spampolicy.col_kill') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($policies as $entry): ?>
            <tr>
              <td><a href="/amavisd/spam-policy/<?= $e($entry['account']) ?>" class="fw-medium"><?= $e($entry['account']) ?></a></td>
              <td><?= $e($entry['policy']->spamTagLevel ?? '-') ?></td>
              <td><?= $e($entry['policy']->spamTag2Level ?? '-') ?></td>
              <td><?= $e($entry['policy']->spamKillLevel ?? '-') ?></td>
              <td><div class="table-actions"><a href="/amavisd/spam-policy/<?= $e($entry['account']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <a href="/amavisd/quarantine"><i class="bi bi-arrow-left me-1"></i><?= $te('spampolicy.back') ?></a>
  </div>
</div>
