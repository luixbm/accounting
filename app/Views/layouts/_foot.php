<?php

/**
 * Shared end-of-body script for every shell: colour-theme picker + dismissible
 * announcement banners. Shell-specific behaviour (mobile nav toggle, dropdowns)
 * stays in the shell file.
 */
?>
<script>
  (function () {
    var picker = document.getElementById('themePicker');
    if (picker) {
      var mark = function () {
        var cur = document.documentElement.getAttribute('data-theme') || 'light';
        picker.querySelectorAll('button').forEach(function (b) {
          b.setAttribute('aria-pressed', b.dataset.theme === cur ? 'true' : 'false');
        });
      };
      picker.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        document.documentElement.setAttribute('data-theme', b.dataset.theme);
        try { localStorage.setItem('sa-theme', b.dataset.theme); } catch (err) {}
        mark();
      });
      mark();
    }

    // Dismissible announcement banners - remembered per browser, keyed by
    // id + last-updated so an edited announcement re-appears.
    var stack = document.getElementById('announceStack');
    if (stack) {
      var KEY = 'sa-ann-dismissed', seen = [];
      try { seen = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { seen = []; }
      stack.querySelectorAll('.announce').forEach(function (el) {
        var k = el.getAttribute('data-ann');
        if (seen.indexOf(k) !== -1) { el.hidden = true; return; }
        el.querySelector('.announce-x').addEventListener('click', function () {
          el.hidden = true;
          if (seen.indexOf(k) === -1) seen.push(k);
          try { localStorage.setItem(KEY, JSON.stringify(seen.slice(-100))); } catch (e) {}
          if (!stack.querySelector('.announce:not([hidden])')) stack.hidden = true;
        });
      });
      if (!stack.querySelector('.announce:not([hidden])')) stack.hidden = true;
    }
  })();
</script>
