<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$unresolved = array_filter($rows, static fn ($r) => $r['accountId'] === null);
?>

<div class="page-head">
  <div><h1><?= lang('Import.crumb') ?> · <?= lang('Import.step_accounts') ?></h1>
    <div class="muted small"><?= lang('Import.ji_labels_line', [count($rows), count($unresolved)]) ?></div>
  </div>
</div>
<?= view('journals/import/_steps', ['active' => 'accounts', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <p class="muted small"><?= lang('Import.ji_match_note') ?></p>
    <table class="grid tight">
      <thead><tr><th style="width:40%"><?= lang('Import.match_label_col') ?></th><th><?= lang('App.account') ?></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td>
              <?= esc($r['label']) ?>
              <?php if ($r['auto']): ?><span class="badge badge-gray"><?= lang('Import.auto_matched') ?></span><?php endif ?>
              <input type="hidden" name="label[]" value="<?= esc($r['label'], 'attr') ?>">
            </td>
            <td>
              <select name="account_id[]">
                <option value=""><?= lang('Import.choose_account_opt') ?></option>
                <?php foreach ($accounts as $a): ?>
                  <?php if ((int) $a['is_group'] === 1) {
                      continue;
                  } ?>
                  <option value="<?= $a['id'] ?>" <?= (int) $r['accountId'] === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= esc($a['code'] . ' · ' . $a['name']) ?>
                  </option>
                <?php endforeach ?>
              </select>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="2" class="muted"><?= lang('Import.ji_no_labels') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.save_preview') ?></button>
      <a class="btn ghost" href="<?= site_url('journals/import/' . $batch['id'] . '/map') ?>"><?= lang('App.back') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
