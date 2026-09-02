<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s      = $parsed['summary'];
$groups = $parsed['groups'];
$un     = $parsed['unmatched'];
?>

<div class="page-head">
  <div><h1>Import payments · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/payments/import/' . $batch['id'] . '/map') ?>">Back to mapping</a></div>
</div>
<?= view('purchases/payments/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small">Rows read</div><div class="mono" style="font-size:1.2rem"><?= $s['rows'] ?></div></div>
    <div><div class="muted small">Suppliers → payments</div><div class="mono" style="font-size:1.2rem"><?= $s['suppliers'] ?></div></div>
    <div><div class="muted small">Invoices</div><div class="mono" style="font-size:1.2rem"><?= $s['invoices'] ?></div></div>
    <div><div class="muted small">Capped to outstanding</div><div class="mono" style="font-size:1.2rem;<?= $s['capped'] ? 'color:var(--red)' : '' ?>"><?= $s['capped'] ?></div></div>
    <div><div class="muted small">Unmatched rows</div><div class="mono" style="font-size:1.2rem;<?= $s['unmatched'] ? 'color:var(--red)' : '' ?>"><?= $s['unmatched'] ?></div></div>
    <div><div class="muted small">Total to pay (<?= base_code() ?>)</div><div class="mono" style="font-size:1.2rem"><?= money($s['pay_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    Paying from <b><?= esc($bank) ?></b> on <b><?= esc($header['payment_date'] ?? '') ?></b><?= ! empty($header['reference']) ? ' · ref “' . esc($header['reference']) . '”' : '' ?>.
  </div>
</div>

<?php foreach ($groups as $g): ?>
  <div class="card">
    <h2><?= esc($g['supplier']) ?> <span class="muted small">· <?= esc($g['currency']) ?> · <?= money($g['total']) ?></span></h2>
    <table class="grid tight">
      <thead><tr><th>Invoice</th><th>Date</th><th class="right">Outstanding</th><th class="right">Requested</th><th class="right">Will pay</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($g['invoices'] as $iv): ?>
          <tr>
            <td class="mono nowrap"><?= esc($iv['number']) ?></td>
            <td class="small nowrap"><?= esc($iv['invoice_date']) ?></td>
            <td class="right mono"><?= money($iv['outstanding']) ?></td>
            <td class="right mono"><?= money($iv['requested']) ?></td>
            <td class="right mono" style="font-weight:600"><?= money($iv['pay']) ?></td>
            <td class="small">
              <?php if ($iv['capped']): ?><span class="badge badge-red">capped</span>
              <?php elseif ($iv['partial']): ?><span class="badge badge-amber">partial</span>
              <?php else: ?><span class="badge badge-green">full</span><?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endforeach ?>

<?php if ($un): ?>
  <div class="card">
    <h2 style="color:var(--red)">Unmatched rows (<?= count($un) ?>) — skipped</h2>
    <table class="grid tight">
      <thead><tr><th>Row</th><th>Number</th><th class="right">Amount</th><th>Reason</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($un, 0, 200) as $u): ?>
          <tr><td class="muted"><?= $u['n'] ?></td><td class="mono small"><?= esc($u['number']) ?></td>
            <td class="right mono small"><?= money((float) $u['amount'], 2, true) ?></td><td class="small"><?= esc($u['why']) ?></td></tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<div class="card">
  <?php if (! $groups): ?>
    <p class="muted">Nothing to post — no rows matched an open purchase invoice.</p>
    <a class="btn ghost" href="<?= site_url('purchases/payments/import/' . $batch['id'] . '/map') ?>">Back to mapping</a>
  <?php else: ?>
    <form method="post" action="<?= site_url('purchases/payments/import/' . $batch['id'] . '/commit') ?>"
      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Posting…';return confirm('Post <?= $s['suppliers'] ?> payment(s) totalling <?= number_format($s['pay_total'], 2) ?>?');">
      <?= csrf_field() ?>
      <div class="btn-group">
        <button class="btn" type="submit">Commit — post payments</button>
        <a class="btn ghost" href="<?= site_url('purchases/payments/import') ?>">Cancel</a>
      </div>
    </form>
  <?php endif ?>
</div>

<?= $this->endSection() ?>
