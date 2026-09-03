<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= lang('Import.import_batch_h', [$batch['id']]) ?></h1>
    <div class="muted small">
      <?= esc($batch['filename']) ?> · <?= lang('Import.lc_sheet') ?> <?= esc($batch['sheet']) ?> ·
      <?= esc($batch['status']) ?><?= $batch['committed_at'] ? ' ' . esc($batch['committed_at']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('journals/import') ?>"><?= lang('Import.all_imports') ?></a>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_imported') ?></div><div class="k-value mono"><?= (int) $batch['journal_count'] ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_draft') ?></div><div class="k-value mono"><?= $counts['draft'] ?? 0 ?></div></div>
  <div class="kpi pos"><div class="k-label"><?= lang('Import.kpi_posted') ?></div><div class="k-value mono"><?= $counts['posted'] ?? 0 ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_skipped_import') ?></div><div class="k-value mono"><?= (int) $batch['skipped_count'] ?></div></div>
</div>

<div class="card">
  <div class="btn-group">
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.post')): ?>
      <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/post-all') ?>"
        onsubmit="return confirm('<?= esc(lang('Import.ji_post_all_confirm', [$counts['draft']]), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= lang('Import.post_all_drafts', [$counts['draft']]) ?></button>
      </form>
    <?php endif ?>
    <?php if (($counts['draft'] ?? 0) > 0 && user_can('journal.delete')): ?>
      <form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/revert') ?>"
        onsubmit="return confirm('<?= esc(lang('Import.ji_del_confirm'), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit"><?= lang('Import.ji_del_drafts') ?></button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Import.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('App.description') ?></th><th class="right"><?= lang('Import.col_amount_base', [base_code()]) ?></th><th><?= lang('App.status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($journals as $j): ?>
        <tr>
          <td class="mono nowrap"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
          <td><?= esc($j['description']) ?></td>
          <td class="right mono"><?= money($j['total_debit']) ?></td>
          <td><?= status_badge($j['status']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $journals): ?><tr><td colspan="5" class="muted"><?= lang('Import.ji_no_batch') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
