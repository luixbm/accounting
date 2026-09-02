<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import receipts</h1><div class="muted small">Bulk-post customer receipts, or apply a bulk customer deposit across invoices.</div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('sales/receipts/import/template') ?>">Download template</a>
    <a class="btn ghost" href="<?= site_url('sales/receipts') ?>">Back to receipts</a>
  </div>
</div>

<div class="card" style="max-width:680px">
  <h2>New import</h2>
  <form method="post" action="<?= site_url('sales/receipts/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>File (.xlsx, .xls or .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit">Upload &amp; continue</button>
  </form>
  <p class="muted small" style="margin-top:10px">
    Needs an <b>invoice number</b> column and an <b>amount</b> column (<a href="<?= site_url('sales/receipts/import/template') ?>">download the template</a>).
    On the next step choose the mode:
    <b>Receipt</b> — money received into a bank, posted as one Customer Receipt per customer;
    <b>Apply deposit</b> — draw the amounts from each customer's oldest unapplied down payment (no cash).
    Amounts are capped at each invoice's outstanding.
  </p>
</div>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Status</th><th class="right">Posts</th><th class="right">Unmatched</th><th>When</th><th></th></tr></thead>
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
              <form method="post" action="<?= site_url('sales/receipts/import/' . $b['id'] . '/revert') ?>" style="display:inline"
                onsubmit="return confirm('Void every receipt this batch created?')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit">Revert</button>
              </form>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('sales/receipts/import/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
