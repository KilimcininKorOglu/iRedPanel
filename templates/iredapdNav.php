<?php
// Tabs shared by the iRedAPD pages. The including template sets $iredapdTab.
$iredapdTabs = [
    'throttle' => ['/iredapd/throttle/@.', 'throttle.view_title'],
    'greylist' => ['/iredapd/greylist/@.', 'greylist.view_title'],
    'domains' => ['/iredapd/greylist-domains', 'greylist.domains_title'],
    'tracking' => ['/iredapd/greylist-tracking', 'greylist.tracking_title'],
    'rdns' => ['/iredapd/wblist-rdns', 'wblist.rdns_title'],
    'senderscore' => ['/iredapd/wblist-senderscore', 'wblist.senderscore_title'],
];
?>
<ul class="nav nav-tabs">
  <?php foreach ($iredapdTabs as $tab => [$href, $label]): ?>
  <li class="nav-item"><a class="nav-link<?= ($iredapdTab ?? '') === $tab ? ' active' : '' ?>" href="<?= $e($href) ?>"<?= ($iredapdTab ?? '') === $tab ? ' aria-current="page"' : '' ?>><?= $te($label) ?></a></li>
  <?php endforeach; ?>
</ul>
