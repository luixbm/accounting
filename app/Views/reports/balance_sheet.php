<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$block = static function (array $group): string {
    $h = '';
    foreach ($group['blocks'] as $blk) {
        $named = $blk['code'] !== '';
        if ($named) {
            $h .= '<tr class="hdr-row"><td colspan="2"><span class="mono small">' . esc($blk['code']) . '</span> ' . esc($blk['name']) . '</td></tr>';
        }
        foreach ($blk['rows'] as $r) {
            $h .= '<tr><td' . ($named ? ' style="padding-left:28px"' : '') . '>'
                . ($r['code'] !== '' ? '<span class="mono small">' . esc($r['code']) . '</span> ' : '') . esc($r['name']) . '</td>'
                . '<td class="right mono">' . money($r['amount']) . '</td></tr>';
        }
        if ($named) {
            $h .= '<tr class="sub-row"><td class="right">' . lang('Report.v_subtotal_of', [esc($blk['name'])]) . '</td>'
                . '<td class="right mono">' . money($blk['subtotal']) . '</td></tr>';
        }
    }

    return $h;
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= lang('App.as_of', [date_id($asOf)]) ?></div>
</div>

<div class="row">
  <div class="card" style="flex:1;min-width:320px">
    <table class="grid tight">
      <thead><tr><th><?= esc($data['groups']['asset']['label']) ?></th><th class="right"><?= base_code() ?></th></tr></thead>
      <tbody><?= $block($data['groups']['asset']) ?></tbody>
      <tfoot><tr><td><?= lang('Report.v_total_assets') ?></td><td class="right mono"><?= money($data['assets']) ?></td></tr></tfoot>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:320px">
    <table class="grid tight">
      <thead><tr><th><?= esc($data['groups']['liability']['label']) ?></th><th class="right"><?= base_code() ?></th></tr></thead>
      <tbody><?= $block($data['groups']['liability']) ?></tbody>
      <tr class="subtotal"><td><?= lang('Report.v_total_liabilities') ?></td><td class="right mono"><?= money($data['liabilities']) ?></td></tr>
    </table>
    <table class="grid tight" style="margin-top:14px">
      <thead><tr><th><?= esc($data['groups']['equity']['label']) ?></th><th class="right"><?= base_code() ?></th></tr></thead>
      <tbody><?= $block($data['groups']['equity']) ?></tbody>
      <tr class="subtotal"><td><?= lang('Report.v_total_equity') ?></td><td class="right mono"><?= money($data['equity']) ?></td></tr>
      <tfoot><tr><td><?= lang('Report.v_total_liab_equity') ?></td><td class="right mono"><?= money($data['liab_equity']) ?></td></tr></tfoot>
    </table>
  </div>
</div>

<div class="card">
  <?php if ($data['balanced']): ?>
    <span class="badge badge-green"><?= lang('Report.v_balanced') ?></span> <?= lang('Report.v_balanced_eq', [rupiah($data['assets'])]) ?>
  <?php else: ?>
    <span class="badge badge-red"><?= lang('Report.v_out_of_balance') ?></span>
    <?= lang('Report.v_oob_detail', [rupiah($data['assets']), rupiah($data['liab_equity']), rupiah($data['assets'] - $data['liab_equity'])]) ?>
    <?= lang('Report.v_check_opening') ?>
  <?php endif ?>
</div>

<?= $this->endSection() ?>
