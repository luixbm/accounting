<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$showBase  = $kind === 'sales' ? 'sales' : 'purchases';
$kindLabel = $kind === 'sales' ? lang('Import.kind_sales') : lang('Import.kind_purchase');
?>

<div class="page-head">
  <div><h1><?= lang('Import.inv_batch_h', [$kindLabel, $batch['id']]) ?></h1>
    <div class="muted small"><?= esc($batch['filename']) ?> · <?= esc($batch['status']) ?><?= $batch['committed_at'] ? ' ' . esc($batch['committed_at']) : '' ?></div></div>
  <div class="btn-group no-print"><a class="btn ghost" href="<?= site_url($base) ?>"><?= lang('Import.all_imports') ?></a></div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_imported') ?></div><div class="k-value mono"><?= (int) $batch['journal_count'] ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_draft') ?></div><div class="k-value mono"><?= $counts['draft'] ?? 0 ?></div></div>
  <div class="kpi pos"><div class="k-label"><?= lang('Import.kpi_posted_plus') ?></div><div class="k-value mono"><?= ($counts['posted'] ?? 0) + ($counts['partial'] ?? 0) + ($counts['paid'] ?? 0) ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_skipped') ?></div><div class="k-value mono"><?= (int) $batch['skipped_count'] ?></div></div>
</div>

<div class="card">
  <div class="btn-group">
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.post')): ?>
      <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/post-all') ?>"
        onsubmit="return confirm('<?= esc(lang('Import.inv_post_all_confirm', [$counts['draft']]), 'js') ?>')">
        <?= csrf_field() ?><button class="btn" type="submit"><?= lang('Import.post_all_drafts', [$counts['draft']]) ?></button>
      </form>
    <?php endif ?>
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.delete')): ?>
      <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/revert') ?>"
        onsubmit="return confirm('<?= esc(lang('Import.inv_del_confirm'), 'js') ?>')">
        <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('Import.inv_del_drafts') ?></button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Import.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Import.inv_col_party_ref') ?></th><th class="right"><?= lang('Import.col_total_base', [base_code()]) ?></th><th><?= lang('App.status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url($showBase . '/' . $inv['id']) ?>"><?= esc($inv['internal_no']) ?></a>
            <?php if ($inv['external_id']): ?><div class="small muted"><?= esc($inv['external_id']) ?></div><?php endif ?></td>
          <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
          <td class="small"><?= esc($inv[$kind === 'sales' ? 'customer_ref' : 'supplier_ref']) ?></td>
          <td class="right mono"><?= money($inv['total_base']) ?></td>
          <td><?= status_badge($inv['status'] === 'draft' ? 'draft' : ($inv['status'] === 'void' ? 'void' : 'posted')) ?>
            <?php if (in_array($inv['status'], ['partial', 'paid'], true)): ?><span class="small muted"><?= esc($inv['status']) ?></span><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $invoices): ?><tr><td colspan="5" class="muted"><?= lang('Import.inv_no_batch') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
