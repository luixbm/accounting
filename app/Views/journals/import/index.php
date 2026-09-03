<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Import.ji_h') ?></h1><div class="muted small"><?= lang('Import.ji_note') ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('journals') ?>"><?= lang('Import.ji_back') ?></a></div>
</div>

<?php if (user_can('journal.create')): ?>
  <div class="card" style="max-width:620px">
    <h2><?= lang('Import.new_import') ?></h2>
    <form method="post" action="<?= site_url('journals/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label><?= lang('Import.spreadsheet_file') ?></label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
      </div>
      <button class="btn" type="submit"><?= lang('Import.upload_continue') ?></button>
    </form>
    <p class="muted small" style="margin-top:10px">
      <?= lang('Import.ji_upload_note') ?>
    </p>
  </div>
<?php endif ?>

<div class="card">
  <h2><?= lang('Import.recent_imports') ?></h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th><?= lang('Import.col_file') ?></th><th><?= lang('Import.col_sheet') ?></th><th><?= lang('App.status') ?></th><th class="right"><?= lang('Import.ji_col_journals') ?></th><th><?= lang('Import.col_when') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td><?= status_badge($b['status'] === 'committed' ? 'posted' : ($b['status'] === 'reverted' ? 'void' : 'draft')) ?>
            <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right nowrap">
            <?php if ($b['status'] === 'committed' || $b['status'] === 'reverted'): ?>
              <a class="btn sm ghost" href="<?= site_url('journals/import/' . $b['id']) ?>"><?= lang('Import.open') ?></a>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('journals/import/' . $b['id'] . '/map') ?>"><?= lang('Import.resume') ?></a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted"><?= lang('Import.no_imports') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
