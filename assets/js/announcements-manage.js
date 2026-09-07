document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('annModal');
  if (!modal) return; // migration not applied yet

  var modalTitle = document.getElementById('annModalTitle');
  var annId = document.getElementById('annId');
  var annTitle = document.getElementById('annTitle');
  var annBody = document.getElementById('annBody');
  var annError = document.getElementById('annError');
  var annSave = document.getElementById('annSave');

  function resetModal() {
    annId.value = '';
    annTitle.value = '';
    annBody.value = '';
    annError.classList.remove('show');
  }

  function openModal() { modal.classList.add('open'); }
  function closeModal() { modal.classList.remove('open'); }

  document.getElementById('addAnnouncementBtn').addEventListener('click', function () {
    resetModal();
    modalTitle.textContent = 'New Announcement';
    openModal();
  });

  document.querySelectorAll('.edit-ann-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      resetModal();
      modalTitle.textContent = 'Edit Announcement';
      annId.value = btn.dataset.id;
      annTitle.value = btn.dataset.title;
      annBody.value = btn.dataset.body;
      openModal();
    });
  });

  document.getElementById('annCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  annSave.addEventListener('click', function () {
    if (!annTitle.value.trim() || !annBody.value.trim()) {
      annError.textContent = 'Please fill in both the title and the message.';
      annError.classList.add('show');
      return;
    }

    annError.classList.remove('show');
    annSave.disabled = true;
    annSave.textContent = 'Saving…';

    fetch('ajax/save-announcement.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        id: annId.value,
        title: annTitle.value.trim(),
        body: annBody.value.trim(),
      }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          annError.textContent = res.data.error || 'Something went wrong.';
          annError.classList.add('show');
          annSave.disabled = false;
          annSave.textContent = 'Save Announcement';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        annError.textContent = 'Network error. Please try again.';
        annError.classList.add('show');
        annSave.disabled = false;
        annSave.textContent = 'Save Announcement';
      });
  });

  // ---------------- Hide / Unhide ----------------
  document.querySelectorAll('.toggle-ann-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var willActivate = btn.dataset.active === '0';
      var verb = willActivate ? 'unhide' : 'hide';
      if (!confirm('Are you sure you want to ' + verb + ' this announcement?')) return;

      fetch('ajax/save-announcement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: btn.dataset.id, toggle_active: '1' }),
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            return;
          }
          window.location.reload();
        })
        .catch(function () { alert('Network error. Please try again.'); });
    });
  });

  // ---------------- Delete ----------------
  document.querySelectorAll('.delete-ann-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Permanently delete "' + btn.dataset.title + '"? This cannot be undone.')) return;

      fetch('ajax/delete-announcement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: btn.dataset.id }),
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            return;
          }
          window.location.reload();
        })
        .catch(function () { alert('Network error. Please try again.'); });
    });
  });
});
