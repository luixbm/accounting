<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $party = $kind === 'sales' ? 'customers' : 'suppliers'; ?>

<div class="page-head">
  <div><h1><?= ucfirst($kind) ?> Import</h1><div class="muted small">Load <?= esc($kind) ?> invoices from a spreadsheet as drafts, then review and post.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url($kind === 'sales' ? 'sales' : 'purchases') ?>">Back</a></div>
</div>

<?php if (user_can('journal.create')): ?>
  <div class="card" style="max-width:620px">
    <h2>New import</h2>
    <form method="post" action="<?= site_url($base) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label>Spreadsheet file (.xlsx, .xls or .csv)</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
      </div>
      <button class="btn" type="submit">Upload &amp; continue</button>
    </form>
    <p class="muted small" style="margin-top:10px">
      Each row is one invoice line; rows sharing the “Invoice group / no.” column become one invoice.
      Missing <?= esc($party) ?> are created from the name column.
    </p>
  </div>
<?php endif ?>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Sheet</th><th>Status</th><th class="right">Invoices</th><th>When</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td class="small"><?= esc($b['status']) ?></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right">
            <?php if (in_array($b['status'], ['committed', 'reverted'], true)): ?>
              <a class="btn sm ghost" href="<?= site_url($base . '/' . $b['id']) ?>">Open</a>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url($base . '/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
