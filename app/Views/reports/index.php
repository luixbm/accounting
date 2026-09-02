<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= lang('Nav.reports') ?></h1></div>

<?php foreach ($categories as $catKey => $cat): ?>
  <?php if (empty($byCat[$catKey])) {
      continue;
  } ?>
  <div class="card">
    <h2 class="inline" style="gap:8px">
      <span style="color:var(--brand)"><?= nav_icon($cat['icon']) ?></span> <?= $cat['label'] ?>
    </h2>
    <div class="report-grid">
      <?php foreach ($byCat[$catKey] as $it): ?>
        <?php if ($it['exists']): ?>
          <a class="report-item" href="<?= site_url($it['route']) ?>">
            <span class="ri-icon"><?= nav_icon($it['icon']) ?></span>
            <span class="ri-body"><span class="ri-title"><?= $it['ttl'] ?></span><span class="ri-desc"><?= $it['desc'] ?></span></span>
          </a>
        <?php else: ?>
          <span class="report-item is-soon" title="Coming soon">
            <span class="ri-icon"><?= nav_icon($it['icon']) ?></span>
            <span class="ri-body"><span class="ri-title"><?= $it['ttl'] ?></span><span class="ri-desc"><?= $it['desc'] ?> · <em>soon</em></span></span>
          </span>
        <?php endif ?>
      <?php endforeach ?>
    </div>
  </div>
<?php endforeach ?>

<?= $this->endSection() ?>
