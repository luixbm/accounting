<?php
/** @var string $active  one of: map, accounts, preview */
/** @var array $batch */
$steps = [
    'map'      => ['Map columns', "journals/import/{$batch['id']}/map"],
    'accounts' => ['Match accounts', "journals/import/{$batch['id']}/accounts"],
    'preview'  => ['Preview & commit', "journals/import/{$batch['id']}/preview"],
];
?>
<div class="pill-nav no-print" style="margin-bottom:18px">
  <?php $i = 1;
  foreach ($steps as $k => [$label, $url]): ?>
    <a class="<?= $active === $k ? 'active' : '' ?>" href="<?= site_url($url) ?>"><?= $i++ ?>. <?= esc($label) ?></a>
  <?php endforeach ?>
</div>
