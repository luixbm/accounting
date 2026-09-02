<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>API Tokens</h1><div class="muted small">Machine credentials for <b><?= esc(company_name()) ?></b>. Each token only ever sees this company.</div></div>
</div>

<?php if ($newToken): ?>
  <div class="alert alert-success">
    <b>New token &ldquo;<?= esc($newToken['name']) ?>&rdquo; &mdash; copy it now, it will not be shown again:</b>
    <pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;font-size:.95em"><?= esc($newToken['raw']) ?></pre>
  </div>
<?php endif ?>

<div class="row">
  <div class="card" style="flex:2;min-width:340px">
    <h2>Tokens</h2>
    <table class="grid tight">
      <thead><tr><th>Name</th><th>Prefix</th><th>Abilities</th><th>Last used</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tokens as $t): ?>
          <?php $revoked = ! empty($t['revoked_at']); ?>
          <tr<?= $revoked ? ' class="muted"' : '' ?>>
            <td><?= esc($t['name']) ?></td>
            <td class="mono small"><?= esc($t['prefix']) ?>&hellip;</td>
            <td class="small"><?= esc(str_replace(',', ', ', (string) $t['abilities'])) ?></td>
            <td class="small"><?= $t['last_used_at'] ? date_id(substr($t['last_used_at'], 0, 10)) : '&mdash;' ?></td>
            <td><?= $revoked ? '<span class="badge badge-red">revoked</span>' : '<span class="badge badge-green">active</span>' ?></td>
            <td class="right">
              <?php if (! $revoked): ?>
                <form method="post" action="<?= site_url('api-tokens/' . $t['id'] . '/revoke') ?>" onsubmit="return confirm('Revoke this token? Apps using it stop working immediately.')">
                  <?= csrf_field() ?><button class="btn sm danger" type="submit">Revoke</button>
                </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $tokens): ?><tr><td colspan="6" class="muted">No tokens yet.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:280px">
    <h2>New token</h2>
    <form method="post" action="<?= site_url('api-tokens') ?>">
      <?= csrf_field() ?>
      <div class="field"><label>Name</label><input name="name" placeholder="e.g. Jambix production" required></div>
      <div class="field">
        <label>Abilities</label>
        <?php foreach ($abilities as $key => $label): ?>
          <label style="display:block;margin:4px 0;font-weight:normal">
            <input type="checkbox" name="abilities[]" value="<?= esc($key, 'attr') ?>" <?= $key === 'read' ? 'checked' : '' ?>>
            <?= $label ?>
          </label>
        <?php endforeach ?>
      </div>
      <button class="btn" type="submit">Generate</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Using the API</h2>
  <p class="muted small">Base URL <code><?= esc(rtrim(site_url('api/v1'), '/')) ?></code>. Send the token as a bearer header. Invoices are idempotent on <code>external_id</code> &mdash; repeating a call returns the existing invoice.</p>
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
