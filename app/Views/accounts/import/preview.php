<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $s = $parsed['summary']; ?>

<div class="page-head">
  <div><h1><?= lang('Import.ai_crumb_prev') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts/import/' . $batch['id'] . '/map') ?>"><?= lang('Import.back_to_mapping') ?></a></div>
</div>
<?= view('accounts/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px">
    <div><div class="muted small"><?= lang('Import.kpi_rows') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['total'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.kpi_new') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['new'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.kpi_update') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['update'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.ai_col_group_headers') ?></div><div class="mono" style="font-size:1.2rem"><?= $s['groups'] ?></div></div>
    <div><div class="muted small"><?= lang('Import.kpi_errors') ?></div><div class="mono" style="font-size:1.2rem;<?= $s['error'] ? 'color:var(--red)' : '' ?>"><?= $s['error'] ?></div></div>
  </div>
</div>

<?php if ($s['error']): ?>
  <div class="alert alert-error"><?= lang('Import.ai_err_note') ?></div>
<?php endif ?>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr><th>#</th><th><?= lang('Import.ai_col_code') ?></th><th><?= lang('Import.ai_col_name') ?></th><th><?= lang('Import.ai_col_file_type') ?></th><th><?= lang('Import.ai_app_type') ?></th><th><?= lang('Import.ai_col_cash') ?></th><th><?= lang('Import.ai_col_group') ?></th><th><?= lang('Import.ai_col_parent') ?></th><th><?= lang('App.currency') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($parsed['rows'] as $r): ?>
          <tr<?= $r['errors'] ? ' style="background:var(--red-bg)"' : '' ?>>
            <td class="muted"><?= $r['n'] ?></td>
            <td class="mono nowrap"><?= esc($r['code']) ?></td>
            <td><?= esc($r['name']) ?></td>
            <td class="mono small"><?= esc($r['raw_type']) ?></td>
            <td class="small"><?= esc($r['type']) ?></td>
            <td class="small"><?= $r['is_cash'] ? '✓' : '' ?></td>
            <td class="small"><?= $r['is_group'] ? '✓' : '' ?></td>
            <td class="mono small"><?= esc($r['parent_code']) ?></td>
            <td class="small"><?= esc($r['currency']) ?></td>
            <td>
              <?= $r['errors']
                ? '<span class="badge badge-red">' . esc(implode(' ', $r['errors'])) . '</span>'
                : '<span class="badge badge-gray">' . esc($r['action']) . '</span>' ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <form method="post" action="<?= site_url('accounts/import/' . $batch['id'] . '/commit') ?>" onsubmit="return confirm('<?= esc(lang('Import.ai_commit_confirm', [$s['new'], $s['update']]), 'js') ?>')">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.commit_import') ?></button>
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>"><?= lang('App.cancel') ?></a>
      <span class="muted small" style="align-self:center"><?= lang('Import.ai_rows_written', [$s['ok'], $s['total']]) ?></span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
