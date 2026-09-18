<?php
// Tabs shared by the iRedAPD pages. The including template sets $iredapdTab.
$iredapdTabs = [
    'throttle' => ['/iredapd/throttle/@.', 'throttle.view_title'],
    'greylist' => ['/iredapd/greylist/@.', 'greylist.view_title'],
    'tracking' => ['/iredapd/greylist-tracking', 'greylist.tracking_title'],
    'rdns' => ['/iredapd/wblist-rdns', 'wblist.rdns_title'],
    'senderscore' => ['/iredapd/wblist-senderscore', 'wblist.senderscore_title'],
];
?>
<nav class="tabs">
  <?php foreach ($iredapdTabs as $tab => [$href, $label]): ?>
  <a <?php if (($iredapdTab ?? '') === $tab): ?>class="active"<?php endif; ?> href="<?= $e($href) ?>"><?= $te($label) ?></a>
  <?php endforeach; ?>
</nav>
