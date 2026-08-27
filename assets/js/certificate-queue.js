document.addEventListener('DOMContentLoaded', function () {
  function postForm(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data),
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
  }

  // ---------------- Approve ----------------
  document.querySelectorAll('[data-approve-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Approve this certificate request?')) return;
      btn.disabled = true;
      btn.textContent = 'Approving…';
      postForm('ajax/approve-certificate.php', { id: btn.dataset.approveId })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            btn.disabled = false;
            btn.textContent = 'Approve';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.textContent = 'Approve';
        });
    });
  });

  // ---------------- Mark Completed ----------------
  document.querySelectorAll('[data-complete-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Mark this certificate as completed and released?')) return;
      btn.disabled = true;
      btn.textContent = 'Saving…';
      postForm('ajax/complete-certificate.php', { id: btn.dataset.completeId })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            btn.disabled = false;
            btn.textContent = 'Mark Completed';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.textContent = 'Mark Completed';
        });
    });
  });

  // ---------------- Reject ----------------
  var rejectModal = document.getElementById('rejectModal');
  var rejectReason = document.getElementById('rejectReason');
  var rejectError = document.getElementById('rejectError');
  var rejectConfirm = document.getElementById('rejectConfirm');
  var currentRejectId = null;

  document.querySelectorAll('[data-reject-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentRejectId = btn.dataset.rejectId;
      rejectReason.value = '';
      rejectError.classList.remove('show');
      rejectModal.classList.add('open');
      rejectReason.focus();
    });
  });
  document.getElementById('rejectCancel').addEventListener('click', function () { rejectModal.classList.remove('open'); });
  rejectModal.addEventListener('click', function (e) { if (e.target === rejectModal) rejectModal.classList.remove('open'); });

  rejectConfirm.addEventListener('click', function () {
    var reason = rejectReason.value.trim();
    if (!reason) {
      rejectError.textContent = 'Please explain why this request is being rejected.';
      rejectError.classList.add('show');
      return;
    }
    rejectError.classList.remove('show');
    rejectConfirm.disabled = true;
    rejectConfirm.textContent = 'Rejecting…';
    postForm('ajax/reject-certificate.php', { id: currentRejectId, reason: reason })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          rejectError.textContent = res.data.error || 'Something went wrong.';
          rejectError.classList.add('show');
          rejectConfirm.disabled = false;
          rejectConfirm.textContent = 'Reject';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        rejectError.textContent = 'Network error. Please try again.';
        rejectError.classList.add('show');
        rejectConfirm.disabled = false;
        rejectConfirm.textContent = 'Reject';
      });
  });

  // ---------------- Request documents ----------------
  var docsModal = document.getElementById('docsModal');
  var docsMessage = document.getElementById('docsMessage');
  var docsError = document.getElementById('docsError');
  var docsConfirm = document.getElementById('docsConfirm');
  var currentDocsId = null;

  document.querySelectorAll('[data-docs-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentDocsId = btn.dataset.docsId;
      docsMessage.value = '';
      docsError.classList.remove('show');
      docsModal.classList.add('open');
      docsMessage.focus();
    });
  });
  document.getElementById('docsCancel').addEventListener('click', function () { docsModal.classList.remove('open'); });
  docsModal.addEventListener('click', function (e) { if (e.target === docsModal) docsModal.classList.remove('open'); });

  docsConfirm.addEventListener('click', function () {
    var msg = docsMessage.value.trim();
    if (!msg) {
      docsError.textContent = 'Please specify what documents are needed.';
      docsError.classList.add('show');
      return;
    }
    docsError.classList.remove('show');
    docsConfirm.disabled = true;
    docsConfirm.textContent = 'Sending…';
    postForm('ajax/request-certificate-documents.php', { id: currentDocsId, message: msg })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          docsError.textContent = res.data.error || 'Something went wrong.';
          docsError.classList.add('show');
          docsConfirm.disabled = false;
          docsConfirm.textContent = 'Send Reminder';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        docsError.textContent = 'Network error. Please try again.';
        docsError.classList.add('show');
        docsConfirm.disabled = false;
        docsConfirm.textContent = 'Send Reminder';
      });
  });
});
