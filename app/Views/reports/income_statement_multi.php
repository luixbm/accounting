<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$cols = $data['columns'];
$nc   = count($cols);

$cells = static function (array $amounts): string {
    $h = '';
    foreach ($amounts as $v) {
        $h .= '<td class="right mono">' . money($v, 2, true) . '</td>';
    }
    $h .= '<td class="right mono col-tot">' . money(array_sum($amounts), 2, true) . '</td>';

    return $h;
};

$section = static function (array $group) use ($cells): string {
    $span = count($group['totals']) + 2;
    $h    = '<tr class="grp-row"><td colspan="' . $span . '">' . esc($group['label']) . '</td></tr>';
    foreach ($group['blocks'] as $blk) {
        $named = $blk['code'] !== '';
        if ($named) {
            $h .= '<tr class="hdr-row"><td colspan="' . $span . '"><span class="mono small">' . esc($blk['code']) . '</span> ' . esc($blk['name']) . '</td></tr>';
        }
        foreach ($blk['rows'] as $r) {
            $lbl = ($r['code'] !== '' ? '<span class="mono small">' . esc($r['code']) . '</span> ' : '') . esc($r['name']);
            $h  .= '<tr><td' . ($named ? ' style="padding-left:28px"' : '') . '>' . $lbl . '</td>' . $cells($r['amounts']) . '</tr>';
        }
        if ($named) {
            $h .= '<tr class="sub-row"><td class="right">Subtotal — ' . esc($blk['name']) . '</td>' . $cells($blk['subtotals']) . '</tr>';
        }
    }
    $h .= '<tr class="subtotal"><td>Total</td>' . $cells($group['totals']) . '</tr>';

    return $h;
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= date_id($from) ?> — <?= date_id($to) ?> · per <?= esc($compare) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr>
          <th style="min-width:220px">Account</th>
          <?php foreach ($cols as $c): ?><th class="right nowrap"><?= esc($c) ?></th><?php endforeach ?>
          <th class="right nowrap col-tot">Total</th>
        </tr>
      </thead>
      <tbody>
        <?= $section($data['groups']['revenue']) ?>
        <?= $section($data['groups']['cogs']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td>LABA KOTOR / GROSS PROFIT</td><?= $cells($data['subtotals']['gross_profit']) ?></tr>
        <?= $section($data['groups']['expense']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td>LABA USAHA / OPERATING PROFIT</td><?= $cells($data['subtotals']['operating']) ?></tr>
        <?= $section($data['groups']['other_income']) ?>
        <?= $section($data['groups']['other_expense']) ?>
      </tbody>
      <tfoot>
        <tr><td>LABA (RUGI) BERSIH / NET INCOME</td><?= $cells($data['subtotals']['net_income']) ?></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($nc === 0): ?><p class="muted">The date range produced no periods — widen From/To.</p><?php endif ?>

<?= $this->endSection() ?>
