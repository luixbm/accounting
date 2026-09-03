<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Bank Reconciliation</h1>
    <div class="muted small">Import a bank statement, match it to the ledger, and tie out the balance.</div>
  </div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking') ?>">&lsaquo; Banking</a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('banking/reconcile/new') ?>">Import statement</a>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th>Statement date</th><th>Bank account</th>
        <th class="right">Opening</th><th class="right">Closing</th>
        <th>Note</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($statements as $s): ?>
        <tr>
          <td class="nowrap"><?= date_id($s['statement_date']) ?></td>
          <td><?= esc($accts[(int) $s['bank_account_id']] ?? ('#' . $s['bank_account_id'])) ?></td>
          <td class="right mono"><?= money_c($s['opening_balance']) ?></td>
          <td class="right mono"><?= money_c($s['closing_balance']) ?></td>
          <td class="muted small"><?= esc($s['note']) ?></td>
          <td>
            <?php if ($s['status'] === 'reconciled'): ?>
              <span class="badge badge-green">Reconciled</span>
            <?php else: ?>
              <span class="badge badge-gray">Draft</span>
            <?php endif ?>
          </td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('banking/reconcile/' . $s['id']) ?>">Open</a></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $statements): ?>
        <tr><td colspan="7" class="muted">No statements imported yet.</td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
