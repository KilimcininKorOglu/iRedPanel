<?php
// The tab row of every domain page. The including template sets $mode to its own
// page name and passes $domain and $openPages through.
$domainPath = '/domains/' . $e($domain->domainName);
$tabs = [
    'general' => [$domainPath . '/edit', $t('admin.tab_general')],
    'settings' => [$domainPath . '/settings', $t('common.settings')],
    'catchall' => [$domainPath . '/catchall', $t('domain.tab_catchall')],
    'bcc' => [$domainPath . '/bcc', $t('domain.tab_bcc')],
    'relay' => [$domainPath . '/relay', $t('domain.tab_relay')],
    'admins' => [$domainPath . '/admins', $t('domain.tab_admins')],
    'dns' => [$domainPath . '/dns', $t('dns.tab')],
];
// A domain admin sees only the pages that the global admin left open.
$tabs = array_intersect_key($tabs, array_flip($openPages));
?>
<ul class="nav nav-tabs">
  <?php foreach ($tabs as $key => [$href, $label]): ?>
  <li class="nav-item"><a class="nav-link<?= $mode === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $mode === $key ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a></li>
  <?php endforeach; ?>
  <?php if (!empty($features['iredapd']) && !empty($session['isGlobalAdmin'])): ?>
  <li class="nav-item"><a class="nav-link" href="/iredapd/throttle/@<?= $e($domain->domainName) ?>"><?= $te('throttle.view_title') ?></a></li>
  <li class="nav-item"><a class="nav-link" href="/iredapd/greylist/@<?= $e($domain->domainName) ?>"><?= $te('greylist.view_title') ?></a></li>
  <?php endif; ?>
</ul>
