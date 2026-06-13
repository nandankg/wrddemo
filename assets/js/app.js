// WRD demo — accessibility + UI interactions
(function () {
  const html = document.documentElement;
  const body = document.body;

  // ---- font scaling (A- / A / A+) ----
  const FS = ['fs-0', 'fs-1', 'fs-2', 'fs-3'];
  function applyFs(level) {
    FS.forEach(c => html.classList.remove(c));
    html.classList.add('fs-' + level);
    localStorage.setItem('wrd_fs', level);
  }
  const savedFs = localStorage.getItem('wrd_fs');
  if (savedFs) applyFs(savedFs);

  // ---- high contrast ----
  if (localStorage.getItem('wrd_hc') === '1') body.classList.add('hc');

  window.WRD = {
    fontSmaller() { applyFs(Math.max(0, (+localStorage.getItem('wrd_fs') || 1) - 1)); },
    fontReset() { applyFs(1); },
    fontLarger() { applyFs(Math.min(3, (+localStorage.getItem('wrd_fs') || 1) + 1)); },
    toggleContrast() {
      body.classList.toggle('hc');
      localStorage.setItem('wrd_hc', body.classList.contains('hc') ? '1' : '0');
    },
    toggleMenu(id) {
      const el = document.getElementById(id);
      if (el) el.classList.toggle('hidden');
    }
  };

  // ---- accessibility toolbar dropdown ----
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-acc-toggle]');
    const panel = document.getElementById('acc-panel');
    if (panel) {
      if (t) panel.classList.toggle('hidden');
      else if (!e.target.closest('#acc-panel')) panel.classList.add('hidden');
    }
  });
})();
