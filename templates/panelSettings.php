<?php
$pageTitle = $t('panelset.title');
$currentKeys = $categories[$activeTab] ?? [];
?>
<div class="page-header">
  <h1><?= $te('panelset.title') ?></h1>
</div>

<ul class="nav nav-tabs">
  <?php foreach ($categoryTitles as $catKey => $catTitle): ?>
  <li class="nav-item"><a class="nav-link<?= $activeTab === $catKey ? ' active' : '' ?>" href="/panel-settings?tab=<?= $e($catKey) ?>"<?= $activeTab === $catKey ? ' aria-current="page"' : '' ?>><?= $e($catTitle) ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="row">
  <div class="col-xxl-10">
    <form method="POST" action="/panel-settings" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="category" value="<?= $e($activeTab) ?>">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th style="width:40%"><?= $te('sysset.setting') ?></th>
              <th><?= $te('sysset.value') ?></th>
              <th style="width:15%"><?= $te('panelset.source') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($currentKeys as $key):
              $type = $overridableKeys[$key] ?? 'string';
              $label = $labels[$key] ?? $key;
              $currentValue = $settings->$key;
              $isFromDb = isset($dbSettings[$key]);
              $source = $isFromDb ? $t('panelset.source_database') : '.env';
              $fieldId = 'field-' . $e($key);
            ?>
            <tr>
              <td><label for="<?= $fieldId ?>"><?= $e($label) ?></label></td>
              <td>
                <?php if ($type === 'bool'): ?>
                <div class="form-check form-switch mb-0">
                  <input type="checkbox" class="form-check-input" name="<?= $e($key) ?>" id="<?= $fieldId ?>" value="1" <?= $currentValue ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= $fieldId ?>"><?= $te('common.enabled') ?></label>
                </div>
                <?php elseif ($key === 'passwordDefaultScheme'): ?>
                <select name="<?= $e($key) ?>" id="<?= $fieldId ?>" class="form-select form-select-sm">
                  <?php foreach ($allowedSchemes as $scheme): ?>
                  <option value="<?= $e($scheme) ?>"<?= $currentValue === $scheme ? ' selected' : '' ?>><?= $e($scheme) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php elseif ($key === 'defaultLanguage'): ?>
                <select name="<?= $e($key) ?>" id="<?= $fieldId ?>" class="form-select form-select-sm">
                  <?php foreach ($availableLocales as $code => $name): ?>
                  <option value="<?= $e($code) ?>"<?= $currentValue === $code ? ' selected' : '' ?>><?= $e($name) ?> (<?= $e($code) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <?php elseif ($type === 'int'): ?>
                <input type="number" name="<?= $e($key) ?>" id="<?= $fieldId ?>" class="form-control form-control-sm" style="max-width: 12rem" value="<?= $e((string) $currentValue) ?>" min="0">
                <?php else: ?>
                <input type="text" name="<?= $e($key) ?>" id="<?= $fieldId ?>" class="form-control form-control-sm" value="<?= $e((string) $currentValue) ?>">
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $isFromDb ? 'text-bg-success' : 'text-bg-secondary' ?> badge-status"><?= $e($source) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('panelset.save_category', ['category' => $categoryTitles[$activeTab] ?? '']) ?></button>
      </div>
    </form>

    <p class="small text-body-secondary"><?= $t('panelset.footer_note') ?></p>
  </div>
</div>
