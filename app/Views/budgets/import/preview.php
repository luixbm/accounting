<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $s = $parsed['summary']; ?>

<div class="page-head">
  <div><h1><?= lang('Budget.imp_preview') ?></h1><div class="muted small"><?= esc($batch['filename']) ?><?= $ver ? ' → ' . esc($ver['name']) . ' (' . (int) $ver['year'] . ')' : '' ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('budgets/import/' . $batch['id'] . '/map') ?>"><?= lang('App.back') ?></a></div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Budget.imp_accounts_matched') ?></div><div class="k-value mono"><?= (int) $s['accounts'] ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Budget.imp_cells') ?></div><div class="k-value mono"><?= (int) $s['cells'] ?></div></div>
  <div class="kpi <?= $s['error'] ? 'neg' : '' ?>"><div class="k-label"><?= lang('Budget.imp_errors') ?></div><div class="k-value mono"><?= (int) $s['error'] ?></div></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
  <table class="grid tight">
    <thead><tr>
      <th>#</th><th><?= lang('Report.col_account') ?></th>
      <?php for ($m = 1; $m <= 12; $m++): ?><th class="right"><?= date('M', mktime(0, 0, 0, $m, 1)) ?></th><?php endfor ?>
      <th class="right"><?= lang('App.total') ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($parsed['rows'] as $r): ?>
        <tr<?= $r['errors'] ? ' style="background:var(--red-bg,#fee)"' : '' ?>>
          <td class="muted"><?= $r['n'] ?></td>
          <td class="small"><?= esc($r['errors'] ? $r['raw'] : ($r['code'] . ' · ' . $r['name'])) ?></td>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <td class="right mono"><?= abs((float) ($r['months'][$m] ?? 0)) >= 0.005 ? money((float) $r['months'][$m], 0) : '' ?></td>
          <?php endfor ?>
          <td class="right mono"><?= money($r['total'], 0) ?></td>
          <td class="small"><?= $r['errors'] ? '<span class="badge badge-amber">' . esc(implode('; ', $r['errors'])) . '</span>' : '<span class="badge badge-green">' . lang('Budget.imp_row_ok') . '</span>' ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $parsed['rows']): ?><tr><td colspan="16" class="muted"><?= lang('App.no_records') ?></td></tr><?php endif ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card">
  <form method="post" action="<?= site_url('budgets/import/' . $batch['id'] . '/commit') ?>">
    <?= csrf_field() ?>
    <button class="btn" type="submit" <?= $s['accounts'] ? '' : 'disabled' ?>><?= lang('Budget.imp_commit', [(int) $s['accounts']]) ?></button>
    <a class="btn ghost" href="<?= site_url('budgets/' . ($ver['id'] ?? '')) ?>"><?= lang('App.cancel') ?></a>
  </form>
</div>

<?= $this->endSection() ?>
