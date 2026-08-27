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

  // ---------------- Proceed to Payment ----------------
  var payModal = document.getElementById('payModal');
  if (payModal) {
    var payFeeDisplay = document.getElementById('payFeeDisplay');
    var payPmCash = document.getElementById('payPmCash');
    var payPmGcash = document.getElementById('payPmGcash');
    var payCashPanel = document.getElementById('payCashPanel');
    var payGcashPanel = document.getElementById('payGcashPanel');
    var payGcashRedirectStatus = document.getElementById('payGcashRedirectStatus');
    var payError = document.getElementById('payError');
    var payConfirm = document.getElementById('payConfirm');
    var currentPayId = null;

    function togglePayPanels() {
      var isGcash = payPmGcash.checked;
      payGcashPanel.classList.toggle('show', isGcash);
      payCashPanel.classList.toggle('show', !isGcash);
    }
    payPmCash.addEventListener('change', togglePayPanels);
    payPmGcash.addEventListener('change', togglePayPanels);

    document.querySelectorAll('[data-pay-btn]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentPayId = btn.dataset.id;
        payFeeDisplay.textContent = '₱' + Number(btn.dataset.fee || 0).toLocaleString();
        payPmCash.checked = true;
        togglePayPanels();
        payGcashRedirectStatus.classList.remove('show');
        payError.classList.remove('show');
        payConfirm.disabled = false;
        payConfirm.textContent = 'Submit Payment';
        payModal.classList.add('open');
      });
    });

    document.getElementById('payCancel').addEventListener('click', function () { payModal.classList.remove('open'); });
    payModal.addEventListener('click', function (e) { if (e.target === payModal) payModal.classList.remove('open'); });

    payConfirm.addEventListener('click', function () {
      payError.classList.remove('show');
      payConfirm.disabled = true;
      payConfirm.textContent = 'Submitting…';

      var method = payPmGcash.checked ? 'gcash' : 'cash';
      if (method === 'gcash') {
        payGcashRedirectStatus.classList.add('show');
      }

      fetch('ajax/submit-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: currentPayId, payment_method: method }),
      })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            payGcashRedirectStatus.classList.remove('show');
            payError.textContent = res.data.error || 'Something went wrong.';
            payError.classList.add('show');
            payConfirm.disabled = false;
            payConfirm.textContent = 'Submit Payment';
            return;
          }
          if (res.data.checkout_url) {
            setTimeout(function () { window.location.href = res.data.checkout_url; }, 400);
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          payGcashRedirectStatus.classList.remove('show');
          payError.textContent = 'Network error. Please try again.';
          payError.classList.add('show');
          payConfirm.disabled = false;
          payConfirm.textContent = 'Submit Payment';
        });
    });
  }

  // ---------------- Re-upload documents ----------------
  var reuploadModal = document.getElementById('reuploadModal');
  if (reuploadModal) {
    var reuploadFieldsContainer = document.getElementById('reuploadFieldsContainer');
    var reuploadError = document.getElementById('reuploadError');
    var reuploadConfirm = document.getElementById('reuploadConfirm');
    var currentReuploadId = null;
    var currentReuploadRequirements = [];

    function renderReuploadSlots(reqs) {
      reuploadFieldsContainer.innerHTML = '';
      reqs.forEach(function (label, i) {
        var group = document.createElement('div');
        group.className = 'form-group';

        var lbl = document.createElement('label');
        lbl.setAttribute('for', 'reuploadDoc' + i);
        lbl.textContent = label;

        var input = document.createElement('input');
        input.type = 'file';
        input.id = 'reuploadDoc' + i;
        input.accept = '.jpg,.jpeg,.png,.pdf';
        input.required = true;

        group.appendChild(lbl);
        group.appendChild(input);
        reuploadFieldsContainer.appendChild(group);
      });
    }

    document.querySelectorAll('[data-reupload-btn]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentReuploadId = btn.dataset.id;
        try { currentReuploadRequirements = JSON.parse(btn.dataset.requirements || '[]'); } catch (e) { currentReuploadRequirements = []; }
        renderReuploadSlots(currentReuploadRequirements);
        reuploadError.classList.remove('show');
        reuploadConfirm.disabled = false;
        reuploadConfirm.textContent = 'Submit';
        reuploadModal.classList.add('open');
      });
    });

    document.getElementById('reuploadCancel').addEventListener('click', function () { reuploadModal.classList.remove('open'); });
    reuploadModal.addEventListener('click', function (e) { if (e.target === reuploadModal) reuploadModal.classList.remove('open'); });

    reuploadConfirm.addEventListener('click', function () {
      for (var i = 0; i < currentReuploadRequirements.length; i++) {
        var input = document.getElementById('reuploadDoc' + i);
        if (!input || !input.files || !input.files.length) {
          reuploadError.textContent = 'Please upload a document for "' + currentReuploadRequirements[i] + '".';
          reuploadError.classList.add('show');
          return;
        }
      }
      reuploadError.classList.remove('show');
      reuploadConfirm.disabled = true;
      reuploadConfirm.textContent = 'Submitting…';

      var formData = new FormData();
      formData.append('id', currentReuploadId);
      currentReuploadRequirements.forEach(function (label, i) {
        var input = document.getElementById('reuploadDoc' + i);
        if (input && input.files && input.files[0]) {
          formData.append('req_doc_' + i, input.files[0]);
        }
      });

      fetch('ajax/resubmit-documents.php', {
        method: 'POST',
        body: formData,
      })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            reuploadError.textContent = res.data.error || 'Something went wrong.';
            reuploadError.classList.add('show');
            reuploadConfirm.disabled = false;
            reuploadConfirm.textContent = 'Submit';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          reuploadError.textContent = 'Network error. Please try again.';
          reuploadError.classList.add('show');
          reuploadConfirm.disabled = false;
          reuploadConfirm.textContent = 'Submit';
        });
    });
  }
});
