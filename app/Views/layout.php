<?php

/** @var string $title */
$seg1   = service('uri')->getSegment(1);
$navFor = static fn (string ...$s): string => in_array($seg1, $s, true) ? 'active' : '';

$canReports = user_can('reports.view');
$logo       = company_logo_url();

/** @var list<array{0:string,1:string,2:string,3:string,4:bool}> $link  [seg, url, icon, langkey, visible] */
$masterData = [
    ['accounts', 'accounts', 'accounts', 'chart_of_accounts', true],
    ['suppliers', 'suppliers', 'supplier', 'suppliers', true],
    ['customers', 'customers', 'customer', 'customers', true],
    ['currencies', 'currencies', 'currency', 'currencies', true],
];
$transactions = [
    ['purchases', 'purchases', 'purchase', 'purchases', module_enabled('PurchaseController')],
    ['sales', 'sales', 'sales', 'sales', module_enabled('SalesController')],
    ['banking', 'banking', 'currency', 'banking', module_enabled('BankingController')],
    ['journals', 'journals', 'journal', 'journals', true],
    ['jobs', 'jobs', 'job', 'jobs', module_enabled('JobController')],
];
$setup = [
    ['periods', 'periods', 'period', 'periods', true],
    ['companies', 'companies', 'accounts', 'companies', user_can('settings.manage')],
    ['settings', 'settings', 'settings', 'settings', user_can('settings.manage')],
    ['control-accounts', 'control-accounts', 'accounts', 'control_accounts', user_can('settings.manage')],
    ['custom-fields', 'custom-fields', 'journal', 'custom_fields', user_can('settings.manage')],
    ['users', 'users', 'users', 'users', user_can('users.manage')],
    ['roles', 'roles', 'users', 'roles', user_can('roles.manage')],
    ['api-tokens', 'api-tokens', 'currency', 'api_tokens', user_can('settings.manage')],
];

$allCompanies = model(\App\Models\CompanyModel::class)->where('is_active', 1)->orderBy('code')->findAll();

$renderGroup = static function (string $heading, array $links) use ($navFor) {
    $visible = array_filter($links, static fn ($l) => $l[4]);
    if (! $visible) {
        return;
    }
    echo '<div class="side-group"><h4>' . esc($heading) . '</h4>';
    foreach ($visible as [$seg, $url, $icon, $langKey]) {
        echo '<a class="side-link ' . $navFor($seg) . '" href="' . site_url($url) . '">'
            . nav_icon($icon) . '<span>' . lang('Nav.' . $langKey) . '</span></a>';
    }
    echo '</div>';
};
?>
<!DOCTYPE html>
<html lang="<?= esc(app_locale()) ?>" data-theme="<?= esc(app_theme()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'Simple Accounting') ?> &middot; <?= esc(company_name()) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
<script>
  (function () {
    try {
      var t = localStorage.getItem('sa-theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    } catch (e) {}
  })();
</script>
</head>
<body>
<div class="app">

  <div class="topbar-m no-print">
    <button type="button" id="navToggle" aria-label="Menu">&#9776;</button>
    <span><?= esc(company_name()) ?></span>
  </div>
  <div class="backdrop no-print" id="navBackdrop"></div>

  <aside class="sidebar no-print" id="sidebar">
    <div class="brand">
      <?php if ($logo): ?>
        <img src="<?= esc($logo) ?>" alt="<?= esc(company_name()) ?>">
      <?php else: ?>
        <span class="brand-fallback"><?= esc(company_initials()) ?></span>
        <span><?= esc(company_name()) ?></span>
      <?php endif ?>
    </div>

    <?php if (count($allCompanies) > 1): ?>
      <form method="post" action="<?= site_url('companies/switch') ?>" class="company-switch">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= esc(current_url()) ?>">
        <select name="company_id" onchange="this.form.submit()">
          <?php foreach ($allCompanies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int) $c['id'] === active_company_id() ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
          <?php endforeach ?>
        </select>
      </form>
    <?php endif ?>

    <nav class="side-nav">
      <div class="side-group">
        <?php if ($canReports): ?>
          <a class="side-link <?= $navFor('dashboard') ?>" href="<?= site_url('dashboard') ?>"><?= nav_icon('dashboard') ?><span><?= lang('Nav.dashboard') ?></span></a>
        <?php endif ?>
      </div>

      <?php $renderGroup(lang('Nav.group_master'), $masterData) ?>
      <?php $renderGroup(lang('Nav.group_txn'), $transactions) ?>

      <?php if ($canReports): ?>
        <div class="side-group">
          <h4><?= lang('Nav.group_reports') ?></h4>
          <a class="side-link <?= $navFor('reports') ?>" href="<?= site_url('reports') ?>"><?= nav_icon('reports') ?><span><?= lang('Nav.reports') ?></span></a>
        </div>
      <?php endif ?>

      <?php $renderGroup(lang('Nav.group_setup'), $setup) ?>
    </nav>

    <div class="side-foot">
      <a class="who" href="<?= site_url('profile') ?>" title="<?= lang('Nav.profile') ?>">
        <?= user_avatar_tag(null, 'avatar-sm') ?>
        <span><?= esc(auth()->user()->username ?? auth()->user()->email) ?></span>
      </a>
      <a href="<?= site_url('logout') ?>"><?= lang('Nav.logout') ?></a>
      <div class="theme-picker" id="themePicker" title="<?= lang('Nav.theme') ?>">
        <button type="button" class="sw-light" data-theme="light" aria-label="Modern light"></button>
        <button type="button" class="sw-green" data-theme="green" aria-label="Green"></button>
        <button type="button" class="sw-blue" data-theme="blue" aria-label="Blue"></button>
        <button type="button" class="sw-dark" data-theme="dark" aria-label="Dark"></button>
      </div>
      <div class="lang-picker" title="<?= lang('Nav.language') ?>">
        <a href="<?= site_url('lang/id') ?>" class="<?= app_locale() === 'id' ? 'on' : '' ?>">ID</a>
        <a href="<?= site_url('lang/en') ?>" class="<?= app_locale() === 'en' ? 'on' : '' ?>">EN</a>
      </div>
    </div>
  </aside>

  <div class="content">
    <main class="wrap">
      <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
      <?php endif ?>
      <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
      <?php endif ?>
      <?php $errs = session()->getFlashdata('errors'); ?>
      <?php if (! empty($errs) && is_array($errs)): ?>
        <div class="alert alert-error">
          <?= lang('App.please_fix') ?>
          <ul><?php foreach ($errs as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul>
        </div>
      <?php endif ?>

      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>

<script>
  (function () {
    var sb = document.getElementById('sidebar'),
        bd = document.getElementById('navBackdrop'),
        tg = document.getElementById('navToggle');
    function close() { sb.classList.remove('open'); bd.classList.remove('show'); }
    if (tg) tg.addEventListener('click', function () {
      sb.classList.toggle('open'); bd.classList.toggle('show');
    });
    if (bd) bd.addEventListener('click', close);

    var picker = document.getElementById('themePicker');
    function mark() {
      var cur = document.documentElement.getAttribute('data-theme') || 'light';
      picker.querySelectorAll('button').forEach(function (b) {
        b.setAttribute('aria-pressed', b.dataset.theme === cur ? 'true' : 'false');
      });
    }
    picker.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      document.documentElement.setAttribute('data-theme', b.dataset.theme);
      try { localStorage.setItem('sa-theme', b.dataset.theme); } catch (err) {}
      mark();
    });
    mark();
  })();
</script>
</body>
</html>
