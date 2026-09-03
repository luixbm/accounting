<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$map    = $opt['map'] ?? [];
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">' . lang('Import.not_mapped_opt') . '</option>';
    foreach ($headers as $i => $label) {
        $h .= '<option value="' . $i . '"' . ((string) $selected === (string) $i ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div><h1><?= lang('Import.ai_crumb_map') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts/import') ?>"><?= lang('App.cancel') ?></a></div>
</div>
<?= view('accounts/import/_steps', ['active' => 'map', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('accounts/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="row">
      <div class="field" style="max-width:240px">
        <label><?= lang('Import.sheet') ?></label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $batch['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:130px">
        <label><?= lang('Import.header_row') ?></label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
    </div>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <?php if ($rawTypes): ?>
    <div class="card">
      <h2><?= lang('Import.ai_types_h') ?></h2>
      <p class="muted small"><?= lang('Import.ai_types_note') ?></p>
      <table class="grid tight">
        <thead><tr><th><?= lang('Import.ai_in_file') ?></th><th><?= lang('Import.ai_app_type') ?></th><th><?= lang('Import.ai_cash_bank') ?></th></tr></thead>
        <tbody>
          <?php foreach ($rawTypes as $i => $raw): ?>
            <?php [$guessType, $guessCash] = $typeMap[$raw] ?? ['asset', 0]; ?>
            <tr>
              <td class="mono"><?= esc($raw) ?><input type="hidden" name="type_raw[<?= $i ?>]" value="<?= esc($raw, 'attr') ?>"></td>
              <td>
                <select name="type_app[<?= $i ?>]">
                  <?php foreach ($appTypes as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $guessType === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                  <?php endforeach ?>
                </select>
              </td>
              <td><input type="checkbox" name="type_cash[<?= $i ?>]" value="1" <?= $guessCash ? 'checked' : '' ?>></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <div class="card">
    <h2><?= lang('Import.first_rows') ?></h2>
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr><td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc((string) ($r['cells'][$i] ?? '')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
          <?php if (! $sample): ?><tr><td colspan="<?= count($headers) + 1 ?>" class="muted"><?= lang('Import.no_data_rows') ?></td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= $rawTypes ? lang('Import.save_preview') : lang('Import.save_continue') ?></button>
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
