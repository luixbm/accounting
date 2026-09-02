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
            $h .= '<tr class="sub-row"><td class="right">Subtotal — ' . esc($blk['name']) . '</td>'
                . '<td class="right mono">' . money($blk['subtotal']) . '</td></tr>';
        }
    }

    return $h;
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted">Per <?= date_id($asOf) ?></div>
</div>

<div class="row">
  <div class="card" style="flex:1;min-width:320px">
    <table class="grid tight">
      <thead><tr><th><?= esc($data['groups']['asset']['label']) ?></th><th class="right">Rp</th></tr></thead>
      <tbody><?= $block($data['groups']['asset']) ?></tbody>
      <tfoot><tr><td>TOTAL AKTIVA / TOTAL ASSETS</td><td class="right mono"><?= money($data['assets']) ?></td></tr></tfoot>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:320px">
    <table class="grid tight">
      <thead><tr><th><?= esc($data['groups']['liability']['label']) ?></th><th class="right">Rp</th></tr></thead>
      <tbody><?= $block($data['groups']['liability']) ?></tbody>
      <tr class="subtotal"><td>Total Kewajiban / Total Liabilities</td><td class="right mono"><?= money($data['liabilities']) ?></td></tr>
    </table>
    <table class="grid tight" style="margin-top:14px">
      <thead><tr><th><?= esc($data['groups']['equity']['label']) ?></th><th class="right">Rp</th></tr></thead>
      <tbody><?= $block($data['groups']['equity']) ?></tbody>
      <tr class="subtotal"><td>Total Ekuitas / Total Equity</td><td class="right mono"><?= money($data['equity']) ?></td></tr>
      <tfoot><tr><td>TOTAL KEWAJIBAN &amp; EKUITAS</td><td class="right mono"><?= money($data['liab_equity']) ?></td></tr></tfoot>
    </table>
  </div>
</div>

<div class="card">
  <?php if ($data['balanced']): ?>
    <span class="badge badge-green">Balanced</span> Assets = Liabilities + Equity = <?= rupiah($data['assets']) ?>
  <?php else: ?>
    <span class="badge badge-red">Out of balance</span>
    Assets <?= rupiah($data['assets']) ?> vs Liabilities + Equity <?= rupiah($data['liab_equity']) ?>
    (difference <?= rupiah($data['assets'] - $data['liab_equity']) ?>).
    Check for unbalanced or unposted opening balances.
  <?php endif ?>
</div>

<?= $this->endSection() ?>
