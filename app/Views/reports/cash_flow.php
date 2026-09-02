<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$section = static function (array $g): string {
    $h = '<tr class="grp-row"><td colspan="2">' . esc($g['label']) . '</td></tr>';
    if (! $g['rows']) {
        $h .= '<tr><td class="muted" colspan="2">—</td></tr>';
    }
    foreach ($g['rows'] as $r) {
        $h .= '<tr><td><span class="mono small">' . esc($r['code']) . '</span> ' . esc($r['name']) . '</td>'
            . '<td class="right mono">' . money($r['amount']) . '</td></tr>';
    }

    return $h . '<tr class="subtotal"><td>Total</td><td class="right mono">' . money($g['total']) . '</td></tr>';
};
?>

<div class="page-head"><h1>Cash Flow</h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true]) ?>

<div class="report-title">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_name()) ?></div>
  <h1>Laporan Arus Kas / Cash Flow Statement</h1>
  <div class="muted"><?= date_id($f['from']) ?> — <?= date_id($f['to']) ?> · direct method</div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label">Cash — beginning</div><div class="k-value mono"><?= rupiah($data['opening'], false, 0) ?></div></div>
  <div class="kpi <?= $data['operating'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label">Operating</div><div class="k-value mono"><?= rupiah($data['operating'], false, 0) ?></div></div>
  <div class="kpi <?= $data['investing'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label">Investing</div><div class="k-value mono"><?= rupiah($data['investing'], false, 0) ?></div></div>
  <div class="kpi <?= $data['financing'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label">Financing</div><div class="k-value mono"><?= rupiah($data['financing'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label">Cash — end</div><div class="k-value mono"><?= rupiah($data['closing'], false, 0) ?></div></div>
</div>

<div class="card" style="max-width:720px">
  <table class="grid tight">
    <tbody>
      <?= $section($data['groups']['operating']) ?>
      <?= $section($data['groups']['investing']) ?>
      <?= $section($data['groups']['financing']) ?>
      <tr class="subtotal" style="background:var(--brand-soft)"><td>NET CHANGE IN CASH</td><td class="right mono"><?= money($data['net_change']) ?></td></tr>
      <tr><td>Cash at beginning of period</td><td class="right mono"><?= money($data['opening']) ?></td></tr>
    </tbody>
    <tfoot>
      <tr><td>CASH AT END OF PERIOD</td><td class="right mono"><?= money($data['closing']) ?></td></tr>
    </tfoot>
  </table>
  <p class="small <?= $data['reconciles'] ? 'muted' : '' ?>" style="<?= $data['reconciles'] ? '' : 'color:var(--red);font-weight:700' ?>">
    <?= $data['reconciles']
        ? 'Beginning + net change agrees with the ending cash balance.'
        : 'Reconciliation off by ' . money(($data['opening'] + $data['net_change']) - $data['closing']) . ' — check for cash lines with no counterpart.' ?>
  </p>
</div>

<?= $this->endSection() ?>
