<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isEdit = ! empty($field['id']);
$v      = static fn (string $k, $d = '') => old($k, $field[$k] ?? $d);
$action = $isEdit ? site_url('custom-fields/' . $field['id']) : site_url('custom-fields');
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field">
        <label><?= lang('Setup.cf_applies_to') ?></label>
        <select name="entity" <?= $isEdit ? 'disabled' : '' ?>>
          <?php foreach ($entities as $key => $label): ?>
            <option value="<?= $key ?>" <?= $v('entity') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach ?>
        </select>
        <?php if ($isEdit): ?><input type="hidden" name="entity" value="<?= esc($field['entity']) ?>"><?php endif ?>
      </div>
      <div class="field" style="max-width:200px">
        <label><?= lang('Setup.cf_key') ?> <span class="muted"><?= lang('Setup.cf_key_hint') ?></span></label>
        <input name="field_key" class="mono" value="<?= esc($v('field_key')) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
      </div>
    </div>
    <div class="row">
      <div class="field"><label><?= lang('Setup.cf_label') ?></label><input name="label" value="<?= esc($v('label')) ?>" required></div>
      <div class="field" style="max-width:170px">
        <label><?= lang('Report.col_type') ?></label>
        <select name="type" id="cfType">
          <?php foreach (['text' => lang('Setup.cf_type_text'), 'textarea' => lang('Setup.cf_type_textarea'), 'number' => lang('Setup.cf_type_number'), 'date' => lang('Setup.cf_type_date'), 'select' => lang('Setup.cf_type_select'), 'checkbox' => lang('Setup.cf_type_checkbox')] as $k => $l): ?>
            <option value="<?= $k ?>" <?= $v('type') === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <div class="field" id="cfOptions" style="<?= $v('type') === 'select' ? '' : 'display:none' ?>">
      <label><?= lang('Setup.cf_dropdown_opts') ?> <span class="muted"><?= lang('Setup.cf_one_per_line') ?></span></label>
      <textarea name="options" rows="3"><?= esc($v('options')) ?></textarea>
    </div>
    <div class="field"><label><?= lang('Setup.cf_help_text') ?></label><input name="help" value="<?= esc($v('help')) ?>"></div>
    <div class="row">
      <div class="field" style="max-width:120px"><label><?= lang('Setup.cf_sort_order') ?></label><input type="number" name="sort_order" value="<?= (int) $v('sort_order', 0) ?>"></div>
    </div>
    <div class="inline">
      <label class="inline" style="font-weight:400"><input type="checkbox" name="is_required" value="1" style="width:auto" <?= $v('is_required') ? 'checked' : '' ?>> <?= lang('Setup.cf_required') ?></label>
      <label class="inline" style="font-weight:400"><input type="checkbox" name="show_in_list" value="1" style="width:auto" <?= $v('show_in_list') ? 'checked' : '' ?>> <?= lang('Setup.cf_show_in_list') ?></label>
      <label class="inline" style="font-weight:400"><input type="checkbox" name="is_active" value="1" style="width:auto" <?= old('is_active', $field['is_active'] ?? 1) ? 'checked' : '' ?>> <?= lang('App.active') ?></label>
    </div>

    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('custom-fields?entity=' . esc($field['entity'] ?? '')) ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<script>
  document.getElementById('cfType').addEventListener('change', function () {
    document.getElementById('cfOptions').style.display = this.value === 'select' ? '' : 'none';
  });
</script>

<?= $this->endSection() ?>
