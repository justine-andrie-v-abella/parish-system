// Sliding tab switcher. Expects:
// <div class="tab-panel-wrap">
//   <div class="tab-switch" data-active="first-tab">
//     <div class="tab-indicator"></div>
//     <button class="tab-btn active" data-tab="first-tab">...</button>
//     <button class="tab-btn" data-tab="second-tab">...</button>
//   </div>
//   <div class="tab-panel active" id="tab-first-tab">...</div>
//   <div class="tab-panel" id="tab-second-tab">...</div>
// </div>
//assets\js\dashboard.js
document.querySelectorAll('.tab-switch').forEach(switcher => {
  const wrap = switcher.closest('.tab-panel-wrap') || switcher.parentElement;
  const buttons = switcher.querySelectorAll('.tab-btn');

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;
      switcher.dataset.active = target;
      buttons.forEach(b => b.classList.toggle('active', b === btn));
      wrap.querySelectorAll(':scope > .tab-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === 'tab-' + target);
      });
    });
  });
});

// Header dropdowns (calendar / notifications bell icons)
// Includes: open/close toggling, click-outside/escape-to-close, and
// resetting the calendar dropdown back to the current month whenever it closes.
(function () {
  const toggles = document.querySelectorAll('[data-dropdown-toggle]');
  const calPanel = document.querySelector('.dropdown-panel[data-dropdown="cal"]');
  const calDefaultHtml = calPanel ? calPanel.innerHTML : null;

  function resetCalendarIfNeeded(panel) {
    if (panel === calPanel && calDefaultHtml !== null) {
      calPanel.innerHTML = calDefaultHtml;
    }
  }

  function closeAll(except) {
    document.querySelectorAll('.dropdown-panel.open').forEach(function (p) {
      if (p !== except) { p.classList.remove('open'); resetCalendarIfNeeded(p); }
    });
    document.querySelectorAll('.icon-btn.open').forEach(function (b) {
      if (b !== except) { b.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); }
    });
  }

  toggles.forEach(btn => {
    const key = btn.dataset.dropdownToggle;
    const panel = document.querySelector(`.dropdown-panel[data-dropdown="${key}"]`);
    if (!panel) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = !panel.classList.contains('open');
      closeAll();
      if (willOpen) {
        panel.classList.add('open');
        btn.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
    panel.addEventListener('click', (e) => e.stopPropagation());
  });

  document.addEventListener('click', () => closeAll());
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });
})();

// Calendar month navigation (prev/next inside the header calendar dropdown)
(function () {
  const panel = document.querySelector('.dropdown-panel.dp-calendar');
  if (!panel) return;

  function loadMonth(month, year) {
    const params = new URLSearchParams({ month, year });
    fetch('calendar-fragment.php?' + params.toString(), {
      credentials: 'same-origin'
    })
      .then(res => {
        if (!res.ok) throw new Error('Failed to load calendar: ' + res.status);
        return res.text();
      })
      .then(html => {
        panel.innerHTML = html;
      })
      .catch(err => console.error(err));
  }

  // Delegated click handler — works for nav links even after panel.innerHTML is replaced
  panel.addEventListener('click', (e) => {
    const link = e.target.closest('.cal-nav-link');
    if (!link) return;
    e.preventDefault();
    e.stopPropagation(); // don't let the outer dropdown-close-on-click handler fire
    loadMonth(link.dataset.month, link.dataset.year);
  });
})();

function positionDropdown(panel, btn) {
  const margin = 16;
  const btnRect = btn.getBoundingClientRect();
  const panelWidth = panel.offsetWidth || 360;

  // default: right-aligned under the button
  let left = btnRect.right - panelWidth;
  // clamp so it never goes past the left edge
  left = Math.max(margin, left);
  // clamp so it never goes past the right edge
  left = Math.min(left, window.innerWidth - panelWidth - margin);

  const top = btnRect.bottom + 12;

  panel.style.left = left + 'px';
  panel.style.right = 'auto';
  panel.style.top = top + 'px';
}

// ---------------- Generic "Actions" / "Filter" / "Export" dropdowns ----------------
// Shared across every page that has one (queue.php, certificate-queue.php,
// payments.php, ...) so the same markup/behavior works everywhere without
// each page re-implementing it. The menu is repositioned with JS and moved
// to <body> rather than relying on CSS position:absolute, because several
// of these triggers live inside a table wrapper that needs
// overflow:hidden/overflow-x:auto for its rounded corners and mobile
// horizontal scroll — a plain absolutely-positioned child would get
// invisibly clipped by that same overflow instead of floating above it.
(function () {
  function closeAllMenus(except) {
    document.querySelectorAll('.actions-menu.open').forEach(function (menu) {
      if (menu !== except) menu.classList.remove('open');
    });
    document.querySelectorAll('.actions-trigger.open').forEach(function (btn) {
      if (btn !== except) { btn.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }

  function positionMenu(menu, trigger) {
    var margin = 8;
    var rect = trigger.getBoundingClientRect();
    var menuWidth = menu.offsetWidth || 180;
    var menuHeight = menu.offsetHeight || 0;

    var left = rect.right - menuWidth;
    left = Math.max(margin, Math.min(left, window.innerWidth - menuWidth - margin));

    var top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - margin && rect.top - menuHeight - 6 > margin) {
      top = rect.top - menuHeight - 6; // not enough room below — open upward instead
    }

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }

  document.querySelectorAll('.actions-dropdown').forEach(function (wrap) {
    var trigger = wrap.querySelector('.actions-trigger');
    var menu = wrap.querySelector('.actions-menu');
    if (!trigger || !menu) return;

    document.body.appendChild(menu); // escape any overflow:hidden ancestor

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = !menu.classList.contains('open');
      closeAllMenus();
      if (willOpen) {
        menu.classList.add('open');
        trigger.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
        positionMenu(menu, trigger);
      }
    });

    menu.addEventListener('click', function (e) {
      e.stopPropagation();
      // Plain navigation links (filter/export options) close naturally on
      // navigation; buttons (row actions) close immediately since they
      // either open their own modal or reload the page on success.
      if (e.target.closest('button')) closeAllMenus();
    });
  });

  document.addEventListener('click', function () { closeAllMenus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllMenus(); });
  window.addEventListener('resize', function () { closeAllMenus(); });
  window.addEventListener('scroll', function () { closeAllMenus(); }, true);
})();