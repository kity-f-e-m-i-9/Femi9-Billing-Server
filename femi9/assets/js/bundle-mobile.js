/* Bundle pages — shared mobile helpers (see assets/css/bundle-mobile.css).
   1. Labels every table cell from its column header so tables can render
      as stacked cards on phones (also re-runs for rows added later).
   2. Scrolls the active quick-access tab into view.
   3. Pins the page's primary submit button into a fixed bottom bar. */
(function () {
    'use strict';

    function labelTable(table) {
        if (table.classList.contains('no-cards')) return;
        var heads = [];
        var headRow = table.querySelector('thead tr');
        if (!headRow) return;
        Array.prototype.forEach.call(headRow.children, function (th) {
            heads.push((th.textContent || '').replace(/\s+/g, ' ').trim());
        });
        table.classList.add('bm-cards');
        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
            Array.prototype.forEach.call(tr.children, function (td, i) {
                if (!td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i] || '');
            });
        });
    }

    function labelAll() {
        Array.prototype.forEach.call(document.querySelectorAll('.app-content table'), labelTable);
    }

    function pinPrimaryActions() {
        var selectors = ['#rmbForm button[type="submit"]', '#btSubmit'];
        selectors.forEach(function (sel) {
            var btn = document.querySelector(sel);
            if (!btn || !btn.parentElement) return;
            var bar = btn.parentElement;
            // Only pin when the button sits in its own wrapper div.
            if (bar.tagName !== 'DIV' || bar.classList.contains('bm-fixed-actions')) return;
            bar.classList.add('bm-fixed-actions');
            document.body.classList.add('bm-has-bar');
        });
    }

    function scrollActiveTab() {
        var tab = document.querySelector('.ata-nav-tab.active');
        if (tab && tab.scrollIntoView) {
            try { tab.scrollIntoView({ inline: 'center', block: 'nearest' }); } catch (e) { /* older browsers */ }
        }
    }

    function collapsibleFilters() {
        if (!window.matchMedia('(max-width: 767px)').matches) return;
        Array.prototype.forEach.call(document.querySelectorAll('[data-bm-collapsible]'), function (box) {
            if (box.previousElementSibling && box.previousElementSibling.classList.contains('bm-filter-toggle')) return;
            var active = 0;
            Array.prototype.forEach.call(box.querySelectorAll('select, input[type="text"], input[type="date"], input[type="number"], input[type="search"]'), function (el) {
                if (el.value) active++;
            });
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bm-filter-toggle';
            btn.innerHTML = '<span>Filters' + (active ? '<span class="bm-count">' + active + '</span>' : '') + '</span><i class="material-icons-outlined">expand_more</i>';
            box.parentNode.insertBefore(btn, box);
            box.classList.add('bm-collapsed');
            btn.addEventListener('click', function () { box.classList.toggle('bm-collapsed'); });
        });
    }

    function init() {
        labelAll();
        collapsibleFilters();
        pinPrimaryActions();
        scrollActiveTab();
        // Rows built by page scripts (e.g. the transfer page's bundle list).
        var pending = false;
        var host = document.querySelector('.app-content') || document.body;
        new MutationObserver(function () {
            if (pending) return;
            pending = true;
            window.requestAnimationFrame(function () { pending = false; labelAll(); });
        }).observe(host, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
