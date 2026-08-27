document.addEventListener('DOMContentLoaded', function () {
  // Requirements accordion (same pattern as intentions.js)
  document.querySelectorAll('[data-req-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.classList.toggle('open');
      var panel = btn.nextElementSibling;
      panel.classList.toggle('open');
    });
  });

  var modal = document.getElementById('certModal');
  if (!modal) return;

  var modalServiceName = document.getElementById('certModalServiceName');
  var serviceKeyInput = document.getElementById('certServiceKeyInput');
  var feeDisplay = document.getElementById('certFeeDisplay');

  var stepDetails = document.getElementById('certStepDetails');
  var stepPayment = document.getElementById('certStepPayment');
  var stepDot1 = document.getElementById('certStepDot1');
  var stepDot2 = document.getElementById('certStepDot2');

  var requestorInput = document.getElementById('certRequestorName');
  var dynamicFieldsContainer = document.getElementById('certDynamicFields');
  var notesInput = document.getElementById('certNotes');
  var formErrorStep1 = document.getElementById('certFormErrorStep1');
  var goToPaymentBtn = document.getElementById('certGoToPayment');

  var backToDetailsBtn = document.getElementById('certBackToDetails');
  var pmCash = document.getElementById('certPmCash');
  var pmGcash = document.getElementById('certPmGcash');
  var cashPanel = document.getElementById('certCashPanel');
  var gcashPanel = document.getElementById('certGcashPanel');
  var gcashRedirectStatus = document.getElementById('certGcashRedirectStatus');
  var gcashRedirectText = document.getElementById('certGcashRedirectText');
  var formErrorStep2 = document.getElementById('certFormErrorStep2');
  var formSuccess = document.getElementById('certFormSuccess');
  var form = document.getElementById('certForm');
  var submitBtn = document.getElementById('certModalSubmit');

  var currentFields = [];

  function formatPeso(n) {
    return '₱' + Number(n).toLocaleString();
  }

  function showStep(stepNum) {
    if (stepNum === 1) {
      stepDetails.classList.add('active');
      stepPayment.classList.remove('active');
      stepDot1.classList.add('active');
      stepDot1.classList.remove('done');
      stepDot2.classList.remove('active');
    } else {
      stepDetails.classList.remove('active');
      stepPayment.classList.add('active');
      stepDot1.classList.remove('active');
      stepDot1.classList.add('done');
      stepDot2.classList.add('active');
    }
  }

  function togglePaymentPanels() {
    var isGcash = pmGcash.checked;
    gcashPanel.classList.toggle('show', isGcash);
    cashPanel.classList.toggle('show', !isGcash);
  }

  // Renders one text input per staff-defined field label (Catalog →
  // Certificate Form Fields), named field_0, field_1, ... in order — the
  // server re-derives the same labels from service_form_fields rather than
  // trusting anything the client sends about what the fields are.
  function renderDynamicFields(fields) {
    dynamicFieldsContainer.innerHTML = '';
    fields.forEach(function (label, i) {
      var group = document.createElement('div');
      group.className = 'form-group';

      var lbl = document.createElement('label');
      lbl.setAttribute('for', 'certField' + i);
      lbl.textContent = label;

      var input = document.createElement('input');
      input.type = 'text';
      input.id = 'certField' + i;
      input.name = 'field_' + i;
      input.maxLength = 150;
      input.required = true;

      group.appendChild(lbl);
      group.appendChild(input);
      dynamicFieldsContainer.appendChild(group);
    });
  }

  function openModal(serviceKey, serviceName, serviceFee, fields) {
    serviceKeyInput.value = serviceKey;
    modalServiceName.textContent = 'Request ' + serviceName;
    feeDisplay.textContent = formatPeso(serviceFee || 0);
    currentFields = fields || [];

    requestorInput.value = '';
    notesInput.value = '';
    renderDynamicFields(currentFields);

    pmCash.checked = true;
    togglePaymentPanels();
    gcashRedirectStatus.classList.remove('show');

    formErrorStep1.classList.remove('show');
    formErrorStep2.classList.remove('show');
    formSuccess.classList.remove('show');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Request';

    showStep(1);
    modal.classList.add('open');
  }

  function closeModal() {
    modal.classList.remove('open');
  }

  document.querySelectorAll('[data-cert-book-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var fields = [];
      try { fields = JSON.parse(btn.dataset.fields || '[]'); } catch (e) { /* no fields defined yet */ }
      openModal(btn.dataset.serviceKey, btn.dataset.serviceName, btn.dataset.serviceFee, fields);
    });
  });

  document.getElementById('certModalClose').addEventListener('click', closeModal);
  document.getElementById('certModalCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  goToPaymentBtn.addEventListener('click', function () {
    formErrorStep1.classList.remove('show');

    if (!requestorInput.value.trim()) {
      formErrorStep1.textContent = "Please enter the requestor's full name.";
      formErrorStep1.classList.add('show');
      return;
    }
    for (var i = 0; i < currentFields.length; i++) {
      var input = document.getElementById('certField' + i);
      if (!input || !input.value.trim()) {
        formErrorStep1.textContent = 'Please fill in "' + currentFields[i] + '".';
        formErrorStep1.classList.add('show');
        return;
      }
    }
    showStep(2);
  });

  backToDetailsBtn.addEventListener('click', function () {
    showStep(1);
  });

  pmCash.addEventListener('change', togglePaymentPanels);
  pmGcash.addEventListener('change', togglePaymentPanels);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    formErrorStep2.classList.remove('show');
    formSuccess.classList.remove('show');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    if (pmGcash.checked) {
      gcashRedirectStatus.classList.add('show');
      gcashRedirectText.textContent = 'Connecting to GCash…';
    }

    var formData = new FormData();
    formData.append('service_key', serviceKeyInput.value);
    formData.append('requestor_name', requestorInput.value.trim());
    formData.append('notes', notesInput.value);
    formData.append('payment_method', pmGcash.checked ? 'gcash' : 'cash');
    currentFields.forEach(function (label, i) {
      var input = document.getElementById('certField' + i);
      formData.append('field_' + i, input ? input.value.trim() : '');
    });

    fetch('ajax/request-certificate.php', {
      method: 'POST',
      body: formData,
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          gcashRedirectStatus.classList.remove('show');
          formErrorStep2.textContent = res.data.error || 'Something went wrong. Please try again.';
          formErrorStep2.classList.add('show');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Request';
          return;
        }

        if (res.data.checkout_url) {
          gcashRedirectText.textContent = 'Redirecting you to GCash now…';
          submitBtn.textContent = 'Redirecting…';
          setTimeout(function () {
            window.location.href = res.data.checkout_url;
          }, 600);
          return;
        }

        formSuccess.textContent = 'Request submitted! Track it below under My Certificate Requests.';
        formSuccess.classList.add('show');
        submitBtn.textContent = 'Submitted';
        setTimeout(function () {
          window.location.reload();
        }, 1200);
      })
      .catch(function () {
        gcashRedirectStatus.classList.remove('show');
        formErrorStep2.textContent = 'Network error. Please try again.';
        formErrorStep2.classList.add('show');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
      });
  });

  // ---------------- Cancel ----------------
  document.querySelectorAll('[data-cert-cancel-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Cancel this request? This can\'t be undone.')) return;

      var id = btn.dataset.id;
      btn.disabled = true;
      btn.textContent = 'Cancelling…';

      fetch('ajax/cancel-certificate.php', {
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
