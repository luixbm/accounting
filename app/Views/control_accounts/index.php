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
    <h1>Control Accounts</h1>
    <div class="muted small">
      For <b><?= esc(company_name()) ?></b> (base <b><?= esc(base_code()) ?></b>).
      An invoice books its receivable / payable to the account for its currency; a
      settlement at a different rate books the difference to the realized FX account.
    </div>
  </div>
</div>

<?php if ($gaps): ?>
  <div class="alert alert-error">
    Not mapped yet:
    <?php foreach ($gaps as $g): ?><span class="mono"><?= esc($roles[$g['role']] . ' / ' . $g['ccy']) ?></span><?= ! ($g === end($gaps)) ? ', ' : '' ?><?php endforeach ?>.
    Posting invoices in those currencies will fail until these are set.
  </div>
<?php endif ?>

<form method="post" action="<?= site_url('control-accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead>
          <tr>
            <th>Role</th>
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
    <p class="muted small" style="margin-top:10px">
      Saving flags the chosen <b>Trade A/R</b> accounts as customer subledgers and <b>Trade A/P</b> as supplier
      subledgers, so aging and statements pick them up. Accounts dropped from those two rows are un-flagged.
    </p>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="<?= site_url('settings') ?>">Back to settings</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
