<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$party     = $kind === 'sales' ? lang('Import.party_customers') : lang('Import.party_suppliers');
$kindLabel = $kind === 'sales' ? lang('Import.kind_sales') : lang('Import.kind_purchase');
$kindLc    = $kind === 'sales' ? lang('Import.kind_sales_lc') : lang('Import.kind_purchase_lc');
?>

<div class="page-head">
  <div><h1><?= lang('Import.inv_h', [$kindLabel]) ?></h1><div class="muted small"><?= lang('Import.inv_note', [$kindLc]) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url($kind === 'sales' ? 'sales' : 'purchases') ?>"><?= lang('App.back') ?></a></div>
</div>

<?php if (user_can('journal.create')): ?>
  <div class="card" style="max-width:620px">
    <h2><?= lang('Import.new_import') ?></h2>
    <form method="post" action="<?= site_url($base) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label><?= lang('Import.spreadsheet_file') ?></label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
      </div>
      <button class="btn" type="submit"><?= lang('Import.upload_continue') ?></button>
    </form>
    <p class="muted small" style="margin-top:10px">
      <?= lang('Import.inv_upload_note', [$party]) ?>
    </p>
  </div>
<?php endif ?>

<div class="card">
  <h2><?= lang('Import.recent_imports') ?></h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th><?= lang('Import.col_file') ?></th><th><?= lang('Import.col_sheet') ?></th><th><?= lang('App.status') ?></th><th class="right"><?= lang('Import.inv_col_invoices') ?></th><th><?= lang('Import.col_when') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td class="small"><?= esc($b['status']) ?></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right">
            <?php if (in_array($b['status'], ['committed', 'reverted'], true)): ?>
              <a class="btn sm ghost" href="<?= site_url($base . '/' . $b['id']) ?>"><?= lang('Import.open') ?></a>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url($base . '/' . $b['id'] . '/map') ?>"><?= lang('Import.resume') ?></a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted"><?= lang('Import.no_imports') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
