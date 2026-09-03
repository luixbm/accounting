<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= lang('Import.bk_h') ?></h1>
    <div class="muted small"><?= lang('Import.bk_note') ?></div>
  </div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking') ?>">&lsaquo; <?= lang('Import.bk_banking') ?></a>
    <?php if (user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('banking/reconcile/new') ?>"><?= lang('Import.bk_import_stmt') ?></a>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Import.bk_c_stmt_date') ?></th><th><?= lang('Import.bk_c_bank_acct') ?></th>
        <th class="right"><?= lang('Import.bk_c_opening') ?></th><th class="right"><?= lang('Import.bk_c_closing') ?></th>
        <th><?= lang('Import.bk_c_note') ?></th><th><?= lang('App.status') ?></th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($statements as $s): ?>
        <tr>
          <td class="nowrap"><?= date_id($s['statement_date']) ?></td>
          <td><?= esc($accts[(int) $s['bank_account_id']] ?? ('#' . $s['bank_account_id'])) ?></td>
          <td class="right mono"><?= money_c($s['opening_balance']) ?></td>
          <td class="right mono"><?= money_c($s['closing_balance']) ?></td>
          <td class="muted small"><?= esc($s['note']) ?></td>
          <td>
            <?php if ($s['status'] === 'reconciled'): ?>
              <span class="badge badge-green"><?= lang('Import.bk_reconciled') ?></span>
            <?php else: ?>
              <span class="badge badge-gray"><?= lang('Import.bk_draft') ?></span>
            <?php endif ?>
          </td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('banking/reconcile/' . $s['id']) ?>"><?= lang('Import.open') ?></a></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $statements): ?>
        <tr><td colspan="7" class="muted"><?= lang('Import.bk_no_stmts') ?></td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
