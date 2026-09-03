<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Banking</h1><div class="muted small">Transfers and cash movements that don't run through Purchases or Sales.</div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">Reconcile</a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn ghost" href="<?= site_url('banking/transfer') ?>">Transfer</a>
      <a class="btn ghost" href="<?= site_url('banking/receive') ?>">Receive money</a>
      <a class="btn" href="<?= site_url('banking/spend') ?>">Spend money</a>
    <?php endif ?>
  </div>
</div>

<div class="row">
  <div class="card" style="flex:1.4;min-width:380px">
    <h2>Cash &amp; Bank balances <span class="muted small">today</span></h2>
    <table class="grid tight">
      <tbody>
        <?php $tot = 0;
        foreach ($cash as $c): $tot += $c['balance']; ?>
          <tr>
            <td><?= esc($c['name']) ?></td>
            <td class="right mono nowrap"><?= money_c($c['balance']) ?></td>
            <?php if (user_can('reports.view')): ?>
              <td class="right nowrap" style="width:1%"><a class="btn sm ghost" href="<?= site_url('reports/bank-book?account_id=' . $c['id']) ?>">Bank book</a></td>
            <?php endif ?>
          </tr>
        <?php endforeach ?>
        <?php if (! $cash): ?><tr><td class="muted">No accounts flagged as cash/bank.</td></tr><?php endif ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="right mono nowrap"><?= money_c($tot) ?></td><?php if (user_can('reports.view')): ?><td></td><?php endif ?></tr></tfoot>
    </table>
  </div>

  <div class="card" style="flex:1.6;min-width:340px">
    <h2>Recent cash movements</h2>
    <table class="grid tight">
      <thead><tr><th>No.</th><th>Date</th><th>Description</th><th class="right">Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $j): ?>
          <tr>
            <td class="mono nowrap"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
            <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
            <td><?= esc($j['description']) ?></td>
            <td class="right mono"><?= money_c($j['total_debit']) ?></td>
            <td><?= status_badge($j['status']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $recent): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
