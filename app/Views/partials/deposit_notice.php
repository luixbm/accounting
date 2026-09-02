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
?>
<div class="alert alert-info">
  <strong>
    This <?= esc($noun) ?> has an unapplied <?= esc($depositWord) ?> —
    <?= esc(implode(' + ', $totParts)) ?> still to settle.
  </strong>
  <ul>
    <?php foreach ($deposits as $d): ?>
      <li>
        <span class="mono"><?= esc($d['no']) ?></span>
        · <?= $d['date'] ? date_id($d['date']) : '' ?>
        · <?= money((float) $d['unapplied'], 2) ?> <?= esc($d['currency_code']) ?> unapplied
        &nbsp;<a href="<?= site_url($applyBase . '/' . $d['id'] . '/apply') ?>">apply to invoices &rsaquo;</a>
      </li>
    <?php endforeach ?>
  </ul>
  <span class="small">Apply the <?= esc($depositWord) ?> to the invoices below <em>before</em> recording this
    <?= $noun === 'supplier' ? 'payment' : 'receipt' ?>, so the same amount isn't settled twice.</span>
</div>
