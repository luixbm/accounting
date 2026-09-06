<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $row !== null;
$action = $isEdit ? site_url('journals/recurring/' . $row['id']) : site_url('journals/recurring');
$v      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>

    <div class="field">
      <label><?= lang('Recurring.f_name') ?></label>
      <input name="name" maxlength="80" required value="<?= esc($v('name')) ?>" placeholder="<?= esc(lang('Recurring.f_name_ph'), 'attr') ?>">
    </div>

    <div class="field">
      <label><?= lang('Recurring.f_description') ?></label>
      <input name="description" maxlength="255" required value="<?= esc($v('description')) ?>"
        placeholder="<?= esc(lang('Recurring.f_description_ph'), 'attr') ?>">
      <div class="small muted"><?= lang('Recurring.f_description_hint') ?></div>
    </div>

    <div class="row">
      <div class="field" style="max-width:220px">
        <label><?= lang('Txn.source') ?></label>
        <select name="source">
          <?php foreach ($sources as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $v('source', 'general') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:130px">
        <label><?= lang('App.currency') ?></label>
        <select name="currency_id">
          <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string) $v('currency_id', $baseId) === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label><?= lang('Txn.reference_invoice') ?> <span class="muted"><?= lang('App.optional') ?></span></label>
        <input name="reference" maxlength="100" value="<?= esc($v('reference')) ?>">
      </div>
    </div>

    <div class="row">
      <div class="field" style="max-width:170px">
        <label><?= lang('Recurring.f_frequency') ?></label>
        <select name="frequency">
          <?php foreach ($freqs as $f): ?>
            <option value="<?= $f ?>" <?= $v('frequency', 'monthly') === $f ? 'selected' : '' ?>><?= lang('Recurring.freq_' . $f) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:190px">
        <label><?= lang('Recurring.f_next_date') ?></label>
        <input type="date" name="next_date" value="<?= esc($v('next_date')) ?>">
        <div class="small muted"><?= lang('Recurring.f_next_date_hint') ?></div>
      </div>
    </div>

    <label class="inline" style="font-weight:400;margin-top:6px">
      <input type="checkbox" name="is_active" value="1" style="width:auto" <?= $v('is_active', 1) ? 'checked' : '' ?>>
      <span><?= lang('Recurring.f_active') ?></span>
    </label>

    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= $isEdit ? site_url('journals/recurring/' . $row['id']) : site_url('journals/recurring') ?>"><?= lang('App.cancel') ?></a>
      <?php if (! $isEdit): ?><span class="muted small" style="align-self:center"><?= lang('Recurring.after_save_hint') ?></span><?php endif ?>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
