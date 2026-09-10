<?php

/**
 * Classic shell — left sidebar, collapsible nav groups, mobile slide-in drawer.
 * This is the original layout; `layout.php` includes it when design = classic.
 * Nav data comes from layouts/_nav.php so the modern shell stays in sync.
 */
$nav       = require __DIR__ . '/_nav.php';
$navFor    = $nav['navFor'];
$activeSeg = $nav['activeSeg'];
$logo      = company_logo_url();

$renderGroup = static function (string $heading, array $links, ?string $collapseKey = null) use ($navFor, $activeSeg) {
    $visible = array_filter($links, static fn ($l) => $l[4]);
    if (! $visible) {
        return;
    }
    // Collapsible groups start collapsed unless the current page lives inside
    // them (so the active link stays visible); JS then restores the user's choice.
    $collapsible = $collapseKey !== null;
    $forceOpen   = $collapsible && in_array($activeSeg, array_column($visible, 0), true);
    $collapsed   = $collapsible && ! $forceOpen;

    echo '<div class="side-group' . ($collapsed ? ' collapsed' : '') . '"'
        . ($collapsible ? ' data-navgroup="' . esc($collapseKey, 'attr') . '"' : '') . '>';
    if ($collapsible) {
        echo '<button type="button" class="side-group-h">' . esc($heading)
            . '<span class="side-caret" aria-hidden="true">&#9656;</span></button><div class="side-sub">';
    } else {
        echo '<h4>' . esc($heading) . '</h4>';
    }
    foreach ($visible as [$seg, $url, $icon, $langKey]) {
        echo '<a class="side-link ' . $navFor($seg) . '" href="' . site_url($url) . '">'
            . nav_icon($icon) . '<span>' . lang('Nav.' . $langKey) . '</span></a>';
    }
    echo ($collapsible ? '</div>' : '') . '</div>';
};
?>
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

    <?php if (count($nav['companies']) > 1): ?>
      <form method="post" action="<?= site_url('companies/switch') ?>" class="company-switch">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= esc(current_url()) ?>">
        <select name="company_id" onchange="this.form.submit()">
          <?php foreach ($nav['companies'] as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int) $c['id'] === active_company_id() ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
          <?php endforeach ?>
        </select>
      </form>
    <?php endif ?>

    <nav class="side-nav">
      <div class="side-group">
        <?php if ($nav['dashboard']): ?>
          <a class="side-link <?= $navFor('dashboard') ?>" href="<?= site_url('dashboard') ?>"><?= nav_icon('dashboard') ?><span><?= lang('Nav.dashboard') ?></span></a>
        <?php endif ?>
      </div>

      <?php $renderGroup(lang('Nav.group_master'), $nav['master']) ?>
      <?php $renderGroup(lang('Nav.group_txn'), $nav['txn']) ?>

      <?php if ($nav['reports']): ?>
        <div class="side-group">
          <h4><?= lang('Nav.group_reports') ?></h4>
          <a class="side-link <?= $navFor('reports') ?>" href="<?= site_url('reports') ?>"><?= nav_icon('reports') ?><span><?= lang('Nav.reports') ?></span></a>
        </div>
      <?php endif ?>

      <?php $renderGroup(lang('Nav.group_setup'), $nav['setup'], 'setup') ?>
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

  <script>
    // Collapsible sidebar groups - restore each group's remembered state, and
    // save it on click. A group the server force-opened (active page inside)
    // stays open unless the user collapses it here.
    (function () {
      document.querySelectorAll('.side-group[data-navgroup]').forEach(function (g) {
        var key = 'sa-nav-' + g.getAttribute('data-navgroup');
        var head = g.querySelector('.side-group-h');
        if (!head) return;
        try {
          var saved = localStorage.getItem(key);
          if (saved === 'open') g.classList.remove('collapsed');
          else if (saved === 'closed' && !g.querySelector('.side-link.active')) g.classList.add('collapsed');
        } catch (e) {}
        head.addEventListener('click', function () {
          var collapsed = g.classList.toggle('collapsed');
          try { localStorage.setItem(key, collapsed ? 'closed' : 'open'); } catch (e) {}
        });
      });
    })();
  </script>

  <div class="content">
    <main class="wrap">
      <?php include __DIR__ . '/_page_chrome.php'; ?>
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
  })();
</script>
