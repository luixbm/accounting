<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s     = $parsed['summary'];
$rows  = $parsed['jobs'];
$shown = array_slice($rows, 0, 500);
?>

<div class="page-head">
  <div><h1><?= lang('Import.jb_crumb_prev') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('jobs/import/' . $batch['id'] . '/map') ?>"><?= lang('Import.back_to_mapping') ?></a></div>
</div>
<?= view('jobs/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small"><?= lang('Import.jb_s_source_rows') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['rows'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_jobs_new') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['jobs_new'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_jobs_update') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['jobs_update'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.kpi_errors') ?></div><div class="mono" style="font-size:1.2rem;<?= $s['jobs_error'] ? 'color:var(--red)' : '' ?>"><?= $s['jobs_error'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_new_customers') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['cust_new'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_pax_total') ?></div><div class="mono" style="font-size:1.2rem"><?= number_format((int) $s['pax_total']) ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_sales_ref', [base_code()]) ?></div><div class="mono" style="font-size:1.2rem"><?= money($s['sales_total']) ?></div></div>
    <div><div class="muted small"><?= lang('Import.jb_s_buy_ref', [base_code()]) ?></div><div class="mono" style="font-size:1.2rem"><?= money($s['buy_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    <?= lang('Import.jb_ref_note') ?>
    <?php if ($s['blank']): ?> <?= lang('Import.jb_blank_ignored', [$s['blank']]) ?><?php endif ?>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr>
        <th><?= lang('Import.jb_c_dossier') ?></th><th><?= lang('Import.jb_c_name') ?></th><th><?= lang('Import.jb_c_client') ?></th><th><?= lang('Import.jb_c_arrival') ?></th><th><?= lang('Import.jb_c_end') ?></th>
        <th class="right"><?= lang('Import.jb_c_pax') ?></th><th class="right"><?= lang('Import.jb_c_sales') ?></th><th class="right"><?= lang('Import.jb_c_buy') ?></th>
        <th class="right"><?= lang('Import.jb_c_net') ?></th><th class="right"><?= lang('Import.jb_c_margin') ?></th><th><?= lang('Import.jb_c_cat') ?></th><th><?= lang('App.status') ?></th><th><?= lang('Import.jb_c_result') ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($shown as $r): ?>
          <?php
          $net    = (float) $r['sales_ref'] - (float) $r['buy_ref'];
          $margin = (float) $r['sales_ref'] != 0.0 ? $net / (float) $r['sales_ref'] * 100 : null;
          ?>
          <tr<?= $r['action'] === 'error' ? ' style="background:var(--red-bg)"' : '' ?>>
            <td class="mono small nowrap"><?= esc($r['code']) ?></td>
            <td class="small"><?= esc($r['name']) ?></td>
            <td class="small"><?= esc($r['client']) ?><?= $r['client_new'] ? ' <span class="badge badge-gray" style="font-size:.7em">' . esc(lang('Import.new_badge')) . '</span>' : '' ?></td>
            <td class="small nowrap"><?= $r['start_date'] ? date_id($r['start_date']) : '—' ?></td>
            <td class="small nowrap"><?= $r['end_date'] ? date_id($r['end_date']) : '—' ?></td>
            <td class="right mono small"><?= $r['pax'] === null ? '' : (int) $r['pax'] ?></td>
            <td class="right mono small"><?= money((float) $r['sales_ref'], 0, true) ?></td>
            <td class="right mono small"><?= money((float) $r['buy_ref'], 0, true) ?></td>
            <td class="right mono small" style="<?= $net < 0 ? 'color:var(--red)' : '' ?>"><?= money($net, 0, true) ?></td>
            <td class="right mono small"><?= $margin === null ? '' : number_format($margin, 1) . '%' ?></td>
            <td class="small"><?= esc($r['category']) ?></td>
            <td class="small"><?= esc($r['jambix_status']) ?><?= $r['status'] === 'closed' ? ' <span class="badge badge-gray" style="font-size:.7em">' . esc(lang('Import.jb_closed')) . '</span>' : '' ?></td>
            <td class="small">
              <?php if ($r['action'] === 'error'): ?>
                <span class="badge badge-red"><?= esc(implode(' ', $r['errors'])) ?></span>
              <?php elseif ($r['action'] === 'update'): ?>
                <span class="badge badge-gray"><?= lang('Import.update_badge') ?></span>
              <?php else: ?>
                <span class="badge badge-green"><?= lang('Import.new_badge') ?></span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $shown): ?><tr><td colspan="13" class="muted"><?= lang('Import.jb_nothing') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
  <?php if (count($rows) > count($shown)): ?>
    <p class="muted small"><?= lang('Import.jb_showing', [count($shown), count($rows)]) ?></p>
  <?php endif ?>
</div>

<div class="card">
  <form method="post" action="<?= site_url('jobs/import/' . $batch['id'] . '/commit') ?>"
    onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='<?= esc(lang('Import.importing'), 'js') ?>';return confirm('<?= esc(lang('Import.jb_commit_confirm', [$s['jobs_new'], $s['jobs_update']]), 'js') ?>');">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.commit_import') ?></button>
      <a class="btn ghost" href="<?= site_url('jobs/import') ?>"><?= lang('App.cancel') ?></a>
      <span class="muted small" style="align-self:center"><?= lang('Import.large_file_note') ?></span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
