<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$unresolved = array_filter($rows, static fn ($r) => $r['accountId'] === null);
?>

<div class="page-head">
  <div><h1>Import · Match accounts</h1>
    <div class="muted small"><?= count($rows) ?> distinct account labels · <?= count($unresolved) ?> still unmatched</div>
  </div>
</div>
<?= view('journals/import/_steps', ['active' => 'accounts', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('journals/import/' . $batch['id'] . '/accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <p class="muted small">Each label from the spreadsheet is mapped to one account in this chart of accounts.
      Matches are remembered and reused on future imports. Labels left unmatched will make their journals fail in the preview.</p>
    <table class="grid tight">
      <thead><tr><th style="width:40%">Spreadsheet label</th><th>Account</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td>
              <?= esc($r['label']) ?>
              <?php if ($r['auto']): ?><span class="badge badge-gray">auto-matched — confirm</span><?php endif ?>
              <input type="hidden" name="label[]" value="<?= esc($r['label'], 'attr') ?>">
            </td>
            <td>
              <select name="account_id[]">
                <option value="">— choose account —</option>
                <?php foreach ($accounts as $a): ?>
                  <?php if ((int) $a['is_group'] === 1) {
                      continue;
                  } ?>
                  <option value="<?= $a['id'] ?>" <?= (int) $r['accountId'] === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= esc($a['code'] . ' · ' . $a['name']) ?>
                  </option>
                <?php endforeach ?>
              </select>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="2" class="muted">No account labels found — check the column mapping.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Save &amp; preview</button>
      <a class="btn ghost" href="<?= site_url('journals/import/' . $batch['id'] . '/map') ?>">Back</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
