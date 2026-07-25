<?php
$pageTitle = $t('panelset.title');
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>
<div class="container">
  <div class="row">
    <div class="col-10">
      <h1><?= $te('panelset.title') ?></h1>

      <?php if ($flashSuccess !== ''): ?>
      <p class="text-success"><?= $e($flashSuccess) ?></p>
      <?php endif; ?>

      <nav class="tabs">
        <?php foreach ($categoryTitles as $catKey => $catTitle): ?>
        <a <?php if ($activeTab === $catKey): ?>class="active"<?php endif; ?>
           href="/panel-settings?tab=<?= $e($catKey) ?>"><?= $e($catTitle) ?></a>
        <?php endforeach; ?>
      </nav>

      <?php
      $currentKeys = $categories[$activeTab] ?? [];
      ?>

      <form method="POST" action="/panel-settings">
        <?= $csrfField ?>
        <input type="hidden" name="category" value="<?= $e($activeTab) ?>">

        <table class="striped">
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
            ?>
            <tr>
              <td><label for="field-<?= $e($key) ?>"><?= $e($label) ?></label></td>
              <td>
                <?php if ($type === 'bool'): ?>
                  <label>
                    <input type="checkbox" name="<?= $e($key) ?>" id="field-<?= $e($key) ?>"
                           value="1" <?= $currentValue ? 'checked' : '' ?>>
                    <?= $te('common.enabled') ?>
                  </label>
                <?php elseif ($key === 'passwordDefaultScheme'): ?>
                  <select name="<?= $e($key) ?>" id="field-<?= $e($key) ?>">
                    <?php foreach ($allowedSchemes as $scheme): ?>
                    <option value="<?= $e($scheme) ?>" <?= $currentValue === $scheme ? 'selected' : '' ?>><?= $e($scheme) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($key === 'defaultLanguage'): ?>
                  <select name="<?= $e($key) ?>" id="field-<?= $e($key) ?>">
                    <?php foreach ($availableLocales as $code => $name): ?>
                    <option value="<?= $e($code) ?>" <?= $currentValue === $code ? 'selected' : '' ?>><?= $e($name) ?> (<?= $e($code) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'int'): ?>
                  <input type="number" name="<?= $e($key) ?>" id="field-<?= $e($key) ?>"
                         value="<?= $e((string) $currentValue) ?>" min="0" class="col-4">
                <?php else: ?>
                  <input type="text" name="<?= $e($key) ?>" id="field-<?= $e($key) ?>"
                         value="<?= $e((string) $currentValue) ?>">
                <?php endif; ?>
              </td>
              <td>
                <span class="tag <?= $isFromDb ? 'bg-success' : 'bg-light' ?>"><?= $e($source) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="row" style="margin-top:1rem">
          <div class="col">
            <button type="submit" class="button primary"><?= $te('panelset.save_category', ['category' => $categoryTitles[$activeTab] ?? '']) ?></button>
          </div>
        </div>
      </form>

      <p class="text-light" style="margin-top:2rem">
        <?= $t('panelset.footer_note') ?>
      </p>
    </div>
  </div>
</div>
