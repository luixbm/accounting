<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s      = $parsed['summary'];
$groups = $parsed['groups'];
$un     = $parsed['unmatched'];
?>

<div class="page-head">
  <div><h1><?= lang('Import.pp_crumb_prev') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/payments/import/' . $batch['id'] . '/map') ?>"><?= lang('Import.back_to_mapping') ?></a></div>
</div>
<?= view('purchases/payments/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small"><?= lang('Import.pp_s_rows_read') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['rows'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.pp_s_suppliers') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['suppliers'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.pp_s_invoices') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['invoices'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.pp_s_capped') ?></div><div class="mono" style="font-size:1.2rem;<?= $s['capped'] ? 'color:var(--red)' : '' ?>"><?= $s['capped'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.pp_s_unmatched') ?></div><div class="mono" style="font-size:1.2rem;<?= $s['unmatched'] ? 'color:var(--red)' : '' ?>"><?= $s['unmatched'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.pp_s_pay_total', [base_code()]) ?></div><div class="mono" style="font-size:1.2rem"><?= money($s['pay_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    <?= lang('Import.pp_paying_from', [esc($bank), esc($header['payment_date'] ?? '')]) ?><?= ! empty($header['reference']) ? lang('Import.pp_ref_suffix', [esc($header['reference'])]) : '' ?>.
  </div>
</div>

<?php foreach ($groups as $g): ?>
  <div class="card">
    <h2><?= esc($g['supplier']) ?> <span class="muted small">· <?= esc($g['currency']) ?> · <?= money($g['total']) ?></span></h2>
    <table class="grid tight">
      <thead><tr><th><?= lang('Import.pp_c_invoice') ?></th><th><?= lang('App.date') ?></th><th class="right"><?= lang('Import.pp_c_outstanding') ?></th><th class="right"><?= lang('Import.pp_c_requested') ?></th><th class="right"><?= lang('Import.pp_c_will_pay') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($g['invoices'] as $iv): ?>
          <tr>
            <td class="mono nowrap"><?= esc($iv['number']) ?></td>
            <td class="small nowrap"><?= esc($iv['invoice_date']) ?></td>
            <td class="right mono"><?= money($iv['outstanding']) ?></td>
            <td class="right mono"><?= money($iv['requested']) ?></td>
            <td class="right mono" style="font-weight:600"><?= money($iv['pay']) ?></td>
            <td class="small">
              <?php if ($iv['capped']): ?><span class="badge badge-red"><?= lang('Import.st_capped') ?></span>
              <?php elseif ($iv['partial']): ?><span class="badge badge-amber"><?= lang('Import.st_partial') ?></span>
              <?php else: ?><span class="badge badge-green"><?= lang('Import.st_full') ?></span><?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endforeach ?>

<?php if ($un): ?>
  <div class="card">
    <h2 style="color:var(--red)"><?= lang('Import.pp_unmatched_h', [count($un)]) ?></h2>
    <table class="grid tight">
      <thead><tr><th><?= lang('Import.pp_c_row') ?></th><th><?= lang('Import.pp_c_number') ?></th><th class="right"><?= lang('App.amount') ?></th><th><?= lang('Import.pp_c_reason') ?></th></tr></thead>
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
    <p class="muted"><?= lang('Import.pp_nothing') ?></p>
    <a class="btn ghost" href="<?= site_url('purchases/payments/import/' . $batch['id'] . '/map') ?>"><?= lang('Import.back_to_mapping') ?></a>
  <?php else: ?>
    <form method="post" action="<?= site_url('purchases/payments/import/' . $batch['id'] . '/commit') ?>"
      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='<?= esc(lang('Import.posting'), 'js') ?>';return confirm('<?= esc(lang('Import.pp_commit_confirm', [$s['suppliers'], number_format($s['pay_total'], 2)]), 'js') ?>');">
      <?= csrf_field() ?>
      <div class="btn-group">
        <button class="btn" type="submit"><?= lang('Import.pp_commit_btn') ?></button>
        <a class="btn ghost" href="<?= site_url('purchases/payments/import') ?>"><?= lang('App.cancel') ?></a>
      </div>
    </form>
  <?php endif ?>
</div>

<?= $this->endSection() ?>
