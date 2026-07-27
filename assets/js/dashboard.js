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
  // snapshot the current-month markup exactly as PHP rendered it on page load
  const calDefaultHtml = calPanel ? calPanel.innerHTML : null;

  function resetCalendarIfNeeded(panel) {
    if (panel === calPanel && calDefaultHtml !== null) {
      calPanel.innerHTML = calDefaultHtml;
    }
  }

  function closeAll(except) {
    document.querySelectorAll('.dropdown-panel.open').forEach(function (p) {
      if (p !== except) {
        p.classList.remove('open');
        resetCalendarIfNeeded(p);
      }
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