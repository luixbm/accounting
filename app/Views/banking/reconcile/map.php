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
$mode = $opt['amountMode'] ?? 'credit_in';
?>

<div class="page-head">
  <div><h1><?= lang('Import.bk_crumb_map') ?></h1><div class="muted small"><?= esc($st['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('banking/reconcile') ?>"><?= lang('App.cancel') ?></a></div>
</div>

<form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="row">
      <div class="field" style="max-width:240px">
        <label><?= lang('Import.sheet') ?></label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $st['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:120px">
        <label><?= lang('Import.header_row') ?></label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
      <div class="field" style="max-width:170px">
        <label><?= lang('Import.date_format') ?></label>
        <select name="date_format">
          <?php foreach (['auto' => lang('Import.auto'), 'dmy' => 'D/M/Y', 'mdy' => 'M/D/Y', 'ymd' => 'Y/M/D'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($opt['dateFormat'] ?? 'auto') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:240px">
        <label><?= lang('Import.bk_amount_conv') ?></label>
        <select name="amount_mode">
          <option value="credit_in" <?= $mode === 'credit_in' ? 'selected' : '' ?>><?= lang('Import.bk_credit_in') ?></option>
          <option value="debit_in" <?= $mode === 'debit_in' ? 'selected' : '' ?>><?= lang('Import.bk_debit_in') ?></option>
        </select>
      </div>
    </div>
    <p class="muted small"><?= lang('Import.bk_map_note') ?></p>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="card">
    <h2><?= lang('Import.first_rows') ?></h2>
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr>
              <td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc((string) ($r['cells'][$i] ?? '')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
          <?php if (! $sample): ?><tr><td colspan="<?= count($headers) + 1 ?>" class="muted"><?= lang('Import.bk_no_data_rows') ?></td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.bk_import_automatch') ?></button>
      <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
