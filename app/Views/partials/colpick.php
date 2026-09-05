<?php
/**
 * "Columns" show/hide control for a list table. Mirrors the parties list:
 * cells carry data-col="<key>"; hiding toggles the global .col-hidden class.
 * The chosen set is remembered per browser in localStorage.
 *
 * @var string      $key       storage key (unique per list, e.g. 'purchases')
 * @var string      $table     CSS selector of the target <table> (needs an id)
 * @var list<array{0:string,1:string}> $columns  ordered [colKey, label] pairs
 * @var list<string>|null $defaultHidden  keys hidden until the user opts in
 */
$defaultHidden = $defaultHidden ?? [];
$pid           = 'colpick_' . preg_replace('/\W+/', '', $key);
?>
<details class="colpick no-print" id="<?= $pid ?>">
  <summary class="btn ghost"><?= lang('Txn.columns_toggle') ?></summary>
  <div class="colpick-panel">
    <div class="colpick-head">
      <button type="button" class="link-btn" data-all="1"><?= lang('App.all') ?></button>
      <button type="button" class="link-btn" data-all="0"><?= lang('App.none') ?></button>
    </div>
    <?php foreach ($columns as [$k, $lbl]): ?>
      <label><input type="checkbox" value="<?= esc($k, 'attr') ?>"> <?= esc($lbl) ?></label>
    <?php endforeach ?>
  </div>
</details>
<script>
(function () {
  function init() {
  var menu  = document.getElementById(<?= json_encode($pid) ?>);
  var table = document.querySelector(<?= json_encode($table) ?>);
  if (!menu || !table) { return; }
  var KEY   = <?= json_encode('listcols:' . $key) ?>;
  var boxes = Array.prototype.slice.call(menu.querySelectorAll('input[type=checkbox]'));
  var hidden = <?= json_encode(array_values($defaultHidden)) ?>;
  try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (Array.isArray(s)) { hidden = s; } } catch (e) {}

  function apply() {
    boxes.forEach(function (cb) {
      var vis = cb.checked;
      table.querySelectorAll('[data-col="' + cb.value.replace(/"/g, '\\"') + '"]').forEach(function (el) {
        el.classList.toggle('col-hidden', !vis);
      });
    });
  }
  function save() {
    var h = boxes.filter(function (cb) { return !cb.checked; }).map(function (cb) { return cb.value; });
    try { localStorage.setItem(KEY, JSON.stringify(h)); } catch (e) {}
  }

  boxes.forEach(function (cb) { cb.checked = hidden.indexOf(cb.value) === -1; });
  apply();
  menu.addEventListener('change', function () { apply(); save(); });
  menu.querySelectorAll('[data-all]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = b.getAttribute('data-all') === '1';
      boxes.forEach(function (cb) { cb.checked = on; });
      apply(); save();
    });
  });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
