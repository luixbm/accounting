<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$f       = $filters;
$qs      = array_filter([
    'q'        => $f['q'] ?? '',
    'arr_from' => $f['arr_from'] ?? '',
    'arr_to'   => $f['arr_to'] ?? '',
    'pl'       => $f['pl'] ?? '',
    'status'   => $f['status'] ?? '',
], static fn ($v) => $v !== '' && $v !== null);
$xlsxUrl = site_url('jobs') . '?' . http_build_query($qs + ['format' => 'xlsx']);
?>

<div class="page-head">
  <div><h1>Jobs</h1><div class="muted small">Profit &amp; loss per job / project / trip</div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= esc($xlsxUrl, 'attr') ?>">Export Excel</a>
    <button class="btn ghost" type="button" onclick="window.print()">Print / PDF</button>
    <a class="btn ghost" href="<?= site_url('jobs/import') ?>">Import Jambix</a>
    <a class="btn" href="<?= site_url('jobs/new') ?>">+ New Job</a>
  </div>
</div>

<div class="report-title print-only">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_name()) ?></div>
  <h1>Jobs</h1>
  <?php if (! empty($subtitle)): ?><div class="muted"><?= esc($subtitle) ?></div><?php endif ?>
</div>

<form class="filterbar" method="get">
  <div class="field"><label>Search</label><input name="q" value="<?= esc($f['q']) ?>" placeholder="code / name"></div>
  <div class="field" style="max-width:150px"><label>Arrival from</label><input type="date" name="arr_from" value="<?= esc($f['arr_from'] ?? '') ?>"></div>
  <div class="field" style="max-width:150px"><label>Arrival to</label><input type="date" name="arr_to" value="<?= esc($f['arr_to'] ?? '') ?>"></div>
  <div class="field" style="max-width:150px">
    <label>P&amp;L</label>
    <select name="pl">
      <option value="">All</option>
      <option value="loss" <?= ($f['pl'] ?? '') === 'loss' ? 'selected' : '' ?>>Loss-making</option>
      <option value="profit" <?= ($f['pl'] ?? '') === 'profit' ? 'selected' : '' ?>>Profitable</option>
    </select>
  </div>
  <div class="field" style="max-width:130px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="open" <?= $f['status'] === 'open' ? 'selected' : '' ?>>Open</option>
      <option value="closed" <?= $f['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr><th>Code</th><th>Name</th><th>Customer</th><th>Arrival</th>
        <th class="right">Revenue</th><th class="right">Cost</th><th class="right">Net</th><th class="right">Margin</th>
        <th class="right" title="Jambix quoted sales − buy">Jbx Net</th><th class="right" title="Jambix net ÷ sales">Jbx %</th>
        <th>Status</th><th class="no-print"></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $j): ?>
        <?php $s = $summaries[$j['id']] ?? ['revenue' => 0, 'cost' => 0, 'net' => 0];
        $margin = $s['revenue'] != 0 ? $s['net'] / $s['revenue'] * 100 : 0;
        $jbxHas = ($j['sales_ref'] ?? null) !== null || ($j['buy_ref'] ?? null) !== null;
        $jbxNet = (float) ($j['sales_ref'] ?? 0) - (float) ($j['buy_ref'] ?? 0);
        $jbxMargin = (float) ($j['sales_ref'] ?? 0) != 0.0 ? $jbxNet / (float) $j['sales_ref'] * 100 : null; ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('jobs/' . $j['id']) ?>"><?= esc($j['code']) ?></a></td>
          <td><?= esc($j['name']) ?></td>
          <td class="small"><?= esc($j['customer_name']) ?></td>
          <td class="small nowrap"><?= $j['start_date'] ? date_id($j['start_date']) : '<span class="muted">—</span>' ?></td>
          <td class="right mono"><?= money($s['revenue'], 0, true) ?></td>
          <td class="right mono"><?= money($s['cost'], 0, true) ?></td>
          <td class="right mono" style="font-weight:700;color:<?= $s['net'] < 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money($s['net'], 0) ?></td>
          <td class="right mono small"><?= $s['revenue'] != 0 ? number_format($margin, 1) . '%' : '' ?></td>
          <td class="right mono small" style="<?= $jbxHas && $jbxNet < 0 ? 'color:var(--red)' : 'color:var(--muted)' ?>"><?= $jbxHas ? money($jbxNet, 0, true) : '' ?></td>
          <td class="right mono small" style="color:var(--muted)"><?= $jbxMargin === null ? '' : number_format($jbxMargin, 1) . '%' ?></td>
          <td><?= status_badge($j['status'] === 'open' ? 'open' : 'closed') ?></td>
          <td class="right nowrap no-print">
            <a class="btn sm ghost" href="<?= site_url('jobs/' . $j['id'] . '/edit') ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="12" class="muted">No jobs yet.</td></tr><?php endif ?>
    </tbody>
    <?php if ($rows): ?>
      <tfoot>
        <tr class="subtotal">
          <td colspan="4">TOTAL — <?= number_format($total) ?> job<?= $total === 1 ? '' : 's' ?></td>
          <td class="right mono"><?= money($grand['revenue'], 0, true) ?></td>
          <td class="right mono"><?= money($grand['cost'], 0, true) ?></td>
          <td class="right mono" style="font-weight:700;color:<?= $grand['net'] < 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money($grand['net'], 0) ?></td>
          <td class="right mono small"><?= $grand['revenue'] != 0.0 ? number_format($grand['net'] / $grand['revenue'] * 100, 1) . '%' : '' ?></td>
          <td class="right mono small" style="color:var(--muted)"><?= money($grand['jbxnet'], 0, true) ?></td>
          <td></td><td></td><td class="no-print"></td>
        </tr>
      </tfoot>
    <?php endif ?>
  </table>
</div>

<?= $pager->links('default', 'default_full') ?>

<?= $this->endSection() ?>
