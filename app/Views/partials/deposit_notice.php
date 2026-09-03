<?php
/**
 * Reminder banner shown on the Pay Supplier / Receive from Customer screens when
 * the chosen party has an unapplied deposit / down payment still to settle.
 *
 * @var list<array<string,mixed>> $deposits   normalised: id, no, date, unapplied, currency_code
 * @var string                    $applyBase  e.g. 'purchases/payments' or 'sales/receipts'
 * @var string                    $noun       'supplier' | 'customer'
 * @var string                    $depositWord 'deposit' | 'down payment'
 */
if (empty($deposits)) {
    return;
}
$byCcy = [];
foreach ($deposits as $d) {
    $byCcy[$d['currency_code']] = ($byCcy[$d['currency_code']] ?? 0.0) + (float) $d['unapplied'];
}
$totParts = [];
foreach ($byCcy as $code => $sum) {
    $totParts[] = money($sum, 2) . ' ' . $code;
}
$partyWord = $noun === 'supplier' ? lang('Txn.supplier') : lang('Txn.customer');
$depWord   = $depositWord === 'deposit' ? lang('Txn.deposit') : lang('Txn.down_payment');
$txnWord   = $noun === 'supplier' ? lang('Txn.payment') : lang('Txn.receipt');
?>
<div class="alert alert-info">
  <strong><?= lang('Txn.dep_notice_title', [mb_strtolower($partyWord), mb_strtolower($depWord), esc(implode(' + ', $totParts))]) ?></strong>
  <ul>
    <?php foreach ($deposits as $d): ?>
      <li>
        <span class="mono"><?= esc($d['no']) ?></span>
        · <?= $d['date'] ? date_id($d['date']) : '' ?>
        · <?= lang('Txn.dep_notice_unapplied', [money((float) $d['unapplied'], 2) . ' ' . esc($d['currency_code'])]) ?>
        &nbsp;<a href="<?= site_url($applyBase . '/' . $d['id'] . '/apply') ?>"><?= lang('Txn.dep_notice_apply') ?> &rsaquo;</a>
      </li>
    <?php endforeach ?>
  </ul>
  <span class="small"><?= lang('Txn.dep_notice_warn', [mb_strtolower($depWord), mb_strtolower($txnWord)]) ?></span>
</div>
