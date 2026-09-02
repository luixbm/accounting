<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $showBase = $kind === 'sales' ? 'sales' : 'purchases'; ?>

<div class="page-head">
  <div><h1><?= ucfirst($kind) ?> import batch #<?= $batch['id'] ?></h1>
    <div class="muted small"><?= esc($batch['filename']) ?> · <?= esc($batch['status']) ?><?= $batch['committed_at'] ? ' ' . esc($batch['committed_at']) : '' ?></div></div>
  <div class="btn-group no-print"><a class="btn ghost" href="<?= site_url($base) ?>">All imports</a></div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label">Imported</div><div class="k-value mono"><?= (int) $batch['journal_count'] ?></div></div>
  <div class="kpi"><div class="k-label">Draft</div><div class="k-value mono"><?= $counts['draft'] ?? 0 ?></div></div>
  <div class="kpi pos"><div class="k-label">Posted+</div><div class="k-value mono"><?= ($counts['posted'] ?? 0) + ($counts['partial'] ?? 0) + ($counts['paid'] ?? 0) ?></div></div>
  <div class="kpi"><div class="k-label">Skipped</div><div class="k-value mono"><?= (int) $batch['skipped_count'] ?></div></div>
</div>

<div class="card">
  <div class="btn-group">
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.post')): ?>
      <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/post-all') ?>"
        onsubmit="return confirm('Post all <?= $counts['draft'] ?> draft invoices?')">
        <?= csrf_field() ?><button class="btn" type="submit">Post all <?= $counts['draft'] ?> drafts</button>
      </form>
    <?php endif ?>
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.delete')): ?>
      <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/revert') ?>"
        onsubmit="return confirm('Delete the draft invoices from this batch?')">
        <?= csrf_field() ?><button class="btn danger" type="submit">Delete draft invoices</button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>No.</th><th>Date</th><th>Party ref</th><th class="right">Total (<?= base_code() ?>)</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url($showBase . '/' . $inv['id']) ?>"><?= esc($inv['internal_no']) ?></a>
            <?php if ($inv['external_id']): ?><div class="small muted"><?= esc($inv['external_id']) ?></div><?php endif ?></td>
          <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
          <td class="small"><?= esc($inv[$kind === 'sales' ? 'customer_ref' : 'supplier_ref']) ?></td>
          <td class="right mono"><?= money($inv['total_base']) ?></td>
          <td><?= status_badge($inv['status'] === 'draft' ? 'draft' : ($inv['status'] === 'void' ? 'void' : 'posted')) ?>
            <?php if (in_array($inv['status'], ['partial', 'paid'], true)): ?><span class="small muted"><?= esc($inv['status']) ?></span><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $invoices): ?><tr><td colspan="5" class="muted">No invoices from this batch.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
