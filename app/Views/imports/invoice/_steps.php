<?php
/** @var string $active  @var array $batch  @var string $base */
$steps = [
    'map'      => [lang('Import.step_map'), "{$base}/{$batch['id']}/map"],
    'accounts' => [lang('Import.step_accounts'), "{$base}/{$batch['id']}/accounts"],
    'preview'  => [lang('Import.step_preview'), "{$base}/{$batch['id']}/preview"],
];
?>
<div class="pill-nav no-print" style="margin-bottom:18px">
  <?php $i = 1;
  foreach ($steps as $k => [$label, $url]): ?>
    <a class="<?= $active === $k ? 'active' : '' ?>" href="<?= site_url($url) ?>"><?= $i++ ?>. <?= esc($label) ?></a>
  <?php endforeach ?>
</div>
