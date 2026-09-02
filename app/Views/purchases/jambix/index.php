<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import Jambix bookings</h1><div class="muted small">Group a Jambix export into budget-cost purchase invoices.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases') ?>">Back to purchases</a></div>
</div>

<div class="card" style="max-width:660px">
  <h2>New import</h2>
  <form method="post" action="<?= site_url('purchases/jambix') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>Jambix export file (.xlsx, .xls or .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit">Upload &amp; continue</button>
  </form>
  <p class="muted small" style="margin-top:10px">
    One row per booked service. Rows are grouped by <b>Supplier + Dossier number</b> into one
    purchase invoice each, carrying the <b>BUY IDR</b> figure as a budget cost. Travel date becomes
    the invoice date; the booking id is the per-line key. Re-importing the same file adds nothing
    that is already in — invoices are matched on their external id, lines on the booking id.
  </p>
</div>

<div class="card">
  <h2>Recent imports</h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th>File</th><th>Status</th><th class="right">Invoices</th><th class="right">Skipped</th><th>When</th><th></th></tr></thead>
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
              <form method="post" action="<?= site_url('purchases/jambix/' . $b['id'] . '/revert') ?>" style="display:inline"
                onsubmit="return confirm('Delete every unpaid invoice from this batch?')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit">Revert</button>
              </form>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('purchases/jambix/' . $b['id'] . '/map') ?>">Resume</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted">No imports yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
