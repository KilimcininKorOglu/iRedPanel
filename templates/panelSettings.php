<?php
$pageTitle = 'Panel Settings';
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>
<div class="container">
  <div class="row">
    <div class="col-10">
      <h1>Panel Settings</h1>

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
              <th style="width:40%">Setting</th>
              <th>Value</th>
              <th style="width:15%">Source</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($currentKeys as $key):
              $type = $overridableKeys[$key] ?? 'string';
              $label = $labels[$key] ?? $key;
              $currentValue = $settings->$key;
              $isFromDb = isset($dbSettings[$key]);
              $source = $isFromDb ? 'Database' : '.env';
            ?>
            <tr>
              <td><label for="field-<?= $e($key) ?>"><?= $e($label) ?></label></td>
              <td>
                <?php if ($type === 'bool'): ?>
                  <label>
                    <input type="checkbox" name="<?= $e($key) ?>" id="field-<?= $e($key) ?>"
                           value="1" <?= $currentValue ? 'checked' : '' ?>>
                    Enabled
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
            <button type="submit" class="button primary">Save <?= $e($categoryTitles[$activeTab] ?? '') ?></button>
          </div>
        </div>
      </form>

      <p class="text-light" style="margin-top:2rem">
        Settings stored in the database override <code>.env</code> values.
        To revert a setting, delete its row from the <code>panel_settings</code> table.
      </p>
    </div>
  </div>
</div>
