<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1>Sales</h1><div class="muted small">Customer invoices &amp; their payments</div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('sales/import') ?>">Import</a>
    <a class="btn ghost" href="<?= site_url('sales/receipts') ?>">Receipts</a>
    <?php if (user_can('journal.post')): ?><a class="btn ghost" href="<?= site_url('sales/receipts/new') ?>">+ Receive payment</a><?php endif ?>
    <?php if (user_can('journal.create')): ?><a class="btn" href="<?= site_url('sales/new') ?>">+ New Invoice</a><?php endif ?>
  </div>
</div>

<form class="filterbar" method="get">
  <div class="field"><label>Search</label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / ref / desc"></div>
  <div class="field">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['draft', 'posted', 'partial', 'paid', 'void'] as $s): ?>
        <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field">
    <label>Customer</label>
    <select name="customer_id">
      <option value="">All</option>
      <?php foreach ($customers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (string) $f['customer_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field"><label>From</label><input type="date" name="from" value="<?= esc($f['from']) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="to" value="<?= esc($f['to']) ?>"></div>
  <button class="btn" type="submit">Filter</button>
  <a class="btn ghost" href="<?= site_url('sales') ?>">Reset</a>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th>No.</th><th>Date</th><th>Customer</th><th>Ref</th><th>Description</th>
        <?php foreach ($cfDefs as $d): ?><th><?= esc($d['label']) ?></th><?php endforeach ?>
        <th class="right">Total (<?= base_code() ?>)</th><th class="right">Outstanding</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('sales/' . $r['id']) ?>"><?= esc($r['internal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($r['invoice_date']) ?></td>
          <td><?= esc($r['customer_name']) ?></td>
          <td class="small"><?= esc($r['customer_ref']) ?></td>
          <td class="small"><?= esc($r['description']) ?></td>
          <?php foreach ($cfDefs as $d): ?>
            <td class="small"><?= esc(($cfValues[$r['id']][$d['field_key']] ?? '')) ?></td>
          <?php endforeach ?>
          <td class="right mono"><?= money($r['total_base']) ?></td>
          <td class="right mono"><?= money($r['outstanding_base'], 2, true) ?></td>
          <td><?= status_badge($r['status'] === 'partial' ? 'draft' : ($r['status'] === 'paid' ? 'posted' : $r['status'])) ?>
            <?php if ($r['status'] === 'partial'): ?><span class="small muted">partial</span><?php endif ?>
            <?php if ($r['status'] === 'paid'): ?><span class="small muted">paid</span><?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="9" class="muted">No invoices.</td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
