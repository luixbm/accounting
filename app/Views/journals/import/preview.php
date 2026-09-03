<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s   = $parsed['summary'];
$canCommit = $s['ok'] > 0 && $batch['status'] !== 'committed' && user_can('journal.create');
?>

<div class="page-head">
  <div><h1><?= lang('Import.crumb') ?> · <?= lang('Import.preview') ?></h1><div class="muted small"><?= esc($batch['filename']) ?> · <?= lang('Import.lc_sheet') ?> <?= esc($batch['sheet']) ?></div></div>
</div>
<?= view('journals/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Import.ji_journals_found') ?></div><div class="k-value mono"><?= $s['groups'] ?></div></div>
  <div class="kpi pos"><div class="k-label"><?= lang('Import.kpi_ready_import') ?></div><div class="k-value mono"><?= $s['ok'] ?></div></div>
  <div class="kpi <?= $s['error'] ? 'neg' : '' ?>"><div class="k-label"><?= lang('Import.kpi_with_errors') ?></div><div class="k-value mono"><?= $s['error'] ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_skipped_exist') ?></div><div class="k-value mono"><?= $s['skip'] ?></div></div>
</div>

<?php if ($parsed['unmapped']): ?>
  <div class="alert alert-error">
    <?= lang('Import.ji_unmatched_note', [count($parsed['unmapped'])]) ?>
    <?= esc(implode(', ', array_slice($parsed['unmapped'], 0, 20))) ?><?= count($parsed['unmapped']) > 20 ? '…' : '' ?>
    — <a href="<?= site_url('journals/import/' . $batch['id'] . '/accounts') ?>"><?= lang('Import.fix_mapping') ?></a>.
  </div>
<?php endif ?>

<?php if ($parsed['newCustomers'] || $parsed['newSuppliers']): ?>
  <div class="alert alert-success">
    <?= lang('Import.will_create') ?>
    <?php if ($parsed['newCustomers']): ?><strong><?= count($parsed['newCustomers']) ?></strong> <?= lang('Import.ji_customers_pl') ?><?php endif ?>
    <?php if ($parsed['newCustomers'] && $parsed['newSuppliers']): ?> <?= lang('Import.and') ?> <?php endif ?>
    <?php if ($parsed['newSuppliers']): ?><strong><?= count($parsed['newSuppliers']) ?></strong> <?= lang('Import.ji_suppliers_pl') ?><?php endif ?>
    <?= lang('Import.ji_from_name_col') ?>
    <span class="small muted"><?= esc(implode(', ', array_slice(array_merge($parsed['newCustomers'], $parsed['newSuppliers']), 0, 25))) ?></span>
  </div>
<?php endif ?>

<div class="card">
  <?php if ($canCommit): ?>
    <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/commit') ?>"
      onsubmit="return confirm('<?= esc(lang('Import.ji_import_confirm', [$s['ok']]), 'js') ?>')">
      <?= csrf_field() ?>
      <button class="btn" type="submit"><?= lang('Import.ji_import_btn', [$s['ok']]) ?></button>
      <span class="muted small"><?= lang('Import.ji_skip_note') ?></span>
    </form>
  <?php elseif ($batch['status'] === 'committed'): ?>
    <a class="btn" href="<?= site_url('journals/import/' . $batch['id']) ?>"><?= lang('Import.view_imported_batch') ?></a>
  <?php else: ?>
    <span class="muted"><?= lang('Import.ji_nothing') ?></span>
  <?php endif ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr><th><?= lang('Import.ji_col_journal') ?></th><th><?= lang('App.date') ?></th><th><?= lang('App.description') ?></th><th><?= lang('App.currency') ?></th><th class="right"><?= lang('Import.col_total_base', [base_code()]) ?></th><th class="center"><?= lang('Import.col_lines') ?></th><th><?= lang('App.status') ?></th><th><?= lang('Import.col_notes') ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($parsed['journals'] as $j): ?>
        <tr>
          <td class="mono nowrap"><?= esc($j['no'] ?? '(auto)') ?><div class="small muted"><?= esc($j['key']) ?></div></td>
          <td class="nowrap"><?= date_id($j['date']) ?></td>
          <td><?= esc(mb_strimwidth($j['description'], 0, 46, '…')) ?></td>
          <td class="small"><?= esc($j['currency']) ?><?= $j['rate'] != 1 ? ' @' . money($j['rate'], 2) : '' ?></td>
          <td class="right mono"><?= money($j['total_base']) ?></td>
          <td class="center"><?= count($j['lines']) ?></td>
          <td><?php
              $badge = $j['status'] === 'ok' ? 'badge badge-green' : ($j['status'] === 'skip' ? 'badge badge-gray' : 'badge badge-red');
          echo '<span class="' . $badge . '">' . esc($j['status']) . '</span>'; ?></td>
          <td class="small">
            <?php foreach ($j['errors'] as $e): ?><div style="color:var(--red)"><?= esc($e) ?></div><?php endforeach ?>
            <?php foreach ($j['warnings'] as $w): ?><div class="muted"><?= esc($w) ?></div><?php endforeach ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $parsed['journals']): ?><tr><td colspan="8" class="muted"><?= lang('Import.ji_no_parsed') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
