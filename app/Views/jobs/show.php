<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$section = static function (array $g): string {
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
?>

<div class="page-head">
  <div>
    <h1><?= esc($job['code']) ?> — <?= esc($job['name']) ?></h1>
    <div class="muted small">
      <?= esc(ucfirst($job['status'])) ?><?= $job['customer_name'] ? ' · ' . esc($job['customer_name']) : '' ?>
      <?= $job['start_date'] ? ' · ' . date_id($job['start_date']) : '' ?><?= $job['end_date'] ? ' – ' . date_id($job['end_date']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('jobs/' . $job['id'] . '/edit') ?>">Edit</a>
    <form method="post" action="<?= site_url('jobs/' . $job['id'] . '/toggle') ?>" style="display:inline">
      <?= csrf_field() ?><button class="btn ghost" type="submit"><?= $job['status'] === 'open' ? 'Close job' : 'Reopen' ?></button>
    </form>
    <button class="btn secondary" onclick="window.print()">Print</button>
    <a class="btn ghost" href="<?= site_url('jobs') ?>">Back</a>
  </div>
</div>

<form class="filterbar no-print" method="get">
  <div class="field"><label>From</label><input type="date" name="from" value="<?= esc($from) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="to" value="<?= esc($to) ?>"></div>
  <button class="btn" type="submit">Apply</button>
  <a class="btn ghost" href="<?= site_url('jobs/' . $job['id']) ?>">All time</a>
</form>

<div class="kpis">
  <div class="kpi"><div class="k-label">Revenue</div><div class="k-value mono"><?= money_c($pl['revenue'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label">Direct cost</div><div class="k-value mono"><?= money_c($pl['direct_cost'], false, 0) ?></div></div>
  <div class="kpi <?= $pl['gross_profit'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label">Gross profit</div><div class="k-value mono"><?= money_c($pl['gross_profit'], false, 0) ?></div></div>
  <div class="kpi <?= $pl['net'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label">Net profit</div><div class="k-value mono"><?= money_c($pl['net'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label">Margin</div><div class="k-value mono"><?= number_format($pl['margin'], 1) ?>%</div></div>
</div>

<div class="card" style="max-width:720px">
  <div class="report-title">
    <div class="co"><?= esc(company_name()) ?></div>
    <h1>Laba Rugi per Job — <?= esc($job['code']) ?></h1>
  </div>
  <table class="grid tight">
    <tbody>
      <?= $section($pl['groups']['revenue']) ?>
      <?= $section($pl['groups']['cogs']) ?>
      <tr class="subtotal" style="background:var(--brand-soft)"><td>LABA KOTOR / GROSS PROFIT</td><td class="right mono"><?= money($pl['gross_profit']) ?></td></tr>
      <?= $section($pl['groups']['expense']) ?>
      <?= $section($pl['groups']['other_income']) ?>
      <?= $section($pl['groups']['other_expense']) ?>
    </tbody>
    <tfoot>
      <tr><td>LABA (RUGI) BERSIH / NET PROFIT</td><td class="right mono"><?= money($pl['net']) ?></td></tr>
    </tfoot>
  </table>
  <?php if (! array_filter($pl['groups'], static fn ($g) => $g['rows'])): ?>
    <p class="muted">No posted journal lines are tagged to this job yet. Pick this job on journal / purchase / sales lines.</p>
  <?php endif ?>
</div>

<?php
$jbxNet    = (float) ($job['sales_ref'] ?? 0) - (float) ($job['buy_ref'] ?? 0);
$jbxMargin = (float) ($job['sales_ref'] ?? 0) != 0.0 ? $jbxNet / (float) $job['sales_ref'] * 100 : null;
$hasJbx    = ($job['sales_ref'] ?? null) !== null || ($job['buy_ref'] ?? null) !== null
    || ($job['pax'] ?? null) !== null || ($job['category'] ?? null) !== null || ($job['jambix_status'] ?? null) !== null;
?>
<?php if ($hasJbx): ?>
  <div class="card" style="max-width:520px">
    <h2>Jambix reference <span class="muted small" style="font-weight:400">— quoted figures, not the posted ledger</span></h2>
    <table class="grid tight">
      <tbody>
        <?php if (($job['sales_ref'] ?? null) !== null): ?><tr><td class="muted">Sales (quoted)</td><td class="right mono"><?= money((float) $job['sales_ref'], 0) ?></td></tr><?php endif ?>
        <?php if (($job['buy_ref'] ?? null) !== null): ?><tr><td class="muted">Buy (quoted)</td><td class="right mono"><?= money((float) $job['buy_ref'], 0) ?></td></tr><?php endif ?>
        <tr><td class="muted">Net (quoted)</td><td class="right mono" style="font-weight:700;<?= $jbxNet < 0 ? 'color:var(--red)' : '' ?>"><?= money($jbxNet, 0) ?></td></tr>
        <tr><td class="muted">Margin (quoted)</td><td class="right mono"><?= $jbxMargin === null ? '—' : number_format($jbxMargin, 1) . '%' ?></td></tr>
        <?php if (($job['pax'] ?? null) !== null): ?><tr><td class="muted">Pax</td><td class="right mono"><?= (int) $job['pax'] ?></td></tr><?php endif ?>
        <?php if (($job['category'] ?? null) !== null): ?><tr><td class="muted">Category</td><td><?= esc($job['category']) ?></td></tr><?php endif ?>
        <?php if (($job['jambix_status'] ?? null) !== null): ?><tr><td class="muted">Jambix status</td><td><?= esc($job['jambix_status']) ?></td></tr><?php endif ?>
        <?php if (($job['created_on'] ?? null) !== null): ?><tr><td class="muted">Created on</td><td><?= date_id($job['created_on']) ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<?php if (! empty($cfDefs) && array_filter($cfValues ?? [])): ?>
  <div class="card" style="max-width:520px">
    <h2>Additional information</h2>
    <table class="grid tight">
      <tbody>
        <?php foreach ($cfDefs as $d): ?>
          <?php if (($cfValues[$d['field_key']] ?? '') === '') {
              continue;
          } ?>
          <tr><td class="muted"><?= esc($d['label']) ?></td><td><?= esc($cfValues[$d['field_key']]) ?></td></tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
