<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$cols = $data['columns'];

$cells = static function (array $amounts): string {
    $h = '';
    foreach ($amounts as $v) {
        $h .= '<td class="right mono">' . money($v, 2, true) . '</td>';
    }

    return $h;
};

$section = static function (array $group) use ($cells): string {
    $span = count($group['totals']) + 1;
    $h    = '<tr class="grp-row"><td colspan="' . $span . '">' . esc($group['label']) . '</td></tr>';
    foreach ($group['blocks'] as $blk) {
        $named = $blk['code'] !== '';
        if ($named) {
            $h .= '<tr class="hdr-row"><td colspan="' . $span . '"><span class="mono small">' . esc($blk['code']) . '</span> ' . esc($blk['name']) . '</td></tr>';
        }
        foreach ($blk['rows'] as $r) {
            $lbl = ($r['code'] !== '' ? '<span class="mono small">' . esc($r['code']) . '</span> ' : '') . esc($r['name']);
            $h  .= '<tr><td' . ($named ? ' style="padding-left:28px"' : '') . '>' . $lbl . '</td>' . $cells($r['amounts']) . '</tr>';
        }
        if ($named) {
            $h .= '<tr class="sub-row"><td class="right">' . lang('Report.v_subtotal_of', [esc($blk['name'])]) . '</td>' . $cells($blk['subtotals']) . '</tr>';
        }
    }
    $h .= '<tr class="subtotal"><td>' . lang('App.total') . '</td>' . $cells($group['totals']) . '</tr>';

    return $h;
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= lang('Report.v_snapshot_end') ?> · <?= date_id($from) ?> — <?= date_id($to) ?> · <?= lang('App.' . $compare) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr>
          <th style="min-width:220px"><?= lang('App.account') ?></th>
          <?php foreach ($cols as $c): ?><th class="right nowrap"><?= esc($c) ?></th><?php endforeach ?>
        </tr>
      </thead>
      <tbody>
        <?= $section($data['groups']['asset']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td><?= lang('Report.v_total_assets') ?></td><?= $cells($data['assets']) ?></tr>
        <?= $section($data['groups']['liability']) ?>
        <?= $section($data['groups']['equity']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td><?= lang('Report.v_total_liab_equity') ?></td><?= $cells($data['liab_equity']) ?></tr>
      </tbody>
      <tfoot>
        <tr>
          <td><?= lang('Report.v_difference') ?></td>
          <?php foreach ($cols as $i => $_): ?>
            <?php $diff = $data['assets'][$i] - $data['liab_equity'][$i]; ?>
            <td class="right mono" style="<?= abs($diff) < 0.5 ? '' : 'color:var(--red);font-weight:700' ?>"><?= money($diff, 2, true) ?></td>
          <?php endforeach ?>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
