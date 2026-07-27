<script>
document.querySelectorAll('.pw-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show
      ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.4 5.5A9.6 9.6 0 0 1 12 5c6 0 9.5 6 9.5 6a13.6 13.6 0 0 1-2.9 3.6M6.4 6.4A13.6 13.6 0 0 0 2.5 11s3.5 6 9.5 6a9.6 9.6 0 0 0 3.4-.6"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>';
  });
});

// Demo-only: prevent actual submission, show a friendly placeholder state.
document.querySelectorAll('form[data-demo]').forEach(form => {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      const original = btn.textContent;
      btn.textContent = 'Connect this form to your PHP backend →';
      setTimeout(() => { btn.textContent = original; }, 2200);
    }
  });
});
</script>
</body>
</html>