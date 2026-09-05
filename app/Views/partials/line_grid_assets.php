<?php
/**
 * Shared assets for the purchase / sales invoice line grids:
 *   - the searchable account combo (window.initAccountCombo)
 *   - the show/hide-columns control (window.setupColToggle)
 *
 * @var list<array<string,mixed>> $accounts  non-group active accounts (id, code, name)
 */
?>
<style>
  .lines-scroll { overflow-x: auto; }
  .piline-grid select, .piline-grid tbody td > input { width: 100%; }
  .piline-grid tbody td { vertical-align: middle; }
  .piline-grid .bud { background: rgba(128,128,128,.12); color: var(--muted); cursor: default; }

  /* keep the Account column visible while the metadata columns scroll */
  .piline-grid thead th:first-child,
  .piline-grid tbody td:first-child {
    position: sticky; left: 0; z-index: 3;
    background: var(--card, #fff);
    box-shadow: 1px 0 0 var(--rule, #dcdce3);
  }
  .piline-grid thead th:first-child { z-index: 4; background: var(--th-bg, var(--card, #f5f5f7)); }

  /* searchable account combo */
  .combo { position: relative; }
  .combo-input { width: 100%; }
  .combo-pop {
    position: fixed; z-index: 1000; max-width: 480px;
    background: var(--card, #fff); border: 1px solid var(--rule, #ccd);
    border-radius: 7px; box-shadow: 0 8px 26px rgba(0,0,0,.18);
    max-height: 280px; overflow-y: auto; padding: 3px;
  }
  .combo-opt { padding: 5px 9px; border-radius: 5px; cursor: pointer; font-size: .84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .combo-opt b { font-family: ui-monospace, Menlo, Consolas, monospace; margin-right: 6px; }
  .combo-opt.on, .combo-opt:hover { background: var(--brand-soft, rgba(80,120,255,.14)); }
  .combo-empty { padding: 7px 9px; color: var(--muted); font-size: .8rem; }

  /* columns menu */
  .colmenu-wrap { position: relative; display: inline-block; }
  .colmenu {
    position: absolute; z-index: 50; right: 0; top: calc(100% + 4px); min-width: 180px;
    background: var(--card, #fff); border: 1px solid var(--rule, #ccd); border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.16); padding: 6px;
  }
  .colmenu label { display: flex; gap: 7px; align-items: center; padding: 4px 6px; font-size: .84rem; cursor: pointer; border-radius: 5px; white-space: nowrap; }
  .colmenu label:hover { background: var(--brand-soft, rgba(80,120,255,.12)); }
  .colmenu input { width: auto; }
</style>

<script>
window.__ACCTS = <?= json_encode(array_map(static fn ($a) => ['id' => (int) $a['id'], 'code' => $a['code'], 'name' => $a['name']], $accounts), JSON_UNESCAPED_SLASHES) ?>;
(function () {
  var ACCTS = window.__ACCTS || [], byId = {};
  var NOMATCH = <?= json_encode(lang('App.no_records')) ?>;
  ACCTS.forEach(function (a) { byId[String(a.id)] = a; });
  function esc(s){ return String(s).replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }

  function label(a){ return a ? (a.code + ' · ' + a.name) : ''; }
  function tokens(q){ return String(q || '').toLowerCase().replace(/[·.]+/g, ' ').split(/\s+/).filter(Boolean); }

  window.initAccountCombo = function (root) {
    if (root.__wired) { return; } root.__wired = true;
    var hid = root.querySelector('input[type=hidden]'),
        inp = root.querySelector('.combo-input'),
        pop = root.querySelector('.combo-pop'),
        items = [], active = -1, open = false;

    function selLabel(){ return label(byId[hid.value]); }
    function restore(){ inp.value = selLabel(); }

    function place(){
      var r = inp.getBoundingClientRect();
      pop.style.minWidth = Math.max(r.width, 320) + 'px';
      pop.style.left = Math.round(Math.max(4, Math.min(r.left, window.innerWidth - 344))) + 'px';
      var h = Math.min(pop.scrollHeight, 280), room = window.innerHeight - r.bottom - 8;
      pop.style.top = Math.round(room < h && r.top - 8 > room ? r.top - h - 2 : r.bottom + 2) + 'px';
    }
    function onScroll(e){
      if (e.target === pop || (e.target && e.target.nodeType === 1 && pop.contains(e.target))) { return; }
      if (open) { place(); }
    }
    function hidePop(){
      if (!open && pop.hidden) { return; }
      open = false; pop.hidden = true; active = -1;
      if (pop.parentNode === document.body) { root.appendChild(pop); }
      document.removeEventListener('scroll', onScroll, true);
      window.removeEventListener('resize', place);
    }
    function commit(a){
      hid.value = a ? a.id : '';
      inp.value = label(a);
      inp.title = label(a);
      hidePop();
      hid.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function showPop(q){
      var toks = tokens(q);
      items = ACCTS.filter(function (a) {
        if (!toks.length) { return true; }
        var hay = (a.code + ' ' + a.name).toLowerCase();
        return toks.every(function (t) { return hay.indexOf(t) !== -1; });
      }).slice(0, 60);
      pop.innerHTML = items.length
        ? items.map(function (a, i) { return '<div class="combo-opt' + (i === active ? ' on' : '') + '" data-i="' + i + '"><b>' + esc(a.code) + '</b>' + esc(a.name) + '</div>'; }).join('')
        : '<div class="combo-empty">' + esc(NOMATCH) + '</div>';
      if (pop.parentNode !== document.body) { document.body.appendChild(pop); }
      pop.hidden = false;
      place();
      if (!open) {
        open = true;
        document.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', place);
      }
    }
    function move(d){
      if (!open) { showPop(inp.value === selLabel() ? '' : inp.value); }
      if (!items.length) { return; }
      active = (active + d + items.length) % items.length;
      var opts = pop.querySelectorAll('.combo-opt');
      opts.forEach(function (o, i) { o.classList.toggle('on', i === active); });
      if (opts[active]) { opts[active].scrollIntoView({ block: 'nearest' }); }
    }

    inp.addEventListener('focus', function () { inp.select(); showPop(inp.value === selLabel() ? '' : inp.value); });
    inp.addEventListener('input', function () { active = -1; showPop(inp.value); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && open) { e.preventDefault(); commit(items[active] || items[0] || null); }
      else if (e.key === 'Escape') { hidePop(); restore(); inp.blur(); }
    });
    inp.addEventListener('blur', function () {
      setTimeout(function () {
        hidePop();
        if (byId[hid.value]) { restore(); } else { hid.value = ''; inp.value = ''; inp.title = ''; }
      }, 150);
    });
    // Keep focus on the input while the pointer is inside the popup, so the
    // blur/close race can't swallow the click that selects an option.
    pop.addEventListener('mousedown', function (e) {
      e.preventDefault();
      var opt = e.target.closest('.combo-opt'); if (!opt) { return; }
      commit(items[+opt.dataset.i] || null);
    });
  };

  /**
   * Wire a show/hide-columns menu.
   * opts = { table, btn, menu, storeKey }  (CSS selectors + a localStorage key)
   * Menu must contain <input type="checkbox" data-col="KEY"> items; the grid's
   * cells for that column must carry class "col-KEY".
   */
  window.setupColToggle = function (opts) {
    var table = document.querySelector(opts.table),
        btn   = document.querySelector(opts.btn),
        menu  = document.querySelector(opts.menu);
    if (!table || !btn || !menu) { return; }

    function load(){ try { return JSON.parse(localStorage.getItem(opts.storeKey) || '{}') || {}; } catch (e) { return {}; } }
    function save(s){ try { localStorage.setItem(opts.storeKey, JSON.stringify(s)); } catch (e) {} }
    var state = load();

    function apply(){
      menu.querySelectorAll('input[data-col]').forEach(function (cb) {
        var k = cb.dataset.col, hidden = state[k] === false;
        cb.checked = !hidden;
        table.querySelectorAll('.col-' + k).forEach(function (el) { el.style.display = hidden ? 'none' : ''; });
      });
    }
    window.__applyCols = apply;

    menu.addEventListener('change', function (e) {
      var cb = e.target.closest('input[data-col]'); if (!cb) { return; }
      state[cb.dataset.col] = cb.checked; save(state); apply();
    });
    btn.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; });
    document.addEventListener('click', function (e) {
      if (!menu.hidden && !menu.contains(e.target) && e.target !== btn) { menu.hidden = true; }
    });
    apply();
  };
})();
</script>
