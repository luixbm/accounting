<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters; ?>

<div class="page-head">
  <div><h1>Journals</h1></div>
  <?php if (user_can('journal.create')): ?>
    <div class="btn-group">
      <a class="btn ghost" href="<?= site_url('journals/import') ?>">Import from spreadsheet</a>
      <a class="btn" href="<?= site_url('journals/new') ?>">+ New Journal</a>
    </div>
  <?php endif ?>
</div>

<form class="filterbar" method="get">
  <div class="field"><label>Search</label><input name="q" value="<?= esc($f['q']) ?>" placeholder="no. / description / ref"></div>
  <div class="field">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['draft', 'posted', 'void'] as $s): ?>
        <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field">
    <label>Source</label>
    <select name="source">
      <option value="">All</option>
      <?php foreach ($sources as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['source'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field"><label>From</label><input type="date" name="from" value="<?= esc($f['from']) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="to" value="<?= esc($f['to']) ?>"></div>
  <button class="btn" type="submit">Filter</button>
  <a class="btn ghost" href="<?= site_url('journals') ?>">Reset</a>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr><th>No.</th><th>Date</th><th>Source</th><th>Description</th><th>Ref</th><th class="right">Amount (<?= base_code() ?>)</th><th>Cur</th><th>Status</th></tr>
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
      <?php if (! $rows): ?><tr><td colspan="8" class="muted">No journals match.</td></tr><?php endif ?>
    </tbody>
  </table>
  <?= $pager->links('default', 'default_full') ?>
</div>

<?= $this->endSection() ?>
