<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import payments</h1><div class="muted small">Upload a worked-back payment list to post supplier payments in bulk.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/payments') ?>">Back to payments</a></div>
</div>

<div class="card" style="max-width:680px">
  <h2>New import</h2>
  <form method="post" action="<?= site_url('purchases/payments/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>Payment list file (.xlsx, .xls or .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit">Upload &amp; continue</button>
  </form>
  <p class="muted small" style="margin-top:10px">
    The file needs a column with the <b>invoice number</b> and a column with the <b>amount to pay</b>.
    Rows are matched to open purchase invoices, summed per invoice, grouped by supplier, and posted as
    one Supplier Payment each. An amount above the invoice's outstanding is capped; a smaller amount
    pays it partly. Reverting a batch voids the payments it created.
  </p>
</div>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Status</th><th class="right">Payments</th><th class="right">Unmatched</th><th>When</th><th></th></tr></thead>
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
              <form method="post" action="<?= site_url('purchases/payments/import/' . $b['id'] . '/revert') ?>" style="display:inline"
                onsubmit="return confirm('Void every payment this batch created?')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit">Revert</button>
              </form>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('purchases/payments/import/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
