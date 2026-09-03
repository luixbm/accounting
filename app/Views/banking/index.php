<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Nav.banking') ?></h1><div class="muted small"><?= lang('Txn.banking_sub') ?></div></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>"><?= lang('Txn.reconcile') ?></a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn ghost" href="<?= site_url('banking/transfer') ?>"><?= lang('Txn.transfer') ?></a>
      <a class="btn ghost" href="<?= site_url('banking/receive') ?>"><?= lang('Txn.receive_money') ?></a>
      <a class="btn" href="<?= site_url('banking/spend') ?>"><?= lang('Txn.spend_money') ?></a>
    <?php endif ?>
  </div>
</div>

<div class="row">
  <div class="card" style="flex:1.4;min-width:380px">
    <h2><?= lang('Txn.cash_balances') ?> <span class="muted small"><?= lang('Txn.today') ?></span></h2>
    <table class="grid tight">
      <tbody>
        <?php $tot = 0;
        foreach ($cash as $c): $tot += $c['balance']; ?>
          <tr>
            <td><?= esc($c['name']) ?></td>
            <td class="right mono nowrap"><?= money_c($c['balance']) ?></td>
            <?php if (user_can('reports.view')): ?>
              <td class="right nowrap" style="width:1%"><a class="btn sm ghost" href="<?= site_url('reports/bank-book?account_id=' . $c['id']) ?>"><?= lang('Txn.bank_book') ?></a></td>
            <?php endif ?>
          </tr>
        <?php endforeach ?>
        <?php if (! $cash): ?><tr><td class="muted"><?= lang('Txn.no_cash_flagged') ?></td></tr><?php endif ?>
      </tbody>
      <tfoot><tr><td><?= lang('App.total') ?></td><td class="right mono nowrap"><?= money_c($tot) ?></td><?php if (user_can('reports.view')): ?><td></td><?php endif ?></tr></tfoot>
    </table>
  </div>

  <div class="card" style="flex:1.6;min-width:340px">
    <h2><?= lang('Txn.recent_cash_moves') ?></h2>
    <table class="grid tight">
      <thead><tr><th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('App.description') ?></th><th class="right"><?= lang('App.amount') ?></th><th><?= lang('App.status') ?></th></tr></thead>
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
        <?php if (! $recent): ?><tr><td colspan="5" class="muted"><?= lang('Txn.nothing_yet') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
