<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import Journals</h1><div class="muted small">Load journals from a spreadsheet as drafts, then review and post them.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('journals') ?>">Back to journals</a></div>
</div>

<?php if (user_can('journal.create')): ?>
  <div class="card" style="max-width:620px">
    <h2>New import</h2>
    <form method="post" action="<?= site_url('journals/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label>Spreadsheet file (.xlsx, .xls or .csv)</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
      </div>
      <button class="btn" type="submit">Upload &amp; continue</button>
    </form>
    <p class="muted small" style="margin-top:10px">
      Each row is one journal line; rows that share a value in the “Journal group / no.” column
      become one journal. You’ll map the columns and match account names on the next screens.
    </p>
  </div>
<?php endif ?>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Sheet</th><th>Status</th><th class="right">Journals</th><th>When</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td><?= status_badge($b['status'] === 'committed' ? 'posted' : ($b['status'] === 'reverted' ? 'void' : 'draft')) ?>
            <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right nowrap">
            <?php if ($b['status'] === 'committed' || $b['status'] === 'reverted'): ?>
              <a class="btn sm ghost" href="<?= site_url('journals/import/' . $b['id']) ?>">Open</a>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('journals/import/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
