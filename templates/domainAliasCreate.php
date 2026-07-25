<?php $pageTitle = $t('domainalias.create'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('domainalias.create') ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/domain-aliases"><?= $te('domainalias.list_title') ?></a> /
          <span class="text-light"><?= $te('common.create') ?></span>
        </div>
      </div>

      <?php if (!empty($error)): ?>
      <p class="text-error"><?= $e($error) ?></p>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="aliasDomain"><?= $te('domainalias.alias_domain_name') ?></label>
          <input id="aliasDomain" type="text" name="aliasDomain" required placeholder="alias.example.com"
            <?php if (!empty($validationErrors['aliasDomain'])): ?>class="error"<?php endif; ?>
            value="<?= $e($alias?->aliasDomain ?? '') ?>"
          />
          <?php if (!empty($validationErrors['aliasDomain'])): ?>
          <span class="text-error"><?= $e($validationErrors['aliasDomain']) ?></span>
          <?php endif; ?>
        </p>

        <p>
          <label for="targetDomain"><?= $te('domainalias.target_domain') ?></label>
          <select id="targetDomain" name="targetDomain" required
            <?php if (!empty($validationErrors['targetDomain'])): ?>class="error"<?php endif; ?>
          >
            <option value=""><?= $te('domainalias.select_target') ?></option>
            <?php foreach ($allDomains as $d): ?>
            <option value="<?= $e($d['domainName']) ?>"
              <?php if (($alias?->targetDomain ?? '') === $d['domainName']): ?>selected<?php endif; ?>
            ><?= $e($d['domainName']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($validationErrors['targetDomain'])): ?>
          <span class="text-error"><?= $e($validationErrors['targetDomain']) ?></span>
          <?php endif; ?>
        </p>

        <p>
          <label>
            <input type="checkbox" name="active" <?php if ($alias === null || ($alias->active ?? true)): ?>checked<?php endif; ?> />
            <?= $te('common.active') ?>
          </label>
        </p>

        <button type="submit" class="button primary"><?= $te('domainalias.create') ?></button>
        <a href="/domain-aliases" class="button outline"><?= $te('common.cancel') ?></a>
      </form>
    </div>
  </div>
</div>
