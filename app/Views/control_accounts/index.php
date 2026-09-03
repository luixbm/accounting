<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array<string,string> $roles @var list<array> $currencies @var array $grid @var list<array> $accounts @var list<array> $gaps */
$opt = static function (string $sel) use ($accounts): string {
    $h = '<option value="">— none —</option>';
    foreach ($accounts as $a) {
        $h .= '<option value="' . esc($a['code'], 'attr') . '"' . ($sel === $a['code'] ? ' selected' : '') . '>'
            . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div>
    <h1><?= lang('Setup.ctl_accounts_h') ?></h1>
    <div class="muted small"><?= lang('Setup.ctl_note', ['<b>' . esc(company_name()) . '</b>', '<b>' . esc(base_code()) . '</b>']) ?></div>
  </div>
</div>

<?php if ($gaps): ?>
  <div class="alert alert-error">
    <?= lang('Setup.ctl_not_mapped') ?>
    <?php foreach ($gaps as $g): ?><span class="mono"><?= esc($roles[$g['role']] . ' / ' . $g['ccy']) ?></span><?= ! ($g === end($gaps)) ? ', ' : '' ?><?php endforeach ?>.
    <?= lang('Setup.ctl_not_mapped_tail') ?>
  </div>
<?php endif ?>

<form method="post" action="<?= site_url('control-accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead>
          <tr>
            <th><?= lang('Setup.ctl_role') ?></th>
            <?php foreach ($currencies as $c): ?><th><?= esc($c['code']) ?><?= $c['code'] === base_code() ? ' <span class="badge badge-green">base</span>' : '' ?></th><?php endforeach ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $role => $label): ?>
            <tr>
              <td class="nowrap"><b><?= esc($label) ?></b></td>
              <?php foreach ($currencies as $c): ?>
                <td><select name="<?= $role ?>[<?= esc($c['code'], 'attr') ?>]"><?= $opt($grid[$role][$c['code']] ?? '') ?></select></td>
              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <p class="muted small" style="margin-top:10px"><?= lang('Setup.ctl_save_note') ?></p>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('settings') ?>"><?= lang('Setup.back_to_settings') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
