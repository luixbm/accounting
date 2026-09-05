<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1><?= lang('Nav.journals') ?></h1></div>
  <?php if (user_can('journal.create')): ?>
    <div class="btn-group">
      <a class="btn ghost" href="<?= site_url('journals/import') ?>"><?= lang('Txn.import_spreadsheet') ?></a>
      <a class="btn" href="<?= site_url('journals/new') ?>"><?= lang('Txn.new_journal') ?></a>
    </div>
  <?php endif ?>
</div>

<form class="filterbar" method="get">
  <div class="field"><label><?= lang('App.search') ?></label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / description / ref / line memo / account"></div>
  <div class="field">
    <label><?= lang('App.status') ?></label>
    <select name="status">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach (['draft', 'posted', 'void'] as $s): ?>
        <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field">
    <label><?= lang('Txn.source') ?></label>
    <select name="source">
      <option value=""><?= lang('App.all') ?></option>
      <?php foreach ($sources as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['source'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
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
      <tr><th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Txn.source') ?></th><th><?= lang('App.description') ?></th><th><?= lang('Report.col_ref') ?></th><th class="right"><?= lang('Txn.amount_base', [base_code()]) ?></th><th><?= lang('Txn.cur') ?></th><th><?= lang('App.status') ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $j): ?>
        <tr>
          <td class="nowrap mono"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
          <td class="small"><?= esc($sources[$j['source']] ?? $j['source']) ?></td>
          <td><?= esc($j['description']) ?></td>
          <td class="small"><?= esc($j['reference']) ?></td>
          <td class="right mono"><?= money($j['total_debit']) ?></td>
          <td class="small"><?= esc($j['currency_code']) ?></td>
          <td><?= status_badge($j['status']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $rows): ?><tr><td colspan="8" class="muted"><?= lang('Txn.no_journals') ?></td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
