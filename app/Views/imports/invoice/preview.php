<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s         = $parsed['summary'];
$canCommit = $s['ok'] > 0 && $batch['status'] !== 'committed' && user_can('journal.create');
$willPost  = user_can('journal.post');
$party     = $kind === 'sales' ? lang('Import.party_customer') : lang('Import.party_supplier');
$kindLabel = $kind === 'sales' ? lang('Import.kind_sales') : lang('Import.kind_purchase');
$verb      = $willPost ? lang('Import.inv_verb_post') : lang('Import.inv_verb_draft');
?>

<div class="page-head"><div><h1><?= lang('Import.inv_h', [$kindLabel]) ?> · <?= lang('Import.preview') ?></h1><div class="muted small"><?= esc($batch['filename']) ?> · <?= lang('Import.lc_sheet') ?> <?= esc($batch['sheet']) ?></div></div></div>
<?= view('imports/invoice/_steps', ['active' => 'preview', 'batch' => $batch, 'base' => $base]) ?>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Import.inv_found') ?></div><div class="k-value mono"><?= $s['groups'] ?></div></div>
  <div class="kpi pos"><div class="k-label"><?= lang('Import.kpi_ready') ?></div><div class="k-value mono"><?= $s['ok'] ?></div></div>
  <div class="kpi <?= $s['error'] ? 'neg' : '' ?>"><div class="k-label"><?= lang('Import.kpi_with_errors') ?></div><div class="k-value mono"><?= $s['error'] ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Import.kpi_skipped_exist2') ?></div><div class="k-value mono"><?= $s['skip'] ?></div></div>
</div>

<?php if ($parsed['unmapped']): ?>
  <div class="alert alert-error">
    <?= lang('Import.inv_unmatched_note') ?> <?= esc(implode(', ', array_slice($parsed['unmapped'], 0, 20))) ?>
    — <a href="<?= site_url($base . '/' . $batch['id'] . '/accounts') ?>"><?= lang('Import.fix_mapping') ?></a>.
  </div>
<?php endif ?>
<?php if ($parsed['newParties']): ?>
  <div class="alert alert-success">
    <?= lang('Import.inv_will_create') ?> <strong><?= count($parsed['newParties']) ?></strong> <?= lang('Import.inv_parties_pl', [$party]) ?>:
    <span class="small muted"><?= esc(implode(', ', array_slice($parsed['newParties'], 0, 25))) ?></span>
  </div>
<?php endif ?>

<div class="card">
  <?php if ($canCommit): ?>
    <form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/commit') ?>"
      onsubmit="return confirm('<?= esc(lang('Import.inv_commit_confirm', [ucfirst($verb), $s['ok']]), 'js') ?>')">
      <?= csrf_field() ?>
      <button class="btn" type="submit"><?= lang('Import.inv_commit_btn', [ucfirst($verb), $s['ok']]) ?></button>
      <span class="muted small">
        <?= $willPost ? lang('Import.inv_note_post') : lang('Import.inv_note_draft') ?>
      </span>
    </form>
  <?php elseif ($batch['status'] === 'committed'): ?>
    <a class="btn" href="<?= site_url($base . '/' . $batch['id']) ?>"><?= lang('Import.view_imported_batch') ?></a>
  <?php else: ?>
    <span class="muted"><?= lang('Import.inv_nothing') ?></span>
  <?php endif ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Import.inv_col_key') ?></th><th><?= ucfirst($party) ?></th><th><?= lang('App.date') ?></th><th><?= lang('App.currency') ?></th><th class="right"><?= lang('App.subtotal') ?></th><th class="right">PPN</th><th class="right">PPh</th><th class="right"><?= lang('App.total') ?></th><th class="center"><?= lang('Import.col_lines') ?></th><th><?= lang('App.status') ?></th><th><?= lang('Import.col_notes') ?></th></tr></thead>
    <tbody>
      <?php foreach ($parsed['invoices'] as $j): ?>
        <tr>
          <td class="mono nowrap"><?= esc($j['key']) ?></td>
          <td><?= esc($j['party_name']) ?><?= $j['party_id'] === null && $j['party_name'] !== '' ? ' <span class="badge badge-gray">' . esc(lang('Import.new_badge')) . '</span>' : '' ?></td>
          <td class="nowrap"><?= date_id($j['date']) ?></td>
          <td class="small"><?= esc($j['currency']) ?><?= $j['rate'] != 1 ? ' @' . money($j['rate'], 2) : '' ?></td>
          <td class="right mono"><?= money($j['subtotal']) ?></td>
          <td class="right mono"><?= money($j['ppn'], 2, true) ?></td>
          <td class="right mono"><?= money($j['pph'], 2, true) ?></td>
          <td class="right mono"><?= money($j['total']) ?></td>
          <td class="center"><?= count($j['lines']) ?></td>
          <td><?php
              $b = $j['status'] === 'ok' ? 'badge badge-green' : ($j['status'] === 'skip' ? 'badge badge-gray' : 'badge badge-red');
          echo '<span class="' . $b . '">' . esc($j['status']) . '</span>'; ?></td>
          <td class="small">
            <?php foreach ($j['errors'] as $e): ?><div style="color:var(--red)"><?= esc($e) ?></div><?php endforeach ?>
            <?php foreach ($j['warnings'] as $w): ?><div class="muted"><?= esc($w) ?></div><?php endforeach ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $parsed['invoices']): ?><tr><td colspan="11" class="muted"><?= lang('Import.inv_no_parsed') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
