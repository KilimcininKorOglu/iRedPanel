<?php
$pageTitle = $t('domain.pref_spampolicy');
$thresholds = [
    'spamTagLevel' => ['spampolicy.tag_level', 'e.g. 2.0'],
    'spamTag2Level' => ['spampolicy.tag2_level', 'e.g. 6.2'],
    'spamKillLevel' => ['spampolicy.kill_level', 'e.g. 6.9'],
];
$switches = [
    'bypassVirusChecks' => 'spampolicy.bypass_virus',
    'bypassSpamChecks' => 'spampolicy.bypass_spam',
    'virusLover' => 'spampolicy.deliver_virus',
    'spamLover' => 'spampolicy.deliver_spam',
    'bannedFilesLover' => 'spampolicy.deliver_banned',
    'badHeaderLover' => 'spampolicy.deliver_bad_header',
];
?>
<div class="page-header">
  <div>
    <h1><?= $te('domain.pref_spampolicy') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <?php if ($policy === null): ?>
    <div class="alert alert-info"><?= $te('self.spam_policy_default') ?></div>
    <?php endif; ?>
    <form method="post">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="save" />

      <div class="card">
        <div class="card-header"><?= $te('spampolicy.thresholds') ?></div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <?php foreach ($thresholds as $field => [$labelKey, $placeholder]): ?>
            <div class="col-md-4">
              <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
              <input id="<?= $field ?>" type="number" step="0.1" name="<?= $field ?>" class="form-control" value="<?= $e($policy->$field ?? '') ?>" placeholder="<?= $e($placeholder) ?>" />
            </div>
            <?php endforeach; ?>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="spamSubjectTag" class="form-label"><?= $te('spampolicy.subject_tag') ?></label><?= $help('spampolicy.subject_tag') ?>
              <input id="spamSubjectTag" type="text" name="spamSubjectTag" class="form-control" value="<?= $e($policy->spamSubjectTag ?? '') ?>" placeholder="e.g. [SPAM?]" />
            </div>
            <div class="col-md-6">
              <label for="spamSubjectTag2" class="form-label"><?= $te('spampolicy.subject_tag2') ?></label><?= $help('spampolicy.subject_tag2') ?>
              <input id="spamSubjectTag2" type="text" name="spamSubjectTag2" class="form-control" value="<?= $e($policy->spamSubjectTag2 ?? '') ?>" placeholder="e.g. [SPAM]" />
            </div>
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
                <label class="form-check-label" for="<?= $field ?>"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><?= $te('spampolicy.save_policy') ?></button>
        <?php if ($policy !== null): ?>
        <button type="submit" name="action" value="delete" class="btn btn-outline-danger" data-confirm="<?= $te('self.spam_policy_reset_confirm') ?>"><i class="bi bi-arrow-counterclockwise me-1"></i><?= $te('self.spam_policy_reset') ?></button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
