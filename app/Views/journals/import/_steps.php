<?php
/** @var string $active  one of: map, accounts, preview */
/** @var array $batch */
$steps = [
    'map'      => [lang('Import.step_map'), "journals/import/{$batch['id']}/map"],
    'accounts' => [lang('Import.step_accounts'), "journals/import/{$batch['id']}/accounts"],
    'preview'  => [lang('Import.step_preview'), "journals/import/{$batch['id']}/preview"],
];
?>
<div class="pill-nav no-print" style="margin-bottom:18px">
  <?php $i = 1;
  foreach ($steps as $k => [$label, $url]): ?>
    <a class="<?= $active === $k ? 'active' : '' ?>" href="<?= site_url($url) ?>"><?= $i++ ?>. <?= esc($label) ?></a>
  <?php endforeach ?>
</div>
