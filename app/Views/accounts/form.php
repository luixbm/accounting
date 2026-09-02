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
        <label>Code</label>
        <input name="code" class="mono" value="<?= esc($v('code')) ?>" required>
      </div>
      <div class="field">
        <label>Account Name</label>
        <input name="name" value="<?= esc($v('name')) ?>" required>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label>Type</label>
        <select name="type" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= $key ?>" <?= $v('type') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:180px">
        <label>Normal Balance</label>
        <select name="normal_balance">
          <option value="D" <?= $v('normal_balance') === 'D' ? 'selected' : '' ?>>Debit (D)</option>
          <option value="K" <?= $v('normal_balance') === 'K' ? 'selected' : '' ?>>Credit (K)</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label>Parent (header account)</label>
        <select name="parent_id">
          <option value="">— none —</option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (string) $v('parent_id') === (string) $p['id'] ? 'selected' : '' ?>>
              <?= esc($p['code'] . ' · ' . $p['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label>Subledger</label>
        <select name="subledger">
          <option value="none" <?= $v('subledger', 'none') === 'none' ? 'selected' : '' ?>>None</option>
          <option value="customer" <?= $v('subledger') === 'customer' ? 'selected' : '' ?>>Customer (AR)</option>
          <option value="supplier" <?= $v('subledger') === 'supplier' ? 'selected' : '' ?>>Supplier (AP)</option>
        </select>
      </div>
      <div class="field">
        <label>Cash-flow section</label>
        <select name="cashflow">
          <?php foreach (\App\Models\AccountModel::CASHFLOW as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $v('cashflow', 'operating') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label>Denomination Currency</label>
        <select name="currency_id">
          <option value="">— base —</option>
          <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string) $v('currency_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label>Description</label>
      <input name="description" value="<?= esc($v('description')) ?>">
    </div>

    <fieldset>
      <legend>Flags</legend>
      <div class="inline">
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_group" value="1" style="width:auto" <?= $v('is_group') ? 'checked' : '' ?>> Header account (no postings)
        </label>
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_cash" value="1" style="width:auto" <?= $v('is_cash') ? 'checked' : '' ?>> Cash / bank account
        </label>
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="is_active" value="1" style="width:auto" <?= old('is_active', $account['is_active'] ?? 1) ? 'checked' : '' ?>> Active
        </label>
      </div>
    </fieldset>

    <div class="btn-group">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="<?= site_url('accounts') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php if ($account): ?>
  <div class="card" style="max-width:640px">
    <h2>Danger zone</h2>
    <div class="btn-group">
      <form method="post" action="<?= site_url('accounts/' . $account['id'] . '/toggle') ?>">
        <?= csrf_field() ?>
        <button class="btn ghost" type="submit"><?= $account['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
      </form>
      <form method="post" action="<?= site_url('accounts/' . $account['id'] . '/delete') ?>"
        onsubmit="return confirm('Delete account <?= esc($account['code'], 'attr') ?>? This only works if it has no journal entries and no sub-accounts.')">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit">Delete</button>
      </form>
      <span class="muted small" style="align-self:center">Deactivate hides it from pickers but keeps history. Delete is only for accounts never used.</span>
    </div>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
