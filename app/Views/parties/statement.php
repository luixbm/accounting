<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= esc($row['name']) ?></h1>
    <div class="muted small"><?= esc(ucfirst($kind)) ?> · <?= esc($row['code']) ?><?= $row['email'] ? ' · ' . esc($row['email']) : '' ?></div>
  </div>
  <div class="btn-group no-print">
    <?php if (user_can('masterdata.manage')): ?><a class="btn ghost" href="<?= site_url($route . '/' . $row['id'] . '/edit') ?>">Edit</a><?php endif ?>
    <a class="btn ghost" href="<?= site_url($route) ?>">Back</a>
    <button class="btn secondary" onclick="window.print()">Print</button>
  </div>
</div>

<?php if (! empty($cfDefs) && array_filter($cfValues ?? [])): ?>
  <div class="card" style="max-width:520px">
    <h2>Additional information</h2>
    <table class="grid tight">
      <tbody>
        <?php foreach ($cfDefs as $d): ?>
          <?php if (($cfValues[$d['field_key']] ?? '') === '') {
              continue;
          } ?>
          <tr><td class="muted"><?= esc($d['label']) ?></td><td><?= esc($cfValues[$d['field_key']]) ?></td></tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<form class="filterbar no-print" method="get">
  <div class="field"><label>From</label><input type="date" name="from" value="<?= esc($from) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="to" value="<?= esc($to) ?>"></div>
  <button class="btn" type="submit">Apply</button>
</form>

<div class="card">
  <table class="grid tight mono">
    <thead>
      <tr><th>Date</th><th>Journal</th><th>Reference</th><th>Memo</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr>
    </thead>
    <tbody>
      <tr class="subtotal">
        <td colspan="6">Opening balance</td>
        <td class="right"><?= money($statement['opening']) ?></td>
      </tr>
      <?php foreach ($statement['lines'] as $l): ?>
        <tr>
          <td class="nowrap"><?= date_id($l['entry_date']) ?></td>
          <td class="nowrap"><?= esc($l['journal_no']) ?></td>
          <td><?= esc($l['reference']) ?></td>
          <td style="font-family:inherit"><?= esc($l['memo']) ?></td>
          <td class="right"><?= money($l['debit'], 2, true) ?></td>
          <td class="right"><?= money($l['credit'], 2, true) ?></td>
          <td class="right"><?= money($l['balance']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $statement['lines']): ?>
        <tr><td colspan="7" class="muted" style="font-family:sans-serif">No movement in this period.</td></tr>
      <?php endif ?>
    </tbody>
    <tfoot>
      <tr><td colspan="6">Closing balance</td><td class="right"><?= money($statement['closing']) ?></td></tr>
    </tfoot>
  </table>
</div>

<?= $this->endSection() ?>
