<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $isForeign = (int) ($journal['currency_is_base'] ?? 1) === 0; ?>

<div class="page-head">
  <div>
    <h1><?= esc($journal['journal_no']) ?> <?= status_badge($journal['status']) ?></h1>
    <div class="muted small">
      <?= date_id($journal['entry_date']) ?> ·
      <?= esc($journal['currency_code']) ?><?= $isForeign ? ' @ ' . money($journal['exchange_rate'], 4) : '' ?> ·
      created by user #<?= esc($journal['created_by']) ?>
      <?php if ($journal['posted_at']): ?> · posted <?= esc($journal['posted_at']) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php if ($journal['status'] === 'draft'): ?>
      <?php if (user_can('journal.create')): ?><a class="btn ghost" href="<?= site_url('journals/' . $journal['id'] . '/edit') ?>">Edit</a><?php endif ?>
      <?php if (user_can('journal.post')): ?>
        <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/post') ?>" onsubmit="return confirm('Post this journal to the ledger?')">
          <?= csrf_field() ?><button class="btn" type="submit">Post</button>
        </form>
      <?php endif ?>
      <?php if (user_can('journal.delete')): ?>
        <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/delete') ?>" onsubmit="return confirm('Delete this draft?')">
          <?= csrf_field() ?><button class="btn danger" type="submit">Delete</button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <button class="btn secondary" onclick="window.print()">Print</button>
    <a class="btn ghost" href="<?= site_url('journals') ?>">Back</a>
  </div>
</div>

<?php if ($journal['status'] === 'void'): ?>
  <div class="alert alert-error">
    Voided <?= esc($journal['voided_at']) ?> — <?= esc($journal['void_reason']) ?>
    <?php if ($reversedBy): ?> · reversing entry <a href="<?= site_url('journals/' . $reversedBy['id']) ?>"><?= esc($reversedBy['journal_no']) ?></a><?php endif ?>
  </div>
<?php endif ?>
<?php if ($reversal): ?>
  <div class="alert alert-success">This is a reversing entry for
    <a href="<?= site_url('journals/' . $reversal['id']) ?>"><?= esc($reversal['journal_no']) ?></a>.</div>
<?php endif ?>

<div class="card">
  <table class="grid tight">
    <tbody>
      <tr><td class="muted" style="width:130px">Description</td><td><?= esc($journal['description']) ?></td></tr>
      <tr><td class="muted">Reference</td><td><?= esc($journal['reference']) ?: '—' ?></td></tr>
      <tr><td class="muted">Source</td><td><?= esc(\App\Models\JournalModel::SOURCES[$journal['source']] ?? $journal['source']) ?></td></tr>
    </tbody>
  </table>
</div>

<div class="card">
  <table class="grid tight mono">
    <thead>
      <tr>
        <th>Account</th><th style="font-family:sans-serif">Memo</th><th>Party</th><th>Job</th>
        <?php if ($isForeign): ?><th class="right">Debit (<?= esc($journal['currency_code']) ?>)</th><th class="right">Credit</th><?php endif ?>
        <th class="right">Debit (<?= base_code() ?>)</th><th class="right">Credit (<?= base_code() ?>)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td class="nowrap"><?= esc($l['account_code'] . ' · ' . $l['account_name']) ?></td>
          <td style="font-family:sans-serif"><?= esc($l['memo']) ?></td>
          <td style="font-family:sans-serif" class="small"><?= esc($l['customer_name'] ?? $l['supplier_name'] ?? '') ?></td>
          <td class="small"><?= esc($l['job_code'] ?? '') ?></td>
          <?php if ($isForeign): ?>
            <td class="right"><?= money($l['debit'], 2, true) ?></td>
            <td class="right"><?= money($l['credit'], 2, true) ?></td>
          <?php endif ?>
          <td class="right"><?= money($l['debit_base'], 2, true) ?></td>
          <td class="right"><?= money($l['credit_base'], 2, true) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="<?= $isForeign ? 6 : 4 ?>" class="right">Total</td>
        <td class="right"><?= money($journal['total_debit']) ?></td>
        <td class="right"><?= money($journal['total_credit']) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php if ($journal['status'] === 'posted' && user_can('journal.void')): ?>
  <div class="card">
    <h2>Void this journal</h2>
    <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('Void this posted journal? A reversing entry will be booked.')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="Reason for voiding" required style="max-width:360px">
      <button class="btn danger" type="submit">Void &amp; reverse</button>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
