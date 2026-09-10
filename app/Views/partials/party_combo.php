<?php
/**
 * Type-to-search picker for a customer / supplier on a form. Renders a hidden
 * <input> carrying the id plus a text box that filters the list by name, code,
 * email or phone. Drop-in replacement for a plain <select>.
 *
 *   <?= view('partials/party_combo', [
 *       'field'       => 'customer_id',
 *       'items'       => $customers,          // id, code, name, email, phone
 *       'selected'    => old('customer_id', $inv['customer_id'] ?? ''),
 *       'placeholder' => lang('Txn.party_search_customer'),
 *       'required'    => true,
 *   ]) ?>
 *
 * @var string $field
 * @var list<array<string,mixed>> $items
 * @var int|string|null $selected
 * @var string $placeholder
 * @var bool $required
 */
$rows = array_map(static fn ($p) => [
    'id'    => (int) $p['id'],
    'code'  => (string) ($p['code'] ?? ''),
    'name'  => (string) ($p['name'] ?? ''),
    'email' => (string) ($p['email'] ?? ''),
    'phone' => (string) ($p['phone'] ?? ''),
], $items);

$sel      = (string) ($selected ?? '');
$selName  = '';
foreach ($rows as $r) {
    if ((string) $r['id'] === $sel) {
        $selName = $r['name'];
        break;
    }
}
$key = preg_replace('/[^a-z0-9]/i', '', $field);
?>
<div class="combo party-combo" data-combo="party" data-src="__PARTY_<?= esc($key, 'attr') ?>">
  <input type="hidden" name="<?= esc($field, 'attr') ?>" value="<?= esc($sel, 'attr') ?>"<?= ! empty($required) ? ' required' : '' ?>>
  <input class="combo-input" autocomplete="off" spellcheck="false"
         placeholder="<?= esc($placeholder ?? '', 'attr') ?>" value="<?= esc($selName, 'attr') ?>" title="<?= esc($selName, 'attr') ?>">
  <div class="combo-pop" hidden></div>
</div>
<script>window.__PARTY_<?= esc($key) ?> = <?= json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>

<?php if (! ($comboJsDone ?? false)): $comboJsDone = true; ?>
<script>
(function () {
  if (window.initPartyCombo) { return; }
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }
  function toks(q){ return String(q || '').toLowerCase().split(/\s+/).filter(Boolean); }

  window.initPartyCombo = function (root) {
    if (root.__wired) { return; } root.__wired = true;
    var LIST = window[root.dataset.src] || [], byId = {};
    LIST.forEach(function (p) { byId[String(p.id)] = p; });

    var hid = root.querySelector('input[type=hidden]'),
        inp = root.querySelector('.combo-input'),
        pop = root.querySelector('.combo-pop'),
        items = [], active = -1, open = false;

    function selName(){ var p = byId[hid.value]; return p ? p.name : ''; }
    function restore(){ inp.value = selName(); inp.title = inp.value; }

    function place(){
      var r = inp.getBoundingClientRect();
      pop.style.minWidth = Math.max(r.width, 300) + 'px';
      pop.style.left = Math.round(Math.max(6, Math.min(r.left, window.innerWidth - Math.max(r.width, 320) - 10))) + 'px';
      var h = Math.min(pop.scrollHeight, 320), room = window.innerHeight - r.bottom - 8;
      pop.style.top = Math.round(room < h && r.top - 8 > room ? r.top - h - 2 : r.bottom + 2) + 'px';
    }
    function onScroll(e){ if (open && !(e.target && e.target.nodeType === 1 && pop.contains(e.target))) { place(); } }
    function hide(){
      if (!open && pop.hidden) { return; }
      open = false; pop.hidden = true; active = -1;
      if (pop.parentNode === document.body) { root.appendChild(pop); }
      document.removeEventListener('scroll', onScroll, true);
      window.removeEventListener('resize', place);
    }
    function commit(p){
      hid.value = p ? p.id : '';
      restore();
      hide();
      hid.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function optHtml(p, i){
      var sub = [p.email, p.phone].filter(Boolean).join(' &middot; ');
      return '<div class="combo-opt po' + (i === active ? ' on' : '') + '" data-i="' + i + '">'
        + '<div class="po-row"><span class="po-name">' + esc(p.name) + '</span>'
        + (p.code ? '<span class="po-code">' + esc(p.code) + '</span>' : '') + '</div>'
        + (sub ? '<div class="po-sub">' + sub + '</div>' : '')
        + '</div>';
    }
    function show(q){
      var t = toks(q);
      items = LIST.filter(function (p) {
        if (!t.length) { return true; }
        var hay = (p.name + ' ' + p.code + ' ' + p.email + ' ' + p.phone).toLowerCase();
        return t.every(function (x) { return hay.indexOf(x) !== -1; });
      }).slice(0, 80);
      pop.innerHTML = items.length
        ? items.map(optHtml).join('')
        : '<div class="combo-empty">' + esc(<?= json_encode(lang('App.no_records')) ?>) + '</div>';
      if (pop.parentNode !== document.body) { document.body.appendChild(pop); }
      pop.hidden = false; place();
      if (!open) { open = true; document.addEventListener('scroll', onScroll, true); window.addEventListener('resize', place); }
    }
    function move(d){
      if (!open) { show(inp.value === selName() ? '' : inp.value); }
      if (!items.length) { return; }
      active = (active + d + items.length) % items.length;
      var opts = pop.querySelectorAll('.combo-opt');
      opts.forEach(function (o, i) { o.classList.toggle('on', i === active); });
      if (opts[active]) { opts[active].scrollIntoView({ block: 'nearest' }); }
    }

    inp.addEventListener('focus', function () { inp.select(); show(inp.value === selName() ? '' : inp.value); });
    inp.addEventListener('input', function () { active = -1; show(inp.value); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && open) { e.preventDefault(); commit(items[active] || items[0] || null); }
      else if (e.key === 'Escape') { hide(); restore(); inp.blur(); }
    });
    inp.addEventListener('blur', function () {
      setTimeout(function () {
        hide();
        if (byId[hid.value]) { restore(); } else { hid.value = ''; inp.value = ''; inp.title = ''; }
      }, 150);
    });
    pop.addEventListener('mousedown', function (e) {
      e.preventDefault();
      var opt = e.target.closest('.combo-opt'); if (!opt) { return; }
      commit(items[+opt.dataset.i] || null);
    });
  };

  function boot(){ document.querySelectorAll('.party-combo').forEach(window.initPartyCombo); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
</script>
<?php endif ?>
