<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $unresolved = array_filter($rows, static fn ($r) => $r['accountId'] === null); ?>

<div class="page-head">
  <div><h1><?= ucfirst($kind) ?> Import · Match accounts</h1>
    <div class="muted small"><?= count($rows) ?> labels · <?= count($unresolved) ?> unmatched</div></div>
</div>
<?= view('imports/invoice/_steps', ['active' => 'accounts', 'batch' => $batch, 'base' => $base]) ?>

<form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/accounts') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <p class="muted small">Each spreadsheet account label maps to one account. Matches are saved and reused on later imports.</p>
    <table class="grid tight">
      <thead><tr><th style="width:40%">Spreadsheet label</th><th>Account</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= esc($r['label']) ?><?php if ($r['auto']): ?> <span class="badge badge-gray">auto — confirm</span><?php endif ?>
              <input type="hidden" name="label[]" value="<?= esc($r['label'], 'attr') ?>"></td>
            <td>
              <select name="account_id[]">
                <option value="">— choose account —</option>
                <?php foreach ($accounts as $a): ?>
                  <?php if ((int) $a['is_group'] === 1) {
                      continue;
                  } ?>
                  <option value="<?= $a['id'] ?>" <?= (int) $r['accountId'] === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= esc($a['code'] . ' · ' . $a['name']) ?></option>
                <?php endforeach ?>
              </select>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="2" class="muted">No account labels found — check the mapping.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Save &amp; preview</button>
      <a class="btn ghost" href="<?= site_url($base . '/' . $batch['id'] . '/map') ?>">Back</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
