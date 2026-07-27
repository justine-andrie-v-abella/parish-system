document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-cancel-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Cancel this request? This can\'t be undone.')) return;

      var id = btn.dataset.id;
      btn.disabled = true;
      btn.textContent = 'Cancelling…';

      fetch('ajax/cancel-appointment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id }),
      })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Could not cancel this request.');
            btn.disabled = false;
            btn.textContent = 'Cancel';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.textContent = 'Cancel';
        });
    });
  });
});