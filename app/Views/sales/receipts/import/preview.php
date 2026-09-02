<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s      = $parsed['summary'];
$groups = $parsed['groups'];
$un     = $parsed['unmatched'];
$isDep  = ($parsed['mode'] ?? 'receipt') === 'deposit';
?>

<div class="page-head">
  <div><h1>Import receipts · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?> · <?= $isDep ? 'apply deposit' : 'receipt' ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('sales/receipts/import/' . $batch['id'] . '/map') ?>">Back to mapping</a></div>
</div>
<?= view('sales/receipts/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small">Rows read</div><div class="mono" style="font-size:1.2rem"><?= $s['rows'] ?></div></div>
    <div><div class="muted small">Customers → posts</div><div class="mono" style="font-size:1.2rem"><?= $s['customers'] ?></div></div>
    <div><div class="muted small">Invoices</div><div class="mono" style="font-size:1.2rem"><?= $s['invoices'] ?></div></div>
    <div><div class="muted small">Capped to outstanding</div><div class="mono" style="font-size:1.2rem;<?= $s['capped'] ? 'color:var(--red)' : '' ?>"><?= $s['capped'] ?></div></div>
    <?php if ($isDep): ?>
      <div><div class="muted small">No deposit</div><div class="mono" style="font-size:1.2rem;<?= $s['no_deposit'] ? 'color:var(--red)' : '' ?>"><?= $s['no_deposit'] ?></div></div>
      <div><div class="muted small">Over deposit balance</div><div class="mono" style="font-size:1.2rem;<?= $s['over_deposit'] ? 'color:var(--red)' : '' ?>"><?= $s['over_deposit'] ?></div></div>
    <?php endif ?>
    <div><div class="muted small">Unmatched rows</div><div class="mono" style="font-size:1.2rem;<?= $s['unmatched'] ? 'color:var(--red)' : '' ?>"><?= $s['unmatched'] ?></div></div>
    <div><div class="muted small">Total (<?= base_code() ?>)</div><div class="mono" style="font-size:1.2rem"><?= money($s['apply_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    <?= $isDep
        ? 'Applying each customer’s oldest unapplied deposit'
        : 'Receiving into <b>' . esc($bank) . '</b>' ?>
    on <b><?= esc($header['date'] ?? '') ?></b><?= ! empty($header['reference']) ? ' · ref “' . esc($header['reference']) . '”' : '' ?>.
  </div>
</div>

<?php foreach ($groups as $g): ?>
  <div class="card">
    <h2><?= esc($g['customer']) ?> <span class="muted small">· <?= esc($g['currency']) ?> · <?= money($g['total']) ?><?php
      if ($isDep && ! empty($g['deposit'])): ?> · from <?= esc($g['deposit']['receipt_no']) ?> (<?= money((float) $g['deposit']['unapplied']) ?> unapplied)<?php
      elseif ($isDep): ?> · <span style="color:var(--red)">no deposit</span><?php endif ?></span></h2>
    <table class="grid tight">
      <thead><tr><th>Invoice</th><th>Date</th><th class="right">Outstanding</th><th class="right">Requested</th><th class="right">Will <?= $isDep ? 'apply' : 'receive' ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($g['invoices'] as $iv): ?>
          <tr>
            <td class="mono nowrap"><?= esc($iv['number']) ?></td>
            <td class="small nowrap"><?= esc($iv['invoice_date']) ?></td>
            <td class="right mono"><?= money($iv['outstanding']) ?></td>
            <td class="right mono"><?= money($iv['requested']) ?></td>
            <td class="right mono" style="font-weight:600"><?= money($iv['pay']) ?></td>
            <td class="small">
              <?php if ($iv['skip']): ?><span class="badge badge-red"><?= esc($iv['skip']) ?></span>
              <?php elseif ($iv['capped']): ?><span class="badge badge-amber">capped</span>
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
    <p class="muted">Nothing to post — no rows matched an open sales invoice.</p>
    <a class="btn ghost" href="<?= site_url('sales/receipts/import/' . $batch['id'] . '/map') ?>">Back to mapping</a>
  <?php else: ?>
    <form method="post" action="<?= site_url('sales/receipts/import/' . $batch['id'] . '/commit') ?>"
      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Posting…';return confirm('Post <?= $s['customers'] ?> <?= $isDep ? 'deposit application(s)' : 'receipt(s)' ?> totalling <?= number_format($s['apply_total'], 2) ?>?');">
      <?= csrf_field() ?>
      <div class="btn-group">
        <button class="btn" type="submit">Commit — <?= $isDep ? 'apply deposits' : 'post receipts' ?></button>
        <a class="btn ghost" href="<?= site_url('sales/receipts/import') ?>">Cancel</a>
      </div>
    </form>
  <?php endif ?>
</div>

<?= $this->endSection() ?>
