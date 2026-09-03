<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s     = $parsed['summary'];
$rows  = $parsed['invoices'];
$shown = array_slice($rows, 0, 400);
?>

<div class="page-head">
  <div><h1><?= lang('Import.jx_crumb_prev') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/jambix/' . $batch['id'] . '/map') ?>"><?= lang('Import.back_to_mapping') ?></a></div>
</div>
<?= view('purchases/jambix/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small"><?= lang('Import.jx_s_source_rows') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['lines_total'] + $s['skipped_rows'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_invoices_new') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['invoices_new'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_post_draft') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['will_post'] ?> / <?= $s['will_draft'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_already') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['invoices_exists'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.kpi_errors') ?></div><div class="mono" style="font-size:1.2rem;<?= $s['invoices_error'] ? 'color:var(--red)' : '' ?>"><?= $s['invoices_error'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_lines_dup') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['lines_total'] - $s['lines_dup'] ?> <span class="muted">(<?= $s['lines_dup'] ?>)</span></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_lines_zero') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['lines_zero'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jx_s_budget_total', [base_code()]) ?></div><div class="mono" style="font-size:1.2rem"><?= money($s['amount_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    <?= lang('Import.jx_create_note', [$s['suppliers_new'], $s['customers_new'], $s['jobs_new'], esc($acctLabel)]) ?>
    <?php if ($s['skipped_rows']): ?> <?= lang('Import.jx_skipped_status', [$s['skipped_rows']]) ?><?php endif ?>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr>
        <th><?= lang('Import.jx_c_ext_id') ?></th><th><?= lang('Import.jx_c_supplier') ?></th><th><?= lang('Import.jx_c_client') ?></th><th><?= lang('Import.jx_c_dossier') ?></th><th><?= lang('Import.jx_c_inv_date') ?></th>
        <th class="right"><?= lang('Import.jx_c_lines') ?></th><th class="right"><?= lang('Import.jx_c_budget') ?></th><th><?= lang('Import.jx_c_result') ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($shown as $r): ?>
          <tr<?= $r['action'] === 'error' ? ' style="background:var(--red-bg)"' : ($r['action'] === 'exists' ? ' class="muted"' : '') ?>>
            <td class="mono small nowrap"><?= esc($r['external_id']) ?></td>
            <td><?= esc($r['supplier']) ?><?= $r['supplier_new'] ? ' <span class="badge badge-gray" style="font-size:.7em">' . esc(lang('Import.new_badge')) . '</span>' : '' ?></td>
            <td class="small"><?= esc($r['client']) ?></td>
            <td class="mono small"><?= esc($r['doss_nr']) ?></td>
            <td class="small nowrap"><?= $r['invoice_date'] ? date_id($r['invoice_date']) : '—' ?></td>
            <td class="right mono small">
              <?= count($r['live_lines']) ?><?php if ($r['lines_dup']): ?> <span class="muted">+<?= $r['lines_dup'] ?> <?= esc(lang('Import.jx_dup')) ?></span><?php endif ?>
              <?php if ($r['lines_zero']): ?> <span class="muted">· <?= $r['lines_zero'] ?> @0</span><?php endif ?>
            </td>
            <td class="right mono"><?= money($r['amount_total']) ?></td>
            <td class="small">
              <?php if ($r['action'] === 'error'): ?>
                <span class="badge badge-red"><?= esc(implode(' ', $r['errors'])) ?></span>
              <?php elseif ($r['action'] === 'exists'): ?>
                <span class="badge badge-gray"><?= lang('Import.jx_already_imported') ?></span>
              <?php elseif ($r['will_post']): ?>
                <span class="badge badge-green"><?= lang('Import.st_post') ?></span>
              <?php else: ?>
                <span class="badge badge-gray"><?= lang('Import.jx_draft_no_cost') ?></span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if (count($rows) > count($shown)): ?>
    <p class="muted small"><?= lang('Import.jx_showing', [count($shown), count($rows)]) ?></p>
  <?php endif ?>
</div>

<div class="card">
  <form method="post" action="<?= site_url('purchases/jambix/' . $batch['id'] . '/commit') ?>"
    onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='<?= esc(lang('Import.importing'), 'js') ?>';return confirm('<?= esc(lang('Import.jx_commit_confirm', [$s['invoices_new'], $s['will_post']]), 'js') ?>');">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.commit_import') ?></button>
      <a class="btn ghost" href="<?= site_url('purchases/jambix') ?>"><?= lang('App.cancel') ?></a>
      <span class="muted small" style="align-self:center"><?= lang('Import.large_file_note') ?></span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
