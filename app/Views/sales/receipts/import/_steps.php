<?php
/** @var string $active  map | preview  @var array $batch */
$steps = [
    'map'     => ['Map columns', "sales/receipts/import/{$batch['id']}/map"],
    'preview' => ['Preview & commit', "sales/receipts/import/{$batch['id']}/preview"],
];
?>
<div class="pill-nav no-print" style="margin-bottom:18px">
  <?php $i = 1;
  foreach ($steps as $k => [$label, $url]): ?>
    <a class="<?= $active === $k ? 'active' : '' ?>" href="<?= site_url($url) ?>"><?= $i++ ?>. <?= esc($label) ?></a>
  <?php endforeach ?>
</div>
