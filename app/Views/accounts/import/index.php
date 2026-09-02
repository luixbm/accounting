<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import Chart of Accounts</h1><div class="muted small">Load or update this company's accounts from a spreadsheet.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts') ?>">Back to accounts</a></div>
</div>

<div class="card" style="max-width:640px">
  <h2>New import</h2>
  <form method="post" action="<?= site_url('accounts/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>Spreadsheet file (.xlsx, .xls or .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit">Upload &amp; continue</button>
  </form>
  <p class="muted small" style="margin-top:10px">
    One row per account. You'll map the columns (number, name, type, parent, currency) and
    match the file's account-type codes to the app's types. Re-importing the same file
    updates existing accounts by number.
  </p>
</div>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Sheet</th><th>Status</th><th class="right">Accounts</th><th>When</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td><?= status_badge($b['status'] === 'committed' ? 'posted' : 'draft') ?> <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('accounts/import/' . $b['id'] . '/map') ?>"><?= $b['status'] === 'committed' ? 'Re-run' : 'Resume' ?></a></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
