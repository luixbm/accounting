<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">' . lang('Import.not_mapped_opt') . '</option>';
    foreach ($headers as $i => $label) {
        $h .= '<option value="' . $i . '"' . ((string) $selected === (string) $i ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
$mode = $header['mode'] ?? 'receipt';
?>

<div class="page-head">
  <div><h1><?= lang('Import.rc_crumb_map') ?></h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('sales/receipts/import') ?>"><?= lang('App.cancel') ?></a></div>
</div>
<?= view('sales/receipts/import/_steps', ['active' => 'map', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('sales/receipts/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <h2><?= lang('Import.rc_mode_h') ?></h2>
    <div class="row">
      <label class="field inline" style="gap:6px;align-self:center;white-space:nowrap">
        <input type="radio" name="mode" value="receipt" style="width:auto" <?= $mode !== 'deposit' ? 'checked' : '' ?> onclick="document.getElementById('bankRow').style.display=''">
        <span><?= lang('Import.rc_mode_receipt') ?></span>
      </label>
      <label class="field inline" style="gap:6px;align-self:center;white-space:nowrap">
        <input type="radio" name="mode" value="deposit" style="width:auto" <?= $mode === 'deposit' ? 'checked' : '' ?> onclick="document.getElementById('bankRow').style.display='none'">
        <span><?= lang('Import.rc_mode_deposit') ?></span>
      </label>
    </div>
    <div class="row" style="margin-top:8px">
      <div class="field" style="max-width:160px">
        <label><?= lang('App.date') ?> *</label>
        <input type="date" name="date" value="<?= esc($header['date'] ?? date('Y-m-d'), 'attr') ?>" required>
      </div>
      <div class="field" id="bankRow" style="max-width:280px;<?= $mode === 'deposit' ? 'display:none' : '' ?>">
        <label><?= lang('Import.rc_receive_into') ?> *</label>
        <select name="bank_account_id">
          <option value=""><?= lang('Import.rc_bank_cash_opt') ?></option>
          <?php foreach ($banks as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (int) ($header['bank_account_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
              <?= esc($b['code'] . ' · ' . $b['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field"><label><?= lang('App.reference') ?></label><input name="reference" value="<?= esc($header['reference'] ?? '', 'attr') ?>"></div>
    </div>
    <p class="muted small"><?= lang('Import.rc_mode_note') ?></p>
  </div>

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
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:240px">
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
      <button class="btn" type="submit"><?= lang('Import.save_preview') ?></button>
      <a class="btn ghost" href="<?= site_url('sales/receipts/import') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
