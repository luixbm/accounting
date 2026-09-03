<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1><?= lang('Nav.purchases') ?></h1><div class="muted small"><?= lang('Txn.purchases_sub') ?></div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('purchases/import') ?>"><?= lang('App.import') ?></a>
    <a class="btn ghost" href="<?= site_url('purchases/jambix') ?>">Jambix</a>
    <a class="btn ghost" href="<?= site_url('purchases/payments') ?>"><?= lang('Txn.payments') ?></a>
    <?php if (user_can('journal.post')): ?><a class="btn ghost" href="<?= site_url('purchases/payments/new') ?>"><?= lang('Txn.pay_supplier') ?></a><?php endif ?>
    <?php if (user_can('journal.create')): ?><a class="btn" href="<?= site_url('purchases/new') ?>"><?= lang('Txn.new_invoice_btn') ?></a><?php endif ?>
  </div>
</div>

<form class="filterbar" method="get">
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
  <div class="field">
    <label><?= lang('Txn.supplier') ?></label>
    <select name="supplier_id">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (string) $f['supplier_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field"><label><?= lang('App.from') ?></label><input type="date" name="from" value="<?= esc($f['from']) ?>"></div>
  <div class="field"><label><?= lang('App.to') ?></label><input type="date" name="to" value="<?= esc($f['to']) ?>"></div>
  <button class="btn" type="submit"><?= lang('App.filter') ?></button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Txn.supplier') ?></th><th><?= lang('Report.col_ref') ?></th><th><?= lang('App.description') ?></th>
        <?php foreach ($cfDefs as $d): ?><th><?= esc($d['label']) ?></th><?php endforeach ?>
        <th class="right"><?= lang('App.total') ?> (<?= base_code() ?>)</th><th class="right"><?= lang('Txn.outstanding') ?></th><th><?= lang('App.status') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('purchases/' . $r['id']) ?>"><?= esc($r['internal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($r['invoice_date']) ?></td>
          <td><?= esc($r['supplier_name']) ?></td>
          <td class="small"><?= esc($r['supplier_ref']) ?></td>
          <td class="small"><?= esc($r['description']) ?></td>
          <?php foreach ($cfDefs as $d): ?>
            <td class="small"><?= esc(($cfValues[$r['id']][$d['field_key']] ?? '')) ?></td>
          <?php endforeach ?>
          <td class="right mono"><?= money($r['total_base']) ?></td>
          <td class="right mono"><?= money($r['outstanding_base'], 2, true) ?></td>
          <td><?= status_badge($r['status'] === 'partial' ? 'draft' : ($r['status'] === 'paid' ? 'posted' : $r['status'])) ?>
            <?php if ($r['status'] === 'partial'): ?><span class="small muted"><?= lang('App.partial') ?></span><?php endif ?>
            <?php if ($r['status'] === 'paid'): ?><span class="small muted"><?= lang('App.paid') ?></span><?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="9" class="muted"><?= lang('Txn.no_invoices') ?></td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
