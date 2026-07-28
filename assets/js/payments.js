document.addEventListener('DOMContentLoaded', function () {
  var rejectModal = document.getElementById('rejectModal');
  var rejectReason = document.getElementById('rejectReason');
  var rejectError = document.getElementById('rejectError');
  var rejectConfirm = document.getElementById('rejectConfirm');
  var rejectCancel = document.getElementById('rejectCancel');
  var currentRejectId = null;

  function openRejectModal(id) {
    currentRejectId = id;
    rejectReason.value = '';
    rejectError.classList.remove('show');
    rejectModal.classList.add('open');
    rejectReason.focus();
  }
  function closeRejectModal() {
    rejectModal.classList.remove('open');
    currentRejectId = null;
  }

  document.querySelectorAll('[data-verify-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.dataset.verifyId;
      if (!confirm('Verify this payment as received? A receipt will be generated and the parishioner notified.')) return;

      btn.disabled = true;
      btn.textContent = 'Verifying…';

      fetch('ajax/verify-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id }),
      })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            btn.disabled = false;
            btn.textContent = 'Verify';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.textContent = 'Verify';
        });
    });
  });

  document.querySelectorAll('[data-reject-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openRejectModal(btn.dataset.rejectId);
    });
  });

  rejectCancel.addEventListener('click', closeRejectModal);
  rejectModal.addEventListener('click', function (e) {
    if (e.target === rejectModal) closeRejectModal();
  });

  rejectConfirm.addEventListener('click', function () {
    var reason = rejectReason.value.trim();
    if (!reason) {
      rejectError.textContent = 'Please explain why this payment is being rejected.';
      rejectError.classList.add('show');
      return;
    }
    rejectError.classList.remove('show');
    rejectConfirm.disabled = true;
    rejectConfirm.textContent = 'Rejecting…';

    fetch('ajax/reject-payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id: currentRejectId, reason: reason }),
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          rejectError.textContent = res.data.error || 'Something went wrong.';
          rejectError.classList.add('show');
          rejectConfirm.disabled = false;
          rejectConfirm.textContent = 'Reject Payment';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        rejectError.textContent = 'Network error. Please try again.';
        rejectError.classList.add('show');
        rejectConfirm.disabled = false;
        rejectConfirm.textContent = 'Reject Payment';
      });
  });
});