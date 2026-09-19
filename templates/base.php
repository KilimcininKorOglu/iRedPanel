<?php
$loggedIn = !empty($session['email']);
$brandName = $brand['name'] ?? 'iRedPanel';
$logoUrl = $brand['logoUrl'] ?? '/static/logo-iredmail.png';
$homeHref = !empty($session['selfService']) ? '/self' : '/dashboard';
// Data for app.js. The HEX flags keep "</script>" and quotes inert inside the script block.
$appData = json_encode([
    'flash' => ['success' => $flashSuccess, 'error' => $flashError],
    'passwordPolicy' => json_decode($passwordPolicy ?? '{}', true),
    'i18n' => [
        'confirmTitle' => $t('common.confirm_title'),
        'confirm' => $t('common.confirm'),
        'cancel' => $t('common.cancel'),
        'pickerPlaceholder' => $t('common.picker_placeholder'),
        'pickerNoResults' => $t('common.picker_no_results'),
        'pickerLoadFailed' => $t('common.picker_load_failed'),
        'pickerAdd' => $t('common.add'),
        'pickerRemove' => $t('common.remove'),
        'types' => [
            'user' => $t('common.type_user'),
            'alias' => $t('common.type_alias'),
            'ml' => $t('common.type_ml'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="<?= $e(str_replace('_', '-', $currentLocale)) ?>" data-bs-theme="dark">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $e($brandName) ?> - <?= $e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $asset('/static/vendor/bootstrap/bootstrap.min.css') ?>" />
    <link rel="stylesheet" href="<?= $asset('/static/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" />
    <link rel="stylesheet" href="<?= $asset('/static/vendor/tom-select/tom-select.bootstrap5.min.css') ?>" />
    <link rel="stylesheet" href="<?= $asset('/static/styles.css') ?>" />
    <?php if (!empty($brand['primaryColor'])): ?>
    <style>:root { --app-accent: <?= $e($brand['primaryColor']) ?>; }</style>
    <?php endif; ?>
  </head>
  <body class="<?= $loggedIn ? 'app-shell' : 'app-guest' ?>">
    <?php if ($loggedIn): ?>
    <header class="app-topbar d-lg-none">
      <button class="btn btn-icon" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="<?= $te('nav.toggle_menu') ?>">
        <i class="bi bi-list"></i>
      </button>
      <a class="app-brand" href="<?= $homeHref ?>"><img src="<?= $e($logoUrl) ?>" alt="" /> <?= $e($brandName) ?></a>
    </header>

    <aside class="app-sidebar offcanvas-lg offcanvas-start" id="appSidebar" tabindex="-1" aria-label="<?= $te('nav.toggle_menu') ?>">
      <div class="sidebar-brand">
        <a class="app-brand" href="<?= $homeHref ?>"><img src="<?= $e($logoUrl) ?>" alt="" /> <?= $e($brandName) ?></a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="<?= $te('common.close') ?>"></button>
      </div>
      <nav class="sidebar-nav">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
        <div class="sidebar-group"><?= $te($groupLabel) ?></div>
        <?php foreach ($items as $item): ?>
        <a href="<?= $e($item['href']) ?>" class="sidebar-link<?= $item['href'] === $navActive ? ' active' : '' ?>"<?= $item['href'] === $navActive ? ' aria-current="page"' : '' ?>>
          <i class="bi bi-<?= $e($item['icon']) ?>"></i><span><?= $te($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <?php if (count($availableLocales) > 1): ?>
        <form method="post" action="/language" title="<?= $te('language.switch') ?>">
          <?= $csrfField ?>
          <select name="locale" class="form-select form-select-sm" data-autosubmit aria-label="<?= $te('language.switch') ?>">
            <?php foreach ($availableLocales as $code => $name): ?>
            <option value="<?= $e($code) ?>"<?= $code === $currentLocale ? ' selected' : '' ?>><?= $e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <div class="sidebar-user">
          <span class="sidebar-avatar"><i class="bi bi-person"></i></span>
          <span class="sidebar-email" title="<?= $e($session['email']) ?>"><?= $e($session['email']) ?></span>
          <form method="post" action="/logout">
            <?= $csrfField ?>
            <button type="submit" class="btn btn-icon" title="<?= $te('nav.logout') ?>" aria-label="<?= $te('nav.logout') ?>"><i class="bi bi-box-arrow-right"></i></button>
          </form>
        </div>
      </div>
    </aside>
    <?php else: ?>
    <?php if (count($availableLocales) > 1): ?>
    <form method="post" action="/language" class="guest-language" title="<?= $te('language.switch') ?>">
      <?= $csrfField ?>
      <select name="locale" class="form-select form-select-sm" data-autosubmit aria-label="<?= $te('language.switch') ?>">
        <?php foreach ($availableLocales as $code => $name): ?>
        <option value="<?= $e($code) ?>"<?= $code === $currentLocale ? ' selected' : '' ?>><?= $e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <main class="app-main">
      <?php if ($flashError !== null || $flashSuccess !== null): ?>
      <noscript>
        <?php if ($flashError !== null): ?>
        <div class="alert alert-danger"><?= $e($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess !== null): ?>
        <div class="alert alert-success"><?= $e($flashSuccess) ?></div>
        <?php endif; ?>
      </noscript>
      <?php endif; ?>
      <?= $bodyContent ?>
      <footer class="app-footer">
        <?php if (!empty($brand['footerText'])): ?>
        <span><?= $e($brand['footerText']) ?></span> &middot;
        <?php endif; ?>
        <a href="https://github.com/KilimcininKorOglu/iRedPanel" target="_blank" rel="noopener"><?= $e($brandName) ?> v<?= $e(defined('APP_VERSION') ? APP_VERSION : '0.0.0') ?></a>
      </footer>
    </main>

    <script type="application/json" id="app-data"><?= $appData ?></script>
    <script src="<?= $asset('/static/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= $asset('/static/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
    <script src="<?= $asset('/static/vendor/tom-select/tom-select.complete.min.js') ?>"></script>
    <script src="<?= $asset('/static/app.js') ?>"></script>
  </body>
</html>
