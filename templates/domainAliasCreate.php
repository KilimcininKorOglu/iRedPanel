<?php $pageTitle = $t('domainalias.create'); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domain-aliases"><?= $te('domainalias.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('common.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('domainalias.create') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-7">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="aliasDomain" class="form-label"><?= $te('domainalias.alias_domain_name') ?></label><?= $help('domainalias.alias_domain_name') ?>
          <input id="aliasDomain" type="text" name="aliasDomain" required placeholder="alias.example.com"
            class="form-control<?= !empty($validationErrors['aliasDomain']) ? ' is-invalid' : '' ?>"
            value="<?= $e($alias?->aliasDomain ?? '') ?>" />
          <?php if (!empty($validationErrors['aliasDomain'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['aliasDomain']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="targetDomain" class="form-label"><?= $te('domainalias.target_domain') ?></label><?= $help('domainalias.target_domain') ?>
          <select id="targetDomain" name="targetDomain" required class="form-select<?= !empty($validationErrors['targetDomain']) ? ' is-invalid' : '' ?>">
            <option value=""><?= $te('domainalias.select_target') ?></option>
            <?php foreach ($allDomains as $d): ?>
            <option value="<?= $e($d['domainName']) ?>"<?php if (($alias?->targetDomain ?? '') === $d['domainName']): ?> selected<?php endif; ?>><?= $e($d['domainName']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($validationErrors['targetDomain'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['targetDomain']) ?></div>
          <?php endif; ?>
        </div>

        <?php if ($supportsStatus): ?>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?php if ($alias === null || ($alias->active ?? true)): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label><?= $help('common.active') ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('domainalias.create') ?></button>
        <a href="/domain-aliases" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
