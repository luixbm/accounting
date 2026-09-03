<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$map    = $opt['map'] ?? [];
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">' . lang('Import.not_mapped_opt') . '</option>';
    foreach ($headers as $i => $label) {
        $sel = (string) $selected === (string) $i ? ' selected' : '';
        $h .= '<option value="' . $i . '"' . $sel . '>' . esc($label) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div><h1><?= lang('Import.crumb') ?> · <?= lang('Import.step_map') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
</div>
<?= view('journals/import/_steps', ['active' => 'map', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="row">
      <div class="field" style="max-width:260px">
        <label><?= lang('Import.sheet') ?></label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $batch['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
        <span class="small muted"><?= lang('Import.reload_sheet_note') ?></span>
      </div>
      <div class="field" style="max-width:140px">
        <label><?= lang('Import.header_row') ?></label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
      <div class="field" style="max-width:200px">
        <label><?= lang('Import.date_format_file') ?></label>
        <select name="date_format">
          <?php foreach (['auto' => lang('Import.auto_detect'), 'dmy' => 'DD/MM/YYYY', 'mdy' => 'MM/DD/YYYY', 'ymd' => 'YYYY-MM-DD'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= ($opt['dateFormat'] ?? 'auto') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:220px">
        <label><?= lang('Import.ji_default_source') ?></label>
        <select name="default_source">
          <?php foreach ($sources as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= ($opt['defaultSource'] ?? 'general') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <h2><?= lang('Import.column_mapping') ?></h2>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="min-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
    <p class="muted small"><?= lang('Import.ji_map_note') ?></p>
  </div>

  <div class="card">
    <h2><?= lang('Import.first_rows_sheet') ?></h2>
    <div style="overflow-x:auto">
      <table class="grid tight mono">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr>
              <td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?>
                <td><?= esc(mb_strimwidth((string) ($r['cells'][$i] ?? ''), 0, 22, '…')) ?></td>
              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.save_continue') ?></button>
      <a class="btn ghost" href="<?= site_url('journals/import') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
