<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Generic list report.
 *
 * @var string                  $title
 * @var array                   $f          ReportFilter::resolve()
 * @var list<array>             $columns    [ ['key'=>,'label'=>,'money'=>bool,'align'=>'right','blankZero'=>bool], ... ]
 * @var list<array<string,mixed>> $rows      normal row, or ['_style'=>'section','_label'=>..], or ['_style'=>'subtotal'|'total', ...]
 * @var array|null               $periodOpts extra opts for reports/_period
 * @var string|null              $subtitle
 */
$nCol = count($columns);
$align = static fn (array $c): string => (($c['align'] ?? '') === 'right' || ! empty($c['money'])) ? ' class="right mono"' : '';
?>

<div class="page-head">
  <div><h1><?= esc($title) ?></h1><?php if (! empty($subtitle)): ?><div class="muted small"><?= esc($subtitle) ?></div><?php endif ?></div>
  <div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Nav.reports') ?></a></div>
</div>

<?= view('reports/_period', ($periodOpts ?? []) + ['f' => $f]) ?>

<div class="report-title">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= esc($f['label']) ?> &middot; <?= date_id($f['from']) ?> – <?= date_id($f['to']) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr><?php foreach ($columns as $c): ?><th<?= $align($c) ?>><?= esc($c['label']) ?></th><?php endforeach ?></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $style = $r['_style'] ?? ''; ?>
          <?php if ($style === 'section'): ?>
            <tr class="grp-row"><td colspan="<?= $nCol ?>"><?= esc($r['_label']) ?></td></tr>
          <?php else: ?>
            <tr class="<?= in_array($style, ['subtotal', 'total'], true) ? 'subtotal' : '' ?>">
              <?php foreach ($columns as $c): ?>
                <?php $v = $r[$c['key']] ?? ''; ?>
                <td<?= $align($c) ?>><?= ! empty($c['money']) && $v !== '' && $v !== null ? money((float) $v, 2, ! empty($c['blankZero'])) : esc($v) ?></td>
              <?php endforeach ?>
            </tr>
          <?php endif ?>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="<?= $nCol ?>" class="muted"><?= lang('App.no_records') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
