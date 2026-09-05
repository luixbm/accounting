<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$f    = $filters;
$cols = [['date', lang('App.date')], ['customer', lang('Txn.customer')], ['ref', lang('Txn.invoice_no')], ['desc', lang('App.description')]];
foreach ($cfDefs as $d) {
    $cols[] = ['cf_' . $d['field_key'], $d['label']];
}
$cols[] = ['total', lang('App.total')];
$cols[] = ['outstanding', lang('Txn.outstanding')];
$cols[] = ['status', lang('App.status')];
?>

<div class="page-head">
  <div><h1><?= lang('Nav.sales') ?></h1><div class="muted small"><?= lang('Txn.sales_sub') ?></div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('sales/import') ?>"><?= lang('App.import') ?></a>
    <a class="btn ghost" href="<?= site_url('sales/receipts') ?>"><?= lang('Txn.receipts') ?></a>
    <?php if (user_can('journal.post')): ?><a class="btn ghost" href="<?= site_url('sales/receipts/new') ?>"><?= lang('Txn.receive_payment_btn') ?></a><?php endif ?>
    <?php if (user_can('journal.create')): ?><a class="btn" href="<?= site_url('sales/new') ?>"><?= lang('Txn.new_invoice_btn') ?></a><?php endif ?>
  </div>
</div>

<form class="filterbar" method="get">
  <div class="field">
    <label><?= lang('Txn.customer') ?></label>
    <select name="customer_id">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach ($customers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (string) $f['customer_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field"><label><?= lang('App.search') ?></label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / ref / desc"></div>
  <div class="field">
    <label><?= lang('App.status') ?></label>
    <select name="status">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach (['draft', 'posted', 'partial', 'paid', 'void'] as $s): ?>
        <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= lang('App.' . $s) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field"><label><?= lang('App.from') ?></label><input type="date" name="from" value="<?= esc($f['from']) ?>"></div>
  <div class="field"><label><?= lang('App.to') ?></label><input type="date" name="to" value="<?= esc($f['to']) ?>"></div>
  <button class="btn" type="submit"><?= lang('App.filter') ?></button>
  <?= view('partials/filter_clear') ?>
  <div style="margin-left:auto"><?= view('partials/colpick', ['key' => 'sales', 'table' => '#saleTable', 'columns' => $cols]) ?></div>
</form>

<div class="card">
  <div style="overflow-x:auto">
  <table class="grid tight" id="saleTable">
    <thead>
      <tr>
        <th><?= lang('Report.col_no') ?></th><th data-col="date"><?= lang('App.date') ?></th><th data-col="customer"><?= lang('Txn.customer') ?></th><th data-col="ref"><?= lang('Txn.invoice_no') ?></th><th data-col="desc"><?= lang('App.description') ?></th>
        <?php foreach ($cfDefs as $d): ?><th data-col="cf_<?= esc($d['field_key'], 'attr') ?>"><?= esc($d['label']) ?></th><?php endforeach ?>
        <th class="right" data-col="total"><?= lang('App.total') ?> (<?= base_code() ?>)</th><th class="right" data-col="outstanding"><?= lang('Txn.outstanding') ?></th><th data-col="status"><?= lang('App.status') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('sales/' . $r['id']) ?>"><?= esc($r['internal_no']) ?></a></td>
          <td class="nowrap" data-col="date"><?= date_id($r['invoice_date']) ?></td>
          <td data-col="customer"><?= esc($r['customer_name']) ?></td>
          <td class="small" data-col="ref"><?= esc($r['customer_ref']) ?></td>
          <td class="small" data-col="desc"><?= esc($r['description']) ?></td>
          <?php foreach ($cfDefs as $d): ?>
            <td class="small" data-col="cf_<?= esc($d['field_key'], 'attr') ?>"><?= esc(($cfValues[$r['id']][$d['field_key']] ?? '')) ?></td>
          <?php endforeach ?>
          <td class="right mono" data-col="total"><?= money($r['total_base']) ?></td>
          <td class="right mono" data-col="outstanding"><?= money($r['outstanding_base'], 2, true) ?></td>
          <td data-col="status"><?= status_badge($r['status'] === 'partial' ? 'draft' : ($r['status'] === 'paid' ? 'posted' : $r['status'])) ?>
            <?php if ($r['status'] === 'partial'): ?><span class="small muted"><?= lang('App.partial') ?></span><?php endif ?>
            <?php if ($r['status'] === 'paid'): ?><span class="small muted"><?= lang('App.paid') ?></span><?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="9" class="muted"><?= lang('Txn.no_invoices') ?></td></tr><?php endif ?>
    </tbody>
  </table>
  </div>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
