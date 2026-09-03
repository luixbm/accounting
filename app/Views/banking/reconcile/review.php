<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $st @var array $bank @var array $sum @var list<array> $freeBook @var list<array> $expenseAccounts */
$reconciled = $st['status'] === 'reconciled';
$canPost    = user_can('journal.post');

// index book lines by id for the "matched to" display
$bookById = [];
foreach ($sum['book'] as $b) {
    $bookById[(int) $b['id']] = $b;
}

$bookOpt = static function ($rows) {
    $h = '<option value="">— match to book entry —</option>';
    foreach ($rows as $b) {
        $h .= '<option value="' . (int) $b['id'] . '">'
            . esc(date_id($b['entry_date']) . '  ' . $b['journal_no'] . '  ' . money_c($b['effect'])
                  . ($b['memo'] ? '  · ' . mb_substr((string) $b['memo'], 0, 40) : ''))
            . '</option>';
    }

    return $h;
};
$accOpt = static function ($rows) {
    $h = '<option value="">— offset account —</option>';
    foreach ($rows as $a) {
        $h .= '<option value="' . (int) $a['id'] . '">' . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div>
    <h1>Reconcile — <?= esc($bank['name'] ?? '') ?>
      <?= $reconciled ? '<span class="badge badge-green">Reconciled</span>' : '<span class="badge badge-gray">Draft</span>' ?>
    </h1>
    <div class="muted small">
      Statement to <?= date_id($st['statement_date']) ?> ·
      opening <?= money_c($st['opening_balance']) ?> · closing <?= money_c($st['closing_balance']) ?>
      <?= $st['note'] ? ' · ' . esc($st['note']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">&lsaquo; All statements</a>
    <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id'] . '/report') ?>">Print statement</a>
    <?php if ($canPost && ! $reconciled): ?>
      <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id'] . '/map') ?>">Re-map columns</a>
      <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/rematch') ?>" style="display:inline"><?= csrf_field() ?><button class="btn secondary">Auto-match again</button></form>
    <?php endif ?>
  </div>
</div>

<?php
$tiles = [
    ['Book balance @ date', money_c($sum['book_balance']), ''],
    ['Statement closing', money_c($st['closing_balance']), ''],
    ['Unmatched on bank', money_c($sum['unmatched_stmt_total']) . ' · ' . $sum['counts']['stmt_unmatched'], 'warn'],
    ['Outstanding in books', money_c($sum['unmatched_book_total']) . ' · ' . $sum['counts']['book_unmatched'], 'warn'],
    ['Difference', money_c($sum['difference']), $sum['reconciled'] ? 'ok' : 'bad'],
];
?>
<div class="kpi-row">
  <?php foreach ($tiles as [$label, $val, $tone]): ?>
    <div class="kpi <?= $tone ?>"><div class="kpi-label"><?= esc($label) ?></div><div class="kpi-val mono"><?= $val ?></div></div>
  <?php endforeach ?>
</div>

<?php if (abs((float) $sum['import_check']) >= 0.5): ?>
  <div class="alert alert-error no-print">
    Imported lines don't tie to the statement: closing − opening − Σlines = <?= money_c($sum['import_check']) ?>.
    Re-map the columns (wrong amount convention or a missed column?).
  </div>
<?php endif ?>

<?php if ($sum['reconciled'] && ! $reconciled && $canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/finish') ?>" class="no-print" style="margin-bottom:14px">
    <?= csrf_field() ?>
    <div class="alert alert-success">Difference is zero — <button class="btn sm">Mark reconciled</button></div>
  </form>
<?php endif ?>
<?php if ($reconciled && $canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/reopen') ?>" class="no-print" style="margin-bottom:14px">
    <?= csrf_field() ?>
    <button class="btn sm ghost">Reopen for editing</button>
  </form>
<?php endif ?>

<div class="card">
  <h2>Statement lines <span class="muted small">(<?= $sum['counts']['stmt'] ?>)</span></h2>
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr><th>Date</th><th>Description</th><th>Ref</th><th class="right">Amount</th><th>Status / action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sum['stmt_lines'] as $sl): ?>
          <?php $mid = $sl['matched_line_id'] !== null ? (int) $sl['matched_line_id'] : null; ?>
          <tr>
            <td class="nowrap"><?= date_id($sl['txn_date']) ?></td>
            <td><?= esc($sl['description']) ?></td>
            <td class="muted small"><?= esc($sl['reference']) ?></td>
            <td class="right mono"><?= money_c($sl['amount']) ?></td>
            <td>
              <?php if ($mid !== null): ?>
                <span class="badge badge-green">Matched</span>
                <?php if (isset($bookById[$mid])): ?>
                  <a class="mono small" href="<?= site_url('journals/' . $bookById[$mid]['journal_id']) ?>"><?= esc($bookById[$mid]['journal_no']) ?></a>
                <?php endif ?>
                <span class="muted small">(<?= esc($sl['match_type']) ?>)</span>
                <?php if ($canPost && ! $reconciled): ?>
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/unmatch') ?>" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <button class="btn sm ghost">Unmatch</button>
                  </form>
                <?php endif ?>
              <?php elseif ($canPost && ! $reconciled): ?>
                <div class="recon-actions">
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/match') ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <select name="book_line_id"><?= $bookOpt($freeBook) ?></select>
                    <button class="btn sm">Match</button>
                  </form>
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/add-entry') ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <select name="account_id"><?= $accOpt($expenseAccounts) ?></select>
                    <input name="memo" placeholder="memo" value="<?= esc($sl['description']) ?>">
                    <button class="btn sm secondary">Add to books</button>
                  </form>
                </div>
              <?php else: ?>
                <span class="badge badge-gray">Unmatched</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $sum['stmt_lines']): ?><tr><td colspan="5" class="muted">No statement lines imported.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Outstanding book entries <span class="muted small">(in the ledger, not on this statement — <?= $sum['counts']['book_unmatched'] ?>)</span></h2>
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr><th>Date</th><th>Journal</th><th>Memo</th><th class="right">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($sum['unmatched_book'] as $b): ?>
          <tr>
            <td class="nowrap"><?= date_id($b['entry_date']) ?></td>
            <td class="mono"><a href="<?= site_url('journals/' . $b['journal_id']) ?>"><?= esc($b['journal_no']) ?></a></td>
            <td><?= esc($b['memo'] ?: $b['jdesc']) ?></td>
            <td class="right mono"><?= money_c($b['effect']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $sum['unmatched_book']): ?><tr><td colspan="4" class="muted">Nothing outstanding — every ledger entry on this account is on the statement.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/delete') ?>" class="no-print"
        onsubmit="return confirm('Delete this statement and all its imported lines? Journals already posted stay.');">
    <?= csrf_field() ?>
    <button class="btn sm ghost" style="color:var(--c-danger,#b00)">Delete statement</button>
  </form>
<?php endif ?>

<style>
  .recon-actions{display:flex;flex-direction:column;gap:6px}
  .inline-form{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .inline-form select,.inline-form input{max-width:240px}
  .kpi-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .kpi{flex:1;min-width:150px;padding:12px 14px;border:1px solid var(--c-border,#ddd);border-radius:10px;background:var(--c-surface,#fff)}
  .kpi-label{font-size:.8rem;color:var(--c-muted,#777)}
  .kpi-val{font-size:1.15rem;margin-top:4px}
  .kpi.ok{border-color:#1a7f37}.kpi.bad{border-color:#b00}.kpi.warn{border-color:#b7791f}
</style>

<?= $this->endSection() ?>
