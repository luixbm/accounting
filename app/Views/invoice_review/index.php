<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>>            $batches
 * @var array<int,list<array<string,mixed>>> $itemsByBatch
 * @var string                               $status
 * @var string                               $q
 */
$badge = static function (array $it): array {
    if (! empty($it['confirmed_at'])) {
        return ['badge-green', lang('Review.status_confirmed')];
    }

    return match ($it['match_status']) {
        'would_apply', 'unchanged' => ! empty($it['over_budget'])
            ? ['badge-amber', lang('Review.status_overbudget')]
            : ['badge-green', lang('Review.status_matched_ok')],
        'ambiguous', 'not_found' => ['badge-gray', lang('Review.status_pending')],
        'applied'                => ['badge-green', lang('Review.status_confirmed')],
        default                  => ['badge-red', lang('Review.status_error')],
    };
};
$canAct = static fn (array $it): bool => empty($it['confirmed_at']) && in_array($it['match_status'], ['would_apply', 'unchanged'], true);
$diff   = static fn (?float $budget, ?float $req): ?float => $budget === null || $req === null ? null : round($budget - $req, 2);

// One promise-date control per purchase invoice in the batch. $showRow is true
// only on the first review line of each invoice (and the first unmatched line).
$promiseCell = static function (array $it, int $batchId, bool $showRow, string $curDate): string {
    if (! $showRow) {
        return '';
    }
    if (empty($it['invoice_id'])) {
        return '<span class="muted small">' . esc(lang('Review.promise_when_matched')) . '</span>';
    }

    return '<form method="post" action="' . site_url('purchases/review/batch/' . $batchId . '/invoice/' . (int) $it['invoice_id'] . '/promise-date') . '" style="display:inline">'
        . csrf_field()
        . '<input type="date" name="promise_date" value="' . esc($curDate, 'attr') . '" onchange="this.form.requestSubmit()" style="padding:2px 4px;font-size:.82rem">'
        . '</form>';
};

$actionCell = static function (array $it) use ($canAct): string {
    if (empty($it['confirmed_at']) && ! empty($it['over_budget'])
        && $it['match_status'] === 'would_apply' && ! empty($it['invoice_id'])) {
        return '<a class="btn sm" href="' . site_url('purchases/' . (int) $it['invoice_id'] . '/edit') . '">'
            . esc(lang('Review.open_invoice')) . '</a>';
    }
    if ($canAct($it)) {
        return '<form method="post" action="' . site_url('purchases/review/' . $it['id'] . '/confirm') . '" style="display:inline">' . csrf_field()
            . '<button class="btn sm" type="submit">' . esc(lang('Review.confirm')) . '</button></form>';
    }
    if (empty($it['confirmed_at'])) {
        return '<form method="post" action="' . site_url('purchases/review/' . $it['id'] . '/recheck') . '" style="display:inline">' . csrf_field()
            . '<button class="btn sm ghost" type="submit">' . esc(lang('Review.recheck')) . '</button></form>';
    }

    return '';
};

$deleteBtn = static function (int $batchId): string {
    return '<form method="post" action="' . site_url('purchases/review/batch/' . $batchId . '/delete') . '" style="display:inline" onsubmit="return confirm(' . esc(json_encode(lang('Review.delete_confirm')), 'attr') . ')">'
        . csrf_field()
        . '<button class="btn sm ghost" type="submit" title="' . esc(lang('Review.delete'), 'attr') . '">&#128465;</button></form>';
};
?>

