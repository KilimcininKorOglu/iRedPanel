<?php $pageTitle = $t('domain.create'); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $te('domain.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('common.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('domain.create') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="domainName" class="form-label"><?= $te('domain.domain_name') ?></label><?= $help('domain.domain_name') ?>
          <input id="domainName" type="text" name="domainName" required placeholder="example.com"
            class="form-control<?= !empty($validationErrors['domainName']) ? ' is-invalid' : '' ?>"
            value="<?= $e($domain?->domainName ?? '') ?>" />
          <?php if (!empty($validationErrors['domainName'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['domainName']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="description" class="form-label"><?= $te('common.description') ?></label><?= $help('common.description') ?>
          <input id="description" type="text" name="description" class="form-control" value="<?= $e($domain?->description ?? '') ?>" />
        </div>

        <?php /* The domain limits are on the General tab, which only a global admin edits. */ ?>
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="maxQuota" class="form-label"><?= $te('domain.max_quota') ?></label><?= $help('domain.max_quota') ?>
            <input id="maxQuota" type="number" name="maxQuota" min="0" class="form-control" value="<?= $e($domain?->maxQuota ?? 0) ?>" />
          </div>
          <div class="col-md-6">
            <label for="mailboxes" class="form-label"><?= $te('domain.max_mailboxes') ?></label><?= $help('domain.max_mailboxes') ?>
            <input id="mailboxes" type="number" name="mailboxes" min="0" class="form-control" value="<?= $e($domain?->mailboxes ?? 0) ?>" />
          </div>
        </div>
        <?php endif; ?>

        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?php if ($domain === null || $domain->active): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label><?= $help('common.active') ?>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('domain.create') ?></button>
        <a href="/domains" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
