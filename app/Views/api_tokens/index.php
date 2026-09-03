<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Setup.api_tokens_h') ?></h1><div class="muted small"><?= lang('Setup.api_note', ['<b>' . esc(company_name()) . '</b>']) ?></div></div>
</div>

<?php if ($newToken): ?>
  <div class="alert alert-success">
    <b><?= lang('Setup.api_new_token_shown', [esc($newToken['name'])]) ?></b>
    <pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;font-size:.95em"><?= esc($newToken['raw']) ?></pre>
  </div>
<?php endif ?>

<div class="row">
  <div class="card" style="flex:2;min-width:340px">
    <h2><?= lang('Setup.api_tokens_th') ?></h2>
    <table class="grid tight">
      <thead><tr><th><?= lang('Report.col_name') ?></th><th><?= lang('Setup.api_prefix') ?></th><th><?= lang('Setup.api_abilities') ?></th><th><?= lang('Setup.api_last_used') ?></th><th><?= lang('App.status') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tokens as $t): ?>
          <?php $revoked = ! empty($t['revoked_at']); ?>
          <tr<?= $revoked ? ' class="muted"' : '' ?>>
            <td><?= esc($t['name']) ?></td>
            <td class="mono small"><?= esc($t['prefix']) ?>&hellip;</td>
            <td class="small"><?= esc(str_replace(',', ', ', (string) $t['abilities'])) ?></td>
            <td class="small"><?= $t['last_used_at'] ? date_id(substr($t['last_used_at'], 0, 10)) : '&mdash;' ?></td>
            <td><?= $revoked ? '<span class="badge badge-red">' . esc(lang('Setup.api_revoked')) . '</span>' : '<span class="badge badge-green">' . esc(lang('App.active')) . '</span>' ?></td>
            <td class="right">
              <?php if (! $revoked): ?>
                <form method="post" action="<?= site_url('api-tokens/' . $t['id'] . '/revoke') ?>" onsubmit="return confirm('<?= esc(lang('Setup.api_revoke_confirm'), 'js') ?>')">
                  <?= csrf_field() ?><button class="btn sm danger" type="submit"><?= lang('Setup.api_revoke') ?></button>
                </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $tokens): ?><tr><td colspan="6" class="muted"><?= lang('Setup.api_no_tokens') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:280px">
    <h2><?= lang('Setup.api_new_token') ?></h2>
    <form method="post" action="<?= site_url('api-tokens') ?>">
      <?= csrf_field() ?>
      <div class="field"><label><?= lang('Report.col_name') ?></label><input name="name" placeholder="<?= esc(lang('Setup.api_name_ph'), 'attr') ?>" required></div>
      <div class="field">
        <label><?= lang('Setup.api_abilities') ?></label>
        <?php foreach ($abilities as $key => $label): ?>
          <label style="display:block;margin:4px 0;font-weight:normal">
            <input type="checkbox" name="abilities[]" value="<?= esc($key, 'attr') ?>" <?= $key === 'read' ? 'checked' : '' ?>>
            <?= $label ?>
          </label>
        <?php endforeach ?>
      </div>
      <button class="btn" type="submit"><?= lang('Setup.api_generate') ?></button>
    </form>
  </div>
</div>

<div class="card">
  <h2><?= lang('Setup.api_using_h') ?></h2>
  <p class="muted small"><?= lang('Setup.api_using_note', ['<code>' . esc(rtrim(site_url('api/v1'), '/')) . '</code>']) ?></p>
  <pre style="white-space:pre-wrap;font-size:.9em;line-height:1.5">curl -X POST <?= esc(rtrim(site_url('api/v1'), '/')) ?>/sales/invoices \
  -H "Authorization: Bearer sa_..." \
  -H "Content-Type: application/json" \
  -d '{
    "external_id": "JMBX-INV-0042",
    "customer": { "code": "C0001", "name": "PT Contoh" },
    "invoice_date": "2026-08-15",
    "due_date": "2026-09-14",
    "reference": "PO-123",
    "ppn_amount": 0,
    "post": true,
    "lines": [
      { "account_code": "4100", "description": "Jasa", "amount": 1000000, "job_code": "JOB-1" }
    ]
  }'</pre>
  <p class="muted small">Other endpoints: <code>GET /ping</code>, <code>GET /accounts?q=</code>, <code>GET /customers</code>, <code>GET /suppliers</code>, <code>GET /jobs</code>, <code>GET /sales/invoices/{external_id|internal_no}</code>, and the same under <code>/purchase</code>.</p>
</div>

<?= $this->endSection() ?>
