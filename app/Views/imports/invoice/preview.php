<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s         = $parsed['summary'];
$canCommit = $s['ok'] > 0 && $batch['status'] !== 'committed' && user_can('journal.create');
$party     = $kind === 'sales' ? 'customer' : 'supplier';
?>

<div class="page-head"><div><h1><?= ucfirst($kind) ?> Import · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?> · sheet <?= esc($batch['sheet']) ?></div></div></div>
<?= view('imports/invoice/_steps', ['active' => 'preview', 'batch' => $batch, 'base' => $base]) ?>

<div class="kpis">
  <div class="kpi"><div class="k-label">Invoices found</div><div class="k-value mono"><?= $s['groups'] ?></div></div>
  <div class="kpi pos"><div class="k-label">Ready</div><div class="k-value mono"><?= $s['ok'] ?></div></div>
  <div class="kpi <?= $s['error'] ? 'neg' : '' ?>"><div class="k-label">With errors</div><div class="k-value mono"><?= $s['error'] ?></div></div>
  <div class="kpi"><div class="k-label">Skipped (exist)</div><div class="k-value mono"><?= $s['skip'] ?></div></div>
</div>

<?php if ($parsed['unmapped']): ?>
  <div class="alert alert-error">
    Unmatched account label(s): <?= esc(implode(', ', array_slice($parsed['unmapped'], 0, 20))) ?>
    — <a href="<?= site_url($base . '/' . $batch['id'] . '/accounts') ?>">fix the mapping</a>.
  </div>
<?php endif ?>
<?php if ($parsed['newParties']): ?>
  <div class="alert alert-success">
    Will create <strong><?= count($parsed['newParties']) ?></strong> <?= esc($party) ?>(s):
    <span class="small muted"><?= esc(implode(', ', array_slice($parsed['newParties'], 0, 25))) ?></span>
  </div>
<?php endif ?>

<div class="card">
  <?php if ($canCommit): ?>
    <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/commit') ?>"
      onsubmit="return confirm('Import <?= $s['ok'] ?> invoices as draft?')">
      <?= csrf_field() ?>
      <button class="btn" type="submit">Import <?= $s['ok'] ?> invoice(s) as draft</button>
      <span class="muted small">Errored and already-imported invoices are skipped.</span>
    </form>
  <?php elseif ($batch['status'] === 'committed'): ?>
    <a class="btn" href="<?= site_url($base . '/' . $batch['id']) ?>">View imported batch</a>
  <?php else: ?>
    <span class="muted">Nothing importable yet — resolve the errors below.</span>
  <?php endif ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>Invoice key</th><th><?= ucfirst($party) ?></th><th>Date</th><th>Cur</th><th class="right">Subtotal</th><th class="right">PPN</th><th class="right">PPh</th><th class="right">Total</th><th class="center">Lines</th><th>Status</th><th>Notes</th></tr></thead>
    <tbody>
      <?php foreach ($parsed['invoices'] as $j): ?>
        <tr>
          <td class="mono nowrap"><?= esc($j['key']) ?></td>
          <td><?= esc($j['party_name']) ?><?= $j['party_id'] === null && $j['party_name'] !== '' ? ' <span class="badge badge-gray">new</span>' : '' ?></td>
          <td class="nowrap"><?= date_id($j['date']) ?></td>
          <td class="small"><?= esc($j['currency']) ?><?= $j['rate'] != 1 ? ' @' . money($j['rate'], 2) : '' ?></td>
          <td class="right mono"><?= money($j['subtotal']) ?></td>
          <td class="right mono"><?= money($j['ppn'], 2, true) ?></td>
          <td class="right mono"><?= money($j['pph'], 2, true) ?></td>
          <td class="right mono"><?= money($j['total']) ?></td>
          <td class="center"><?= count($j['lines']) ?></td>
          <td><?php
              $b = $j['status'] === 'ok' ? 'badge badge-green' : ($j['status'] === 'skip' ? 'badge badge-gray' : 'badge badge-red');
          echo '<span class="' . $b . '">' . esc($j['status']) . '</span>'; ?></td>
          <td class="small">
            <?php foreach ($j['errors'] as $e): ?><div style="color:var(--red)"><?= esc($e) ?></div><?php endforeach ?>
            <?php foreach ($j['warnings'] as $w): ?><div class="muted"><?= esc($w) ?></div><?php endforeach ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $parsed['invoices']): ?><tr><td colspan="11" class="muted">No invoices parsed — check the mapping and header row.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
