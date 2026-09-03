<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Import.ai_h') ?></h1><div class="muted small"><?= lang('Import.ai_note') ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts') ?>"><?= lang('Import.ai_back') ?></a></div>
</div>

<div class="card" style="max-width:640px">
  <h2><?= lang('Import.new_import') ?></h2>
  <form method="post" action="<?= site_url('accounts/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label><?= lang('Import.spreadsheet_file') ?></label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit"><?= lang('Import.upload_continue') ?></button>
  </form>
  <p class="muted small" style="margin-top:10px">
    <?= lang('Import.ai_upload_note') ?>
  </p>
</div>

<div class="card">
  <h2><?= lang('Import.recent_imports') ?></h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th><?= lang('Import.col_file') ?></th><th><?= lang('Import.col_sheet') ?></th><th><?= lang('App.status') ?></th><th class="right"><?= lang('Import.ai_col_accounts') ?></th><th><?= lang('Import.col_when') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td class="small"><?= esc($b['sheet']) ?></td>
          <td><?= status_badge($b['status'] === 'committed' ? 'posted' : 'draft') ?> <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $b['status'] === 'committed' ? (int) $b['journal_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['created_at']) ?></td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('accounts/import/' . $b['id'] . '/map') ?>"><?= $b['status'] === 'committed' ? lang('Import.rerun') : lang('Import.resume') ?></a></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted"><?= lang('Import.no_imports') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
