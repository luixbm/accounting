<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$mNames = [];
for ($m = 1; $m <= 12; $m++) {
    $mNames[$m] = date('M', mktime(0, 0, 0, $m, 1));
}
?>

<div class="page-head">
  <div>
    <h1><?= esc($ver['name']) ?> <span class="muted small">— <?= (int) $ver['year'] ?><?= $ver['is_default'] ? ' · ' . lang('Budget.default') : '' ?></span></h1>
    <?php if (! empty($ver['note'])): ?><div class="muted small"><?= esc($ver['note']) ?></div><?php endif ?>
  </div>
  <div class="btn-group no-print">
    <a class="btn" href="<?= site_url('budgets/import?version=' . $ver['id']) ?>"><?= lang('Budget.import_btn') ?></a>
    <a class="btn ghost" href="<?= site_url('budgets/' . $ver['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
    <a class="btn ghost" href="<?= site_url('budgets') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Report.col_account') ?></th>
        <?php foreach ($mNames as $lbl): ?><th class="right"><?= $lbl ?></th><?php endforeach ?>
        <th class="right"><?= lang('App.total') ?></th>
        <th class="no-print"></th>
      </tr>
    </thead>
    <tbody>
      <?php $grand = array_fill(1, 12, 0.0);
      $grandT = 0.0; ?>
      <?php foreach ($accounts as $a): ?>
        <?php
        $vals = $grid[(int) $a['id']] ?? [];
        $rowT = 0.0;
        foreach ($vals as $x) {
            $rowT += (float) $x;
        }
        if (abs($rowT) < 0.005 && ! array_filter($vals)) {
            continue;
        }
        $grandT += $rowT;
        ?>
        <tr>
          <td class="mono small"><?= esc($a['code']) ?> · <?= esc($a['name']) ?></td>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <?php $x = (float) ($vals[$m] ?? 0);
            $grand[$m] += $x; ?>
            <td class="right mono"><?= abs($x) >= 0.005 ? money($x, 0) : '' ?></td>
          <?php endfor ?>
          <td class="right mono"><?= money($rowT, 0) ?></td>
          <td class="right nowrap no-print"><a class="btn sm ghost" href="<?= site_url('budgets/' . $ver['id'] . '/row/' . $a['id']) ?>"><?= lang('App.edit') ?></a></td>
        </tr>
      <?php endforeach ?>
      <tr class="subtotal">
        <td><?= lang('App.total') ?></td>
        <?php for ($m = 1; $m <= 12; $m++): ?><td class="right mono"><?= money($grand[$m], 0) ?></td><?php endfor ?>
        <td class="right mono"><?= money($grandT, 0) ?></td>
        <td class="no-print"></td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<p class="small muted"><?= lang('Budget.grid_note') ?></p>

<?= $this->endSection() ?>
