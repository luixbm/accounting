<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$v = static fn (string $k, $d = '') => old($k, $account[$k] ?? $d);
$action = $account ? site_url('accounts/' . $account['id']) : site_url('accounts');
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:640px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>

    <div class="row">
      <div class="field" style="max-width:160px">
        <label><?= lang('Report.col_code') ?></label>
        <input name="code" class="mono" value="<?= esc($v('code')) ?>" required>
      </div>
      <div class="field">
        <label><?= lang('Setup.account_name') ?></label>
        <input name="name" value="<?= esc($v('name')) ?>" required>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label><?= lang('Report.col_type') ?></label>
        <select name="type" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= $key ?>" <?= $v('type') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:180px">
        <label><?= lang('Setup.normal_balance') ?></label>
        <select name="normal_balance">
          <option value="D" <?= $v('normal_balance') === 'D' ? 'selected' : '' ?>><?= lang('Setup.debit_d') ?></option>
          <option value="K" <?= $v('normal_balance') === 'K' ? 'selected' : '' ?>><?= lang('Setup.credit_k') ?></option>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label><?= lang('Setup.parent_header') ?></label>
        <select name="parent_id">
          <option value=""><?= lang('App.none') ?></option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (string) $v('parent_id') === (string) $p['id'] ? 'selected' : '' ?>>
              <?= esc($p['code'] . ' · ' . $p['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label><?= lang('Setup.subledger') ?></label>
        <select name="subledger">
          <option value="none" <?= $v('subledger', 'none') === 'none' ? 'selected' : '' ?>><?= lang('Setup.sl_none') ?></option>
          <option value="customer" <?= $v('subledger') === 'customer' ? 'selected' : '' ?>><?= lang('Setup.sl_customer') ?></option>
          <option value="supplier" <?= $v('subledger') === 'supplier' ? 'selected' : '' ?>><?= lang('Setup.sl_supplier') ?></option>
        </select>
      </div>
      <div class="field">
        <label><?= lang('Setup.cashflow_section') ?></label>
        <select name="cashflow">
          <?php foreach (\App\Models\AccountModel::CASHFLOW as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $v('cashflow', 'operating') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label><?= lang('Setup.denomination_ccy') ?></label>
        <select name="currency_id">
          <option value=""><?= lang('Setup.base_opt') ?></option>
          <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string) $v('currency_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label><?= lang('App.description') ?></label>
      <input name="description" value="<?= esc($v('description')) ?>">
    </div>

    <fieldset>
      <legend><?= lang('Setup.flags') ?></legend>
      <div class="inline">
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_group" value="1" style="width:auto" <?= $v('is_group') ? 'checked' : '' ?>> <?= lang('Setup.flag_header_full') ?>
        </label>
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_cash" value="1" style="width:auto" <?= $v('is_cash') ? 'checked' : '' ?>> <?= lang('Setup.flag_cash_full') ?>
        </label>
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_active" value="1" style="width:auto" <?= old('is_active', $account['is_active'] ?? 1) ? 'checked' : '' ?>> <?= lang('App.active') ?>
        </label>
      </div>
    </fieldset>

    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('accounts') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?php if ($account): ?>
  <div class="card" style="max-width:640px">
    <h2><?= lang('Setup.danger_zone') ?></h2>
    <div class="btn-group">
      <form method="post" action="<?= site_url('accounts/' . $account['id'] . '/toggle') ?>">
        <?= csrf_field() ?>
        <button class="btn ghost" type="submit"><?= $account['is_active'] ? lang('Setup.deactivate') : lang('Setup.reactivate') ?></button>
      </form>
      <form method="post" action="<?= site_url('accounts/' . $account['id'] . '/delete') ?>"
        onsubmit="return confirm('<?= esc(lang('Setup.delete_account_confirm', [esc($account['code'], 'attr')]), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
      </form>
      <span class="muted small" style="align-self:center"><?= lang('Setup.danger_note') ?></span>
    </div>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
