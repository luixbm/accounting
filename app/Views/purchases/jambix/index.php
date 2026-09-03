<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Import.jx_h') ?></h1><div class="muted small"><?= lang('Import.jx_note') ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases') ?>"><?= lang('Import.jx_back') ?></a></div>
</div>

<div class="card" style="max-width:660px">
  <h2><?= lang('Import.new_import') ?></h2>
  <form method="post" action="<?= site_url('purchases/jambix') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label><?= lang('Import.jx_file') ?></label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button class="btn" type="submit"><?= lang('Import.upload_continue') ?></button>
  </form>
  <p class="muted small" style="margin-top:10px">
    <?= lang('Import.jx_upload_note') ?>
  </p>
</div>

<div class="card">
  <h2><?= lang('Import.recent_imports') ?></h2>
  <table class="grid tight">
    <thead><tr><th>#</th><th><?= lang('Import.col_file') ?></th><th><?= lang('App.status') ?></th><th class="right"><?= lang('Import.jx_col_invoices') ?></th><th class="right"><?= lang('Import.jx_col_skipped') ?></th><th><?= lang('Import.col_when') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <?php $committed = $b['status'] === 'committed'; ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><?= esc($b['filename']) ?></td>
          <td><?= status_badge($committed ? 'posted' : 'draft') ?> <span class="small muted"><?= esc($b['status']) ?></span></td>
          <td class="right mono"><?= $committed ? (int) $b['journal_count'] : '' ?></td>
          <td class="right mono"><?= $committed ? (int) $b['skipped_count'] : '' ?></td>
          <td class="small muted"><?= esc($b['committed_at'] ?: $b['created_at']) ?></td>
          <td class="right nowrap">
            <?php if ($committed): ?>
              <form method="post" action="<?= site_url('purchases/jambix/' . $b['id'] . '/revert') ?>" style="display:inline"
                onsubmit="return confirm('<?= esc(lang('Import.jx_revert_confirm'), 'js') ?>')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit"><?= lang('Import.revert') ?></button>
              </form>
            <?php else: ?>
              <a class="btn sm ghost" href="<?= site_url('purchases/jambix/' . $b['id'] . '/map') ?>"><?= lang('Import.resume') ?></a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $batches): ?><tr><td colspan="7" class="muted"><?= lang('Import.no_imports') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
