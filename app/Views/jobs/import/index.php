<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import Jambix jobs</h1><div class="muted small">Record a Jambix job / dossier report into the Jobs module.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('jobs') ?>">Back to jobs</a></div>
</div>

<div class="card" style="max-width:680px">
  <h2>New import</h2>
  <form method="post" action="<?= site_url('jobs/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>Jambix job report file (.xlsx, .xls or .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit">Upload &amp; continue</button>
  </form>
  <p class="muted small" style="margin-top:10px">
    One row per dossier. Rows are matched on the <b>dossier number</b> (job code): a new number
    creates a job, an existing one is updated in place. The client column resolves or creates a
    customer by name. <b>Sales</b> and <b>Buy</b> are stored as Jambix reference figures only —
    the job P&amp;L reports keep reading the posted purchase / sales transactions.
  </p>
</div>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Status</th><th class="right">Jobs</th><th class="right">Skipped</th><th>When</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <?php $committed = $b['status'] === 'committed'; ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td><?= status_badge($committed ? 'posted' : 'draft') ?> <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $committed ? (int) $b['journal_count'] : '' ?></td>
          <td class="right mono"><?= $committed ? (int) $b['skipped_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['committed_at'] ?: $b['created_at']) ?></td>
          <td class="right nowrap">
            <?php if ($committed): ?>
              <form method="post" action="<?= site_url('jobs/import/' . $b['id'] . '/revert') ?>" style="display:inline"
                onsubmit="return confirm('Delete the jobs this batch created (only those not yet used on a transaction)?')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit">Revert</button>
              </form>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('jobs/import/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
