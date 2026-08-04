//assets\js\intentions.js
document.addEventListener('DOMContentLoaded', function () {
  // Requirements accordion on each card
  document.querySelectorAll('[data-req-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.classList.toggle('open');
      var panel = btn.nextElementSibling;
      panel.classList.toggle('open');
    });
  });

  var modal = document.getElementById('bookingModal');
  var modalServiceName = document.getElementById('modalServiceName');
  var serviceKeyInput = document.getElementById('serviceKeyInput');
  var feeDisplay = document.getElementById('feeDisplay');

  // Step 1 elements
  var stepDetails = document.getElementById('stepDetails');
  var stepPayment = document.getElementById('stepPayment');
  var stepDot1 = document.getElementById('stepDot1');
  var stepDot2 = document.getElementById('stepDot2');
  var dateInput = document.getElementById('apptDate');
  var slotGrid = document.getElementById('slotGrid');
  var timeInput = document.getElementById('apptTimeInput');
  var notesInput = document.getElementById('apptNotes');
  var formErrorStep1 = document.getElementById('formErrorStep1');
  var goToPaymentBtn = document.getElementById('goToPayment');

  // Step 2 elements
  var backToDetailsBtn = document.getElementById('backToDetails');
  var pmCash = document.getElementById('pmCash');
  var pmGcash = document.getElementById('pmGcash');
  var cashPanel = document.getElementById('cashPanel');
  var gcashPanel = document.getElementById('gcashPanel');
  var formErrorStep2 = document.getElementById('formErrorStep2');
  var formSuccess = document.getElementById('formSuccess');
  var form = document.getElementById('bookingForm');
  var submitBtn = document.getElementById('modalSubmit');

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

  function openModal(serviceKey, serviceName, serviceFee) {
    serviceKeyInput.value = serviceKey;
    modalServiceName.textContent = 'Request ' + serviceName;
    feeDisplay.textContent = formatPeso(serviceFee || 0);

    dateInput.value = '';
    timeInput.value = '';
    notesInput.value = '';
    slotGrid.innerHTML = '<p class="slot-empty">Choose a date to see open times.</p>';

    pmCash.checked = true;
    togglePaymentPanels();

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

  document.querySelectorAll('[data-book-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.dataset.serviceKey, btn.dataset.serviceName, btn.dataset.serviceFee);
    });
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  // ---------------- Step 1: date/time slots ----------------
  dateInput.addEventListener('change', function () {
    timeInput.value = '';
    formErrorStep1.classList.remove('show');
    var date = dateInput.value;
    if (!date) return;

    slotGrid.innerHTML = '<p class="slot-empty">Loading available times&hellip;</p>';

    fetch('ajax/get-slots.php?date=' + encodeURIComponent(date))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          slotGrid.innerHTML = '<p class="slot-empty">' + data.error + '</p>';
          return;
        }
        if (data.closed || !data.slots.length) {
          slotGrid.innerHTML = '<p class="slot-empty">The office is closed that day. Please pick a weekday or Saturday.</p>';
          return;
        }

        slotGrid.innerHTML = '';
        data.slots.forEach(function (slot) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'slot-btn';
          b.textContent = slot.label;
          b.disabled = !slot.available;
          if (slot.available) {
            b.addEventListener('click', function () {
              slotGrid.querySelectorAll('.slot-btn').forEach(function (s) { s.classList.remove('selected'); });
              b.classList.add('selected');
              timeInput.value = slot.time;
            });
          }
          slotGrid.appendChild(b);
        });
      })
      .catch(function () {
        slotGrid.innerHTML = '<p class="slot-empty">Couldn\'t load times. Please try again.</p>';
      });
  });

  goToPaymentBtn.addEventListener('click', function () {
    formErrorStep1.classList.remove('show');
    if (!dateInput.value) {
      formErrorStep1.textContent = 'Please choose a date.';
      formErrorStep1.classList.add('show');
      return;
    }
    if (!timeInput.value) {
      formErrorStep1.textContent = 'Please choose an available time slot.';
      formErrorStep1.classList.add('show');
      return;
    }
    showStep(2);
  });

  backToDetailsBtn.addEventListener('click', function () {
    showStep(1);
  });

  // ---------------- Step 2: payment ----------------
  pmCash.addEventListener('change', togglePaymentPanels);
  pmGcash.addEventListener('change', togglePaymentPanels);

  // ---------------- Submit ----------------
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    formErrorStep2.classList.remove('show');
    formSuccess.classList.remove('show');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    var formData = new FormData();
    formData.append('service_key', serviceKeyInput.value);
    formData.append('appointment_date', dateInput.value);
    formData.append('appointment_time', timeInput.value);
    formData.append('notes', notesInput.value);
    formData.append('payment_method', pmGcash.checked ? 'gcash' : 'cash');

    fetch('ajax/book-appointment.php', {
      method: 'POST',
      body: formData,
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          formErrorStep2.textContent = res.data.error || 'Something went wrong. Please try again.';
          formErrorStep2.classList.add('show');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Request';
          if (/slot/i.test(res.data.error || '')) {
            showStep(1);
            if (dateInput.value) dateInput.dispatchEvent(new Event('change'));
          }
          return;
        }

        if (res.data.checkout_url) {
          formSuccess.textContent = 'Redirecting you to GCash to complete payment…';
          formSuccess.classList.add('show');
          submitBtn.textContent = 'Redirecting…';
          window.location.href = res.data.checkout_url;
          return;
        }

        formSuccess.textContent = 'Request submitted! You can track it under View Requests.';
        formSuccess.classList.add('show');
        submitBtn.textContent = 'Submitted';
        setTimeout(function () {
          window.location.href = 'requests.php';
        }, 1200);
      })
      .catch(function () {
        formErrorStep2.textContent = 'Network error. Please try again.';
        formErrorStep2.classList.add('show');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
      });
  });
});