<div class="page-head">
  <div><h1><?= esc(lang('Review.title')) ?></h1><div class="muted small"><?= esc(lang('Review.subtitle')) ?></div></div>
  <div class="btn-group no-print">
    <?php $qs = ($q !== '' ? '&q=' . urlencode($q) : '') . ($state !== 'all' ? '&state=' . urlencode($state) : ''); ?>
    <form method="get" action="<?= site_url('purchases/review') ?>" style="display:contents">
      <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
      <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Review.search_ph'), 'attr') ?>" style="min-width:170px;width:auto">
      <select name="state" onchange="this.form.submit()" style="width:auto">
        <option value="all"><?= esc(lang('Review.state_all')) ?></option>
        <?php foreach (['confirmed', 'matched', 'overbudget', 'pending', 'error'] as $s): ?>
          <option value="<?= $s ?>" <?= $state === $s ? 'selected' : '' ?>><?= esc(lang('Review.state_' . $s)) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn sm ghost" type="submit"><?= esc(lang('Review.search')) ?></button>
    </form>
    <a class="btn sm <?= $status === 'open' ? '' : 'ghost' ?>" href="<?= site_url('purchases/review?status=open' . $qs) ?>"><?= esc(lang('Review.filter_open')) ?></a>
    <a class="btn sm <?= $status === 'all' ? '' : 'ghost' ?>" href="<?= site_url('purchases/review?status=all' . $qs) ?>"><?= esc(lang('Review.filter_all')) ?></a>
    <?php if (($confirmedBatches ?? 0) > 0): ?>
      <form method="post" action="<?= site_url('purchases/review/clear-confirmed') ?>" style="display:contents"
        onsubmit="return confirm(<?= esc(json_encode(lang('Review.clear_confirmed_confirm')), 'attr') ?>)">
        <?= csrf_field() ?>
        <button class="btn sm ghost danger" type="submit"><?= esc(lang('Review.clear_confirmed', [$confirmedBatches])) ?></button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight" id="reviewTable">
      <thead>
        <tr>
          <th><?= esc(lang('Review.col_supplier_file')) ?></th>
          <th><?= esc(lang('Review.col_travelers')) ?></th>
          <th><?= esc(lang('Review.col_service_date')) ?></th>
          <th><?= esc(lang('Review.col_arrival_date')) ?></th>
          <th class="right"><?= esc(lang('Review.col_requested')) ?></th>
          <th class="right"><?= esc(lang('Review.col_matched_budget')) ?></th>
          <th class="right"><?= esc(lang('Review.col_diff')) ?></th>
          <th><?= esc(lang('Review.col_status')) ?></th>
          <th><?= esc(lang('Review.col_promise_date')) ?></th>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($batches as $b): ?>
          <?php
            $items = $itemsByBatch[$b['id']] ?? [];
            if (! $items) {
                continue;
            }
          ?>
          <?php if (count($items) === 1): ?>
            <?php $it = $items[0]; [$cls, $label] = $badge($it); $d = $diff($it['matched_budget'] !== null ? (float) $it['matched_budget'] : null, $it['requested_amount'] !== null ? (float) $it['requested_amount'] : null); ?>
            <tr>
              <td><b><?= esc($b['vendor'] ?: $it['party_name'] ?: '—') ?></b><?php if ($b['file_name']): ?><br><span class="muted small mono"><?= esc($b['file_name']) ?><?php if ($b['file_url']): ?> · <a href="<?= esc($b['file_url'], 'attr') ?>" target="_blank" rel="noopener">&#8599;</a><?php endif ?></span><?php endif ?></td>
              <td><?= esc($it['party_name'] ?: '—') ?><?php if ($it['description']): ?><br><span class="muted small"><?= esc($it['description']) ?></span><?php endif ?></td>
              <td class="mono small"><?= $it['service_date'] ? date_id($it['service_date']) : '—' ?></td>
              <td class="mono small"><?= $it['arrival_date'] ? date_id($it['arrival_date']) : '—' ?></td>
              <td class="right mono"><?= $it['requested_amount'] !== null ? money((float) $it['requested_amount']) : '—' ?></td>
              <td class="right mono"><?= $it['matched_budget'] !== null ? money((float) $it['matched_budget']) : '—' ?></td>
              <td class="right mono" style="color:<?= $d === null ? 'inherit' : ($d < 0 ? 'var(--red)' : 'var(--green)') ?>"><?= $d === null ? '—' : money($d) ?></td>
              <td><span class="badge <?= $cls ?>"><?= esc($label) ?></span><?php if ($it['match_message']): ?><br><span class="muted small"><?= esc($it['match_message']) ?></span><?php endif ?></td>
              <td><?= $promiseCell($it, (int) $b['id'], true, $promiseByInvoice[(int) ($it['invoice_id'] ?? 0)] ?? (string) ($it['promise_date'] ?? '')) ?></td>
              <td class="right no-print"><div class="btn-group"><?= $actionCell($it) . $deleteBtn((int) $b['id']) ?></div></td>
            </tr>
          <?php else: ?>
            <?php
              $sumReq  = array_sum(array_column($items, 'requested_amount'));
              $sumBud  = array_sum(array_map(static fn ($i) => $i['matched_budget'] !== null ? (float) $i['matched_budget'] : 0.0, $items));
              $sumOk   = abs($sumReq - $sumBud) < 0.005;
              $done    = count(array_filter($items, static fn ($i) => ! empty($i['confirmed_at'])));
              $grpId   = 'grp-' . $b['id'];
              $anyAct  = (bool) array_filter($items, $canAct);
            ?>
            <tr class="grp-row" data-toggle="<?= $grpId ?>" style="cursor:pointer">
              <td><span class="grp-arrow">&#9662;</span> <b><?= esc($b['vendor'] ?: $b['file_name'] ?: '—') ?></b><br>
                <span class="muted small mono"><?= esc($b['file_name'] ?: '') ?><?= $b['file_name'] ? ' · ' : '' ?><?= esc(lang('Review.transactions_n', [count($items)])) ?> · <?= esc(lang('Review.confirmed_of', [$done, count($items)])) ?></span>
              </td>
              <td></td>
              <td></td>
              <td></td>
              <td class="right mono"><?= money((float) $sumReq) ?></td>
              <td class="right mono"><?= money((float) $sumBud) ?></td>
              <td></td>
              <td><span class="badge <?= $sumOk ? 'badge-green' : 'badge-amber' ?>"><?= esc($sumOk ? lang('Review.sum_ok') : lang('Review.sum_mismatch')) ?></span></td>
              <td></td>
              <td class="right no-print">
                <div class="btn-group" onclick="event.stopPropagation()">
                  <?php if ($done < count($items)): ?>
                    <form method="post" action="<?= site_url('purchases/review/batch/' . $b['id'] . '/recheck') ?>"><?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= esc(lang('Review.recheck_all')) ?></button></form>
                  <?php endif ?>
                  <?php if ($anyAct): ?>
                    <form method="post" action="<?= site_url('purchases/review/batch/' . $b['id'] . '/confirm') ?>"><?= csrf_field() ?><button class="btn sm" type="submit"><?= esc(lang('Review.confirm_all')) ?></button></form>
                  <?php endif ?>
                  <?= $deleteBtn((int) $b['id']) ?>
                </div>
              </td>
            </tr>
            <?php $shownInv = []; ?>
            <?php foreach ($items as $it): ?>
              <?php
              [$cls, $label] = $badge($it);
              $d      = $diff($it['matched_budget'] !== null ? (float) $it['matched_budget'] : null, $it['requested_amount'] !== null ? (float) $it['requested_amount'] : null);
              $pInv   = (int) ($it['invoice_id'] ?? 0);
              $pKey   = $pInv > 0 ? 'i' . $pInv : 'unm';
              $showP  = empty($shownInv[$pKey]);
              $shownInv[$pKey] = true;
              $curP   = $pInv > 0 ? ($promiseByInvoice[$pInv] ?? (string) ($it['promise_date'] ?? '')) : '';
              ?>
              <tr class="<?= $grpId ?>">
                <td class="muted small" style="padding-left:24px"><?= esc($it['description'] ?: '—') ?></td>
                <td><?= esc($it['party_name'] ?: '—') ?></td>
                <td class="mono small"><?= $it['service_date'] ? date_id($it['service_date']) : '—' ?></td>
                <td class="mono small"><?= $it['arrival_date'] ? date_id($it['arrival_date']) : '—' ?></td>
                <td class="right mono"><?= $it['requested_amount'] !== null ? money((float) $it['requested_amount']) : '—' ?></td>
                <td class="right mono"><?= $it['matched_budget'] !== null ? money((float) $it['matched_budget']) : '—' ?></td>
                <td class="right mono" style="color:<?= $d === null ? 'inherit' : ($d < 0 ? 'var(--red)' : 'var(--green)') ?>"><?= $d === null ? '—' : money($d) ?></td>
                <td><span class="badge <?= $cls ?>"><?= esc($label) ?></span><?php if ($it['match_message']): ?><br><span class="muted small"><?= esc($it['match_message']) ?></span><?php endif ?></td>
                <td><?= $promiseCell($it, (int) $b['id'], $showP, $curP) ?></td>
                <td class="right no-print"><?= $actionCell($it) ?></td>
              </tr>
            <?php endforeach ?>
          <?php endif ?>
        <?php endforeach ?>
        <?php if (! $batches): ?><tr><td colspan="10" class="muted"><?= esc(lang('Review.no_batches')) ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  .grp-row{background:var(--panel-2, #f5f5f7)}
  .grp-row td{font-weight:600}
</style>
<script>
(function(){
  document.querySelectorAll('[data-toggle]').forEach(function(head){
    head.addEventListener('click', function(){
      var key = head.getAttribute('data-toggle');
      var hidden = null;
      document.querySelectorAll('.' + key).forEach(function(row){
        if (hidden === null) { hidden = row.hidden = !row.hidden; } else { row.hidden = hidden; }
      });
      var arrow = head.querySelector('.grp-arrow');
      if (arrow) { arrow.innerHTML = hidden ? '&#9656;' : '&#9662;'; }
    });
  });
})();
</script>

<?= $this->endSection() ?>
