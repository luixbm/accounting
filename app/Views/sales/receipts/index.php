<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1>Customer Receipts</h1></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('sales') ?>">Invoices</a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn ghost" href="<?= site_url('sales/receipts/import') ?>">Import</a>
      <a class="btn ghost" href="<?= site_url('sales/receipts/deposit') ?>">+ Down payment</a>
      <a class="btn" href="<?= site_url('sales/receipts/new') ?>">+ Receive payment</a>
    <?php endif ?>
  </div>
</div>

<form class="filterbar" method="get">
  <div class="field"><label>Search</label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / ref"></div>
  <div class="field">
    <label>Customer</label>
    <select name="customer_id">
      <option value="">All</option>
      <?php foreach ($customers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (string) $f['customer_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:200px">
    <label>Type</label>
    <?php $k = $f['kind'] ?? ''; ?>
    <select name="kind">
      <option value="">All</option>
      <option value="settlement" <?= $k === 'settlement' ? 'selected' : '' ?>>Receipts</option>
      <option value="deposit" <?= $k === 'deposit' ? 'selected' : '' ?>>Down payments</option>
      <option value="deposit_open" <?= $k === 'deposit_open' ? 'selected' : '' ?>>Down payments with balance</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>No.</th><th>Date</th><th>Customer</th><th>Type</th><th>Into bank</th><th>Ref</th><th class="right">Amount (<?= base_code() ?>)</th><th class="right">Unapplied</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <?php $isDep = ($p['kind'] ?? '') === 'deposit'; $un = (float) ($p['unapplied'] ?? 0); ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('sales/receipts/' . $p['id']) ?>"><?= esc($p['receipt_no']) ?></a></td>
          <td class="nowrap"><?= date_id($p['receipt_date']) ?></td>
          <td><?= esc($p['customer_name']) ?></td>
          <td><?= $isDep ? '<span class="badge badge-amber">down payment</span>' : '<span class="badge badge-gray">receipt</span>' ?></td>
          <td class="small"><?= esc($p['bank_name']) ?></td>
          <td class="small"><?= esc($p['reference']) ?></td>
          <td class="right mono"><?= money($p['amount_base']) ?></td>
          <td class="right mono">
            <?php if ($isDep && $p['status'] !== 'void'): ?>
              <?php if ($un > 0.005): ?>
                <a href="<?= site_url('sales/receipts/' . $p['id'] . '/apply') ?>"><?= money($un) ?></a>
              <?php else: ?>
                <span class="muted">settled</span>
              <?php endif ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif ?>
          </td>
          <td><?= status_badge($p['status'] === 'void' ? 'void' : 'posted') ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="9" class="muted">No receipts.</td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
