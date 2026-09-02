<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$section = static function (array $group): string {
    $h = '<tr class="grp-row"><td colspan="2">' . esc($group['label']) . '</td></tr>';
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
    $h .= '<tr class="subtotal"><td>Total</td><td class="right mono">' . money($group['total']) . '</td></tr>';

    return $h;
};
?>

<div class="page-head"><h1>Income Statement</h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1>Laporan Laba Rugi / Income Statement</h1>
  <div class="muted"><?= date_id($from) ?> — <?= date_id($to) ?></div>
</div>

<div class="card" style="max-width:720px">
  <table class="grid tight">
    <tbody>
      <?= $section($data['groups']['revenue']) ?>
      <?= $section($data['groups']['cogs']) ?>
      <tr class="subtotal" style="background:#eef4ff"><td>LABA KOTOR / GROSS PROFIT</td><td class="right mono"><?= money($data['gross_profit']) ?></td></tr>
      <?= $section($data['groups']['expense']) ?>
      <tr class="subtotal" style="background:#eef4ff"><td>LABA USAHA / OPERATING PROFIT</td><td class="right mono"><?= money($data['operating']) ?></td></tr>
      <?= $section($data['groups']['other_income']) ?>
      <?= $section($data['groups']['other_expense']) ?>
    </tbody>
    <tfoot>
      <tr><td>LABA (RUGI) BERSIH / NET INCOME</td><td class="right mono"><?= money($data['net_income']) ?></td></tr>
    </tfoot>
  </table>
</div>

<?= $this->endSection() ?>
