document.addEventListener('DOMContentLoaded', function () {
  // ---------------- Verify details modal ----------------
  var verifyModal = document.getElementById('verifyModal');
  var verifyError = document.getElementById('verifyError');
  var verifyConfirm = document.getElementById('verifyConfirm');
  var verifyCancel = document.getElementById('verifyCancel');
  var currentVerifyId = null;

  var vdRequest = document.getElementById('vdRequest');
  var vdParishioner = document.getElementById('vdParishioner');
  var vdService = document.getElementById('vdService');
  var vdDate = document.getElementById('vdDate');
  var vdAmount = document.getElementById('vdAmount');
  var vdMethod = document.getElementById('vdMethod');
  var vdReferenceRow = document.getElementById('vdReferenceRow');
  var vdReference = document.getElementById('vdReference');
  var vdScreenshotWrap = document.getElementById('vdScreenshotWrap');
  var vdScreenshotImg = document.getElementById('vdScreenshotImg');
  var vdScreenshotLink = document.getElementById('vdScreenshotLink');
  var vdNoScreenshot = document.getElementById('vdNoScreenshot');

  function openVerifyModal(btn) {
    currentVerifyId = btn.dataset.verifyId;
    vdRequest.textContent = '#' + btn.dataset.verifyId;
    vdParishioner.textContent = btn.dataset.parishioner;
    vdService.textContent = btn.dataset.service;
    vdDate.textContent = btn.dataset.date;
    vdAmount.textContent = '₱' + btn.dataset.amount;

    var method = btn.dataset.method;
    var reference = btn.dataset.reference;
    var screenshot = btn.dataset.screenshot;

    vdMethod.textContent = method ? method.toUpperCase() : 'Not specified (older request)';

    if (reference) {
      vdReferenceRow.style.display = 'flex';
      vdReference.textContent = reference;
    } else {
      vdReferenceRow.style.display = 'none';
    }

    if (screenshot) {
      vdScreenshotWrap.style.display = 'block';
      vdNoScreenshot.style.display = 'none';
      vdScreenshotImg.src = screenshot;
      vdScreenshotLink.href = screenshot;
    } else {
      vdScreenshotWrap.style.display = 'none';
      vdNoScreenshot.style.display = 'block';
    }

    verifyError.classList.remove('show');
    verifyConfirm.disabled = false;
    verifyConfirm.textContent = 'Confirm Verification';
    verifyModal.classList.add('open');
  }

  function closeVerifyModal() {
    verifyModal.classList.remove('open');
    currentVerifyId = null;
  }

  document.querySelectorAll('[data-verify-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openVerifyModal(btn);
    });
  });

  verifyCancel.addEventListener('click', closeVerifyModal);
  verifyModal.addEventListener('click', function (e) {
    if (e.target === verifyModal) closeVerifyModal();
  });

  verifyConfirm.addEventListener('click', function () {
    if (!currentVerifyId) return;
    verifyError.classList.remove('show');
    verifyConfirm.disabled = true;
    verifyConfirm.textContent = 'Verifying…';

    fetch('ajax/verify-payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id: currentVerifyId }),
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          verifyError.textContent = res.data.error || 'Something went wrong.';
          verifyError.classList.add('show');
          verifyConfirm.disabled = false;
          verifyConfirm.textContent = 'Confirm Verification';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        verifyError.textContent = 'Network error. Please try again.';
        verifyError.classList.add('show');
        verifyConfirm.disabled = false;
        verifyConfirm.textContent = 'Confirm Verification';
      });
  });

  // ---------------- Reject reason modal ----------------
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