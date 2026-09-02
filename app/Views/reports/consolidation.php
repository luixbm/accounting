<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$tb = $data['tb'];
$pl = $data['pl'];
$bs = $data['bs'];

$plSection = static function (array $g): string {
    if (! $g['rows']) {
        return '';
    }
    $h = '<tr class="grp-row"><td colspan="2">' . esc($g['label']) . '</td></tr>';
    foreach ($g['rows'] as $r) {
        $h .= '<tr><td><span class="mono small">' . esc($r['code']) . '</span> ' . esc($r['name']) . '</td>'
        . '<td class="right mono">' . money($r['amount']) . '</td></tr>';
    }

    return $h . '<tr class="subtotal"><td>Total</td><td class="right mono">' . money($g['total']) . '</td></tr>';
};
$bsSection = static function (array $g): string {
    $h = '<tr class="grp-row"><td colspan="2">' . esc($g['label']) . '</td></tr>';
    foreach ($g['rows'] as $r) {
        $lbl = ($r['code'] !== '' ? '<span class="mono small">' . esc($r['code']) . '</span> ' : '') . esc($r['name']);
        $h .= '<tr><td>' . $lbl . '</td><td class="right mono">' . money($r['amount']) . '</td></tr>';
    }

    return $h . '<tr class="subtotal"><td>Total</td><td class="right mono">' . money($g['total']) . '</td></tr>';
};
?>

<?php
$coPicker = '<div class="field" style="min-width:220px"><label>Companies</label><div style="display:flex;flex-wrap:wrap;gap:8px">';
foreach ($companies as $c) {
    $coPicker .= '<label class="inline" style="font-weight:400;font-size:13px"><input type="checkbox" name="c[]" value="'
        . $c['id'] . '" style="width:auto"' . (in_array((int) $c['id'], $picked, true) ? ' checked' : '') . '> ' . esc($c['code']) . '</label>';
}
$coPicker .= '</div></div>';
?>
<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true, 'extra' => $coPicker]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?> — Group</div>
  <h1><?= esc($title) ?></h1>
  <div class="muted">
    <?= esc(implode(' + ', array_map(static fn ($c) => $c['code'], array_filter($companies, static fn ($c) => in_array((int) $c['id'], $picked, true))))) ?>
    &middot; <?= date_id($from) ?> – <?= date_id($to) ?>
  </div>
  <div class="small muted">Straight sum across companies — no inter-company eliminations yet.</div>
</div>

<div class="row">
  <div class="card" style="flex:1;min-width:340px">
    <h2>Laba Rugi / Income Statement</h2>
    <table class="grid tight">
      <tbody>
        <?= $plSection($pl['groups']['revenue']) ?>
        <?= $plSection($pl['groups']['cogs']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td>LABA KOTOR</td><td class="right mono"><?= money($pl['gross_profit']) ?></td></tr>
        <?= $plSection($pl['groups']['expense']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td>LABA USAHA</td><td class="right mono"><?= money($pl['operating']) ?></td></tr>
        <?= $plSection($pl['groups']['other_income']) ?>
        <?= $plSection($pl['groups']['other_expense']) ?>
      </tbody>
      <tfoot><tr><td>LABA (RUGI) BERSIH</td><td class="right mono"><?= money($pl['net_income']) ?></td></tr></tfoot>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:340px">
    <h2>Neraca / Balance Sheet <span class="muted small">per <?= date_id($asOf) ?></span></h2>
    <table class="grid tight">
      <tbody>
        <?= $bsSection($bs['groups']['asset']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td>TOTAL AKTIVA</td><td class="right mono"><?= money($bs['assets']) ?></td></tr>
        <?= $bsSection($bs['groups']['liability']) ?>
        <?= $bsSection($bs['groups']['equity']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td>TOTAL KEWAJIBAN &amp; EKUITAS</td><td class="right mono"><?= money($bs['liab_equity']) ?></td></tr>
      </tbody>
    </table>
    <p class="small <?= $bs['balanced'] ? 'muted' : '' ?>" style="<?= $bs['balanced'] ? '' : 'color:var(--red);font-weight:700' ?>">
      <?= $bs['balanced'] ? 'Balanced.' : 'Out of balance by ' . money($bs['assets'] - $bs['liab_equity']) . ' — check each company individually.' ?>
    </p>
  </div>
</div>

<div class="card">
  <h2>Neraca Saldo / Trial Balance</h2>
  <table class="grid tight mono">
    <thead>
      <tr><th>Code</th><th style="font-family:sans-serif">Account</th>
        <th class="right">Open D</th><th class="right">Open C</th>
        <th class="right">Mv D</th><th class="right">Mv C</th>
        <th class="right">End D</th><th class="right">End C</th></tr>
    </thead>
    <tbody>
      <?php foreach ($tb['rows'] as $r): ?>
        <tr>
          <td><?= esc($r['code']) ?></td>
          <td style="font-family:sans-serif"><?= esc($r['name']) ?></td>
          <td class="right"><?= money($r['open_d'], 2, true) ?></td>
          <td class="right"><?= money($r['open_c'], 2, true) ?></td>
          <td class="right"><?= money($r['mv_d'], 2, true) ?></td>
          <td class="right"><?= money($r['mv_c'], 2, true) ?></td>
          <td class="right"><?= money($r['end_d'], 2, true) ?></td>
          <td class="right"><?= money($r['end_c'], 2, true) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $tb['rows']): ?><tr><td colspan="8" class="muted" style="font-family:sans-serif">No activity for the selected companies / range.</td></tr><?php endif ?>
    </tbody>
    <tfoot>
      <tr><td colspan="2">TOTAL</td>
        <td class="right"><?= money($tb['totals']['open_d']) ?></td>
        <td class="right"><?= money($tb['totals']['open_c']) ?></td>
        <td class="right"><?= money($tb['totals']['mv_d']) ?></td>
        <td class="right"><?= money($tb['totals']['mv_c']) ?></td>
        <td class="right"><?= money($tb['totals']['end_d']) ?></td>
        <td class="right"><?= money($tb['totals']['end_c']) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?= $this->endSection() ?>
