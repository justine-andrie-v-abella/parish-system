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
(function () {
  const toggles = document.querySelectorAll('[data-dropdown-toggle]');
  function closeAll(except) {
    document.querySelectorAll('.dropdown-panel.open').forEach(p => {
      if (p !== except) p.classList.remove('open');
    });
    document.querySelectorAll('.icon-btn.open').forEach(b => {
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