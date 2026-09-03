<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1><?= lang('Txn.customer_receipts_h') ?></h1></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('sales') ?>"><?= lang('Txn.invoices') ?></a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn ghost" href="<?= site_url('sales/receipts/import') ?>"><?= lang('App.import') ?></a>
      <a class="btn ghost" href="<?= site_url('sales/receipts/deposit') ?>"><?= lang('Txn.dp_add') ?></a>
      <a class="btn" href="<?= site_url('sales/receipts/new') ?>"><?= lang('Txn.receive_payment_btn') ?></a>
    <?php endif ?>
  </div>
</div>

<form class="filterbar" method="get">
  <div class="field"><label><?= lang('App.search') ?></label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / ref"></div>
  <div class="field">
    <label><?= lang('Txn.customer') ?></label>
    <select name="customer_id">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach ($customers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (string) $f['customer_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:200px">
    <label><?= lang('Report.col_type') ?></label>
    <?php $k = $f['kind'] ?? ''; ?>
    <select name="kind">
      <option value=""><?= lang('App.all') ?></option>
      <option value="settlement" <?= $k === 'settlement' ? 'selected' : '' ?>><?= lang('Txn.receipts_opt') ?></option>
      <option value="deposit" <?= $k === 'deposit' ? 'selected' : '' ?>><?= lang('Txn.dps_opt') ?></option>
      <option value="deposit_open" <?= $k === 'deposit_open' ? 'selected' : '' ?>><?= lang('Txn.dps_with_balance') ?></option>
    </select>
  </div>
  <button class="btn" type="submit"><?= lang('App.filter') ?></button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Txn.customer') ?></th><th><?= lang('Report.col_type') ?></th><th><?= lang('Txn.into_bank') ?></th><th><?= lang('Report.col_ref') ?></th><th class="right"><?= lang('App.total') ?> (<?= base_code() ?>)</th><th class="right"><?= lang('Txn.unapplied') ?></th><th><?= lang('App.status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <?php $isDep = ($p['kind'] ?? '') === 'deposit'; $un = (float) ($p['unapplied'] ?? 0); ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('sales/receipts/' . $p['id']) ?>"><?= esc($p['receipt_no']) ?></a></td>
          <td class="nowrap"><?= date_id($p['receipt_date']) ?></td>
          <td><?= esc($p['customer_name']) ?></td>
          <td><?= $isDep ? '<span class="badge badge-amber">' . esc(lang('Txn.dp_badge')) . '</span>' : '<span class="badge badge-gray">' . esc(lang('Txn.receipt_badge')) . '</span>' ?></td>
          <td class="small"><?= esc($p['bank_name']) ?></td>
          <td class="small"><?= esc($p['reference']) ?></td>
          <td class="right mono"><?= money($p['amount_base']) ?></td>
          <td class="right mono">
            <?php if ($isDep && $p['status'] !== 'void'): ?>
              <?php if ($un > 0.005): ?>
                <a href="<?= site_url('sales/receipts/' . $p['id'] . '/apply') ?>"><?= money($un) ?></a>
              <?php else: ?>
                <span class="muted"><?= lang('Txn.settled') ?></span>
              <?php endif ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif ?>
          </td>
          <td><?= status_badge($p['status'] === 'void' ? 'void' : 'posted') ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="9" class="muted"><?= lang('Txn.no_receipts') ?></td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
