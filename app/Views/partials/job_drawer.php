<?php
/**
 * Read-only job quick-look drawer for the purchase / sales invoice forms.
 * Slides in from the right; search by job code, name or customer; shows job
 * number, name, customer, arrival / end date, pax and status. Click a row to
 * copy its code. State (open / closed) is remembered per browser.
 *
 * Include once per page: <?= view('partials/job_drawer') ?>
 */
?>
<div class="jobdrawer" id="jobDrawer" data-endpoint="<?= site_url('jobs/lookup') ?>" data-jobsurl="<?= site_url('jobs') ?>">
  <button type="button" class="jobdrawer-tab" id="jobDrawerTab" aria-controls="jobDrawerPanel" aria-expanded="false">
    <span class="jobdrawer-tab-txt"><?= lang('Txn.job_finder') ?></span>
  </button>

  <aside class="jobdrawer-panel" id="jobDrawerPanel" hidden aria-label="<?= esc(lang('Txn.job_finder'), 'attr') ?>">
    <header class="jd-head">
      <strong><?= lang('Txn.job_finder') ?></strong>
      <button type="button" class="jd-close" id="jobDrawerClose" title="<?= esc(lang('App.close'), 'attr') ?>" aria-label="<?= esc(lang('App.close'), 'attr') ?>">&times;</button>
    </header>
    <div class="jd-search">
      <input type="search" id="jobDrawerQ" autocomplete="off" spellcheck="false"
             placeholder="<?= esc(lang('Txn.job_finder_ph'), 'attr') ?>">
    </div>
    <p class="jd-hint muted small"><?= lang('Txn.job_finder_hint') ?></p>
    <div class="jd-results" id="jobDrawerResults" aria-live="polite"></div>
    <footer class="jd-foot">
      <a href="<?= site_url('jobs') ?>" target="_blank" rel="noopener"><?= lang('Txn.job_finder_open') ?> &nearr;</a>
    </footer>
  </aside>
</div>

<script>
(function () {
  var root = document.getElementById('jobDrawer');
  if (!root || root.dataset.wired) { return; }
  root.dataset.wired = '1';

  var panel   = document.getElementById('jobDrawerPanel'),
      tab     = document.getElementById('jobDrawerTab'),
      closeB  = document.getElementById('jobDrawerClose'),
      input   = document.getElementById('jobDrawerQ'),
      results = document.getElementById('jobDrawerResults'),
      KEY     = 'sa.jobdrawer.open',
      endpoint = root.dataset.endpoint,
      loaded  = false, timer = null, lastReq = 0;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmtDate(d) {
    if (!d || d === '0000-00-00') { return '—'; }
    var p = String(d).slice(0, 10).split('-');
    if (p.length !== 3) { return esc(d); }
    var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][+p[1] - 1] || p[1];
    return p[2] + ' ' + m + ' ' + p[0];
  }

  function open() {
    panel.hidden = false;
    root.classList.add('is-open');
    tab.setAttribute('aria-expanded', 'true');
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
    if (!loaded) { fetchJobs(''); }
    setTimeout(function () { input.focus(); }, 60);
  }
  function close() {
    root.classList.remove('is-open');
    tab.setAttribute('aria-expanded', 'false');
    try { localStorage.setItem(KEY, '0'); } catch (e) {}
    setTimeout(function () { if (!root.classList.contains('is-open')) { panel.hidden = true; } }, 200);
  }
  function toggle() { root.classList.contains('is-open') ? close() : open(); }

  function render(jobs) {
    if (!jobs || !jobs.length) {
      results.innerHTML = '<p class="jd-empty muted small"><?= esc(lang('Txn.job_finder_none'), 'js') ?></p>';
      return;
    }
    var html = '<ul class="jd-list">';
    jobs.forEach(function (j) {
      var meta = [];
      if (j.customer_name) { meta.push(esc(j.customer_name)); }
      meta.push('<?= esc(lang('Txn.arrival'), 'js') ?> ' + fmtDate(j.start_date));
      if (j.end_date) { meta.push(fmtDate(j.end_date)); }
      if (j.pax) { meta.push(esc(j.pax) + ' pax'); }
      var st = (j.status || '').toLowerCase();
      html += '<li class="jd-item" data-code="' + esc(j.code) + '" tabindex="0" role="button" '
            + 'title="<?= esc(lang('Txn.job_finder_copy'), 'attr') ?>">'
            + '<div class="jd-row1"><span class="jd-code mono">' + esc(j.code) + '</span>'
            + '<span class="jd-status jd-status-' + esc(st) + '">' + esc(j.status || '') + '</span></div>'
            + '<div class="jd-name">' + esc(j.name) + '</div>'
            + '<div class="jd-meta muted small">' + meta.join(' &middot; ') + '</div>'
            + '</li>';
    });
    html += '</ul>';
    results.innerHTML = html;
  }

  function fetchJobs(q) {
    var reqId = ++lastReq;
    results.setAttribute('aria-busy', 'true');
    results.classList.add('is-loading');
    fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.ok ? r.json() : { jobs: [] }; })
      .then(function (d) {
        if (reqId !== lastReq) { return; }   // a newer keystroke won
        loaded = true;
        render(d.jobs || []);
      })
      .catch(function () { if (reqId === lastReq) { render([]); } })
      .finally(function () {
        if (reqId === lastReq) {
          results.removeAttribute('aria-busy');
          results.classList.remove('is-loading');
        }
      });
  }

  function copyCode(code) {
    var done = function () {
      var el = results.querySelector('.jd-item[data-code="' + (window.CSS && CSS.escape ? CSS.escape(code) : code) + '"]');
      if (!el) { return; }
      el.classList.add('is-copied');
      setTimeout(function () { el.classList.remove('is-copied'); }, 900);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(done, done);
    } else {
      var t = document.createElement('textarea');
      t.value = code; document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(t); done();
    }
  }

  tab.addEventListener('click', toggle);
  closeB.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.classList.contains('is-open')) { close(); }
  });
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    timer = setTimeout(function () { fetchJobs(q); }, 250);
  });
  results.addEventListener('click', function (e) {
    var li = e.target.closest('.jd-item'); if (li) { copyCode(li.dataset.code); }
  });
  results.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') { return; }
    var li = e.target.closest('.jd-item'); if (li) { e.preventDefault(); copyCode(li.dataset.code); }
  });

  var wantOpen = '0';
  try { wantOpen = localStorage.getItem(KEY) || '0'; } catch (e) {}
  if (wantOpen === '1') { open(); }
})();
</script>
