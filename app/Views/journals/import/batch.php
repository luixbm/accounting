<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Import batch #<?= $batch['id'] ?></h1>
    <div class="muted small">
      <?= esc($batch['filename']) ?> · sheet <?= esc($batch['sheet']) ?> ·
      <?= esc($batch['status']) ?><?= $batch['committed_at'] ? ' ' . esc($batch['committed_at']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('journals/import') ?>">All imports</a>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label">Imported</div><div class="k-value mono"><?= (int) $batch['journal_count'] ?></div></div>
  <div class="kpi"><div class="k-label">Draft</div><div class="k-value mono"><?= $counts['draft'] ?? 0 ?></div></div>
  <div class="kpi pos"><div class="k-label">Posted</div><div class="k-value mono"><?= $counts['posted'] ?? 0 ?></div></div>
  <div class="kpi"><div class="k-label">Skipped at import</div><div class="k-value mono"><?= (int) $batch['skipped_count'] ?></div></div>
</div>

<div class="card">
  <div class="btn-group">
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.post')): ?>
      <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/post-all') ?>"
        onsubmit="return confirm('Post all <?= $counts['draft'] ?> draft journals from this batch?')">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Post all <?= $counts['draft'] ?> drafts</button>
      </form>
    <?php endif ?>
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.delete')): ?>
      <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/revert') ?>"
        onsubmit="return confirm('Delete the draft journals from this batch? Posted journals are kept.')">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit">Delete draft journals</button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>No.</th><th>Date</th><th>Description</th><th class="right">Amount (<?= base_code() ?>)</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($journals as $j): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
          <td><?= esc($j['description']) ?></td>
          <td class="right mono"><?= money($j['total_debit']) ?></td>
          <td><?= status_badge($j['status']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $journals): ?><tr><td colspan="5" class="muted">No journals from this batch (all deleted, or none imported).</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
