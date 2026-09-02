<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s   = $parsed['summary'];
$canCommit = $s['ok'] > 0 && $batch['status'] !== 'committed' && user_can('journal.create');
?>

<div class="page-head">
  <div><h1>Import · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?> · sheet <?= esc($batch['sheet']) ?></div></div>
</div>
<?= view('journals/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="kpis">
  <div class="kpi"><div class="k-label">Journals found</div><div class="k-value mono"><?= $s['groups'] ?></div></div>
  <div class="kpi pos"><div class="k-label">Ready to import</div><div class="k-value mono"><?= $s['ok'] ?></div></div>
  <div class="kpi <?= $s['error'] ? 'neg' : '' ?>"><div class="k-label">With errors</div><div class="k-value mono"><?= $s['error'] ?></div></div>
  <div class="kpi"><div class="k-label">Skipped (already exist)</div><div class="k-value mono"><?= $s['skip'] ?></div></div>
</div>

<?php if ($parsed['unmapped']): ?>
  <div class="alert alert-error">
    <?= count($parsed['unmapped']) ?> account label(s) are still unmatched:
    <?= esc(implode(', ', array_slice($parsed['unmapped'], 0, 20))) ?><?= count($parsed['unmapped']) > 20 ? '…' : '' ?>
    — <a href="<?= site_url('journals/import/' . $batch['id'] . '/accounts') ?>">fix the mapping</a>.
  </div>
<?php endif ?>

<?php if ($parsed['newCustomers'] || $parsed['newSuppliers']): ?>
  <div class="alert alert-success">
    Will create
    <?php if ($parsed['newCustomers']): ?><strong><?= count($parsed['newCustomers']) ?></strong> customer(s)<?php endif ?>
    <?php if ($parsed['newCustomers'] && $parsed['newSuppliers']): ?> and <?php endif ?>
    <?php if ($parsed['newSuppliers']): ?><strong><?= count($parsed['newSuppliers']) ?></strong> supplier(s)<?php endif ?>
    from the name column:
    <span class="small muted"><?= esc(implode(', ', array_slice(array_merge($parsed['newCustomers'], $parsed['newSuppliers']), 0, 25))) ?></span>
  </div>
<?php endif ?>

<div class="card">
  <?php if ($canCommit): ?>
    <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/commit') ?>"
      onsubmit="return confirm('Import <?= $s['ok'] ?> journals as draft?')">
      <?= csrf_field() ?>
      <button class="btn" type="submit">Import <?= $s['ok'] ?> journal(s) as draft</button>
      <span class="muted small">Journals with errors and already-existing numbers are skipped.</span>
    </form>
  <?php elseif ($batch['status'] === 'committed'): ?>
    <a class="btn" href="<?= site_url('journals/import/' . $batch['id']) ?>">View imported batch</a>
  <?php else: ?>
    <span class="muted">Nothing can be imported yet — resolve the errors below.</span>
  <?php endif ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr><th>Journal</th><th>Date</th><th>Description</th><th>Cur</th><th class="right">Total (<?= base_code() ?>)</th><th class="center">Lines</th><th>Status</th><th>Notes</th></tr>
    </thead>
    <tbody>
      <?php foreach ($parsed['journals'] as $j): ?>
        <tr>
          <td class="mono nowrap"><?= esc($j['no'] ?? '(auto)') ?><div class="small muted"><?= esc($j['key']) ?></div></td>
          <td class="nowrap"><?= date_id($j['date']) ?></td>
          <td><?= esc(mb_strimwidth($j['description'], 0, 46, '…')) ?></td>
          <td class="small"><?= esc($j['currency']) ?><?= $j['rate'] != 1 ? ' @' . money($j['rate'], 2) : '' ?></td>
          <td class="right mono"><?= money($j['total_base']) ?></td>
          <td class="center"><?= count($j['lines']) ?></td>
          <td><?php
              $badge = $j['status'] === 'ok' ? 'badge badge-green' : ($j['status'] === 'skip' ? 'badge badge-gray' : 'badge badge-red');
          echo '<span class="' . $badge . '">' . esc($j['status']) . '</span>'; ?></td>
          <td class="small">
            <?php foreach ($j['errors'] as $e): ?><div style="color:var(--red)"><?= esc($e) ?></div><?php endforeach ?>
            <?php foreach ($j['warnings'] as $w): ?><div class="muted"><?= esc($w) ?></div><?php endforeach ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $parsed['journals']): ?><tr><td colspan="8" class="muted">No journals parsed — check the column mapping and header row.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
