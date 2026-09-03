<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$unresolved = array_filter($rows, static fn ($r) => $r['accountId'] === null);
$kindLabel  = $kind === 'sales' ? lang('Import.kind_sales') : lang('Import.kind_purchase');
?>

<div class="page-head">
  <div><h1><?= lang('Import.inv_h', [$kindLabel]) ?> · <?= lang('Import.step_accounts') ?></h1>
    <div class="muted small"><?= lang('Import.inv_labels_line', [count($rows), count($unresolved)]) ?></div></div>
</div>
<?= view('imports/invoice/_steps', ['active' => 'accounts', 'batch' => $batch, 'base' => $base]) ?>

<form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <p class="muted small"><?= lang('Import.inv_match_note') ?></p>
    <table class="grid tight">
      <thead><tr><th style="width:40%"><?= lang('Import.match_label_col') ?></th><th><?= lang('App.account') ?></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= esc($r['label']) ?><?php if ($r['auto']): ?> <span class="badge badge-gray"><?= lang('Import.auto_confirm') ?></span><?php endif ?>
              <input type="hidden" name="label[]" value="<?= esc($r['label'], 'attr') ?>"></td>
            <td>
              <select name="account_id[]">
                <option value=""><?= lang('Import.choose_account_opt') ?></option>
                <?php foreach ($accounts as $a): ?>
                  <?php if ((int) $a['is_group'] === 1) {
                      continue;
                  } ?>
                  <option value="<?= $a['id'] ?>" <?= (int) $r['accountId'] === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= esc($a['code'] . ' · ' . $a['name']) ?></option>
                <?php endforeach ?>
              </select>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="2" class="muted"><?= lang('Import.inv_no_labels') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.save_preview') ?></button>
      <a class="btn ghost" href="<?= site_url($base . '/' . $batch['id'] . '/map') ?>"><?= lang('App.back') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
