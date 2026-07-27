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
  var dateInput = document.getElementById('apptDate');
  var slotGrid = document.getElementById('slotGrid');
  var timeInput = document.getElementById('apptTimeInput');
  var notesInput = document.getElementById('apptNotes');
  var form = document.getElementById('bookingForm');
  var formError = document.getElementById('formError');
  var formSuccess = document.getElementById('formSuccess');
  var submitBtn = document.getElementById('modalSubmit');

  function openModal(serviceKey, serviceName) {
    serviceKeyInput.value = serviceKey;
    modalServiceName.textContent = 'Request ' + serviceName;
    dateInput.value = '';
    timeInput.value = '';
    notesInput.value = '';
    slotGrid.innerHTML = '<p class="slot-empty">Choose a date to see open times.</p>';
    formError.classList.remove('show');
    formSuccess.classList.remove('show');
    submitBtn.disabled = false;
    modal.classList.add('open');
  }

  function closeModal() {
    modal.classList.remove('open');
  }

  document.querySelectorAll('[data-book-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.dataset.serviceKey, btn.dataset.serviceName);
    });
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  dateInput.addEventListener('change', function () {
    timeInput.value = '';
    formError.classList.remove('show');
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

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    formError.classList.remove('show');
    formSuccess.classList.remove('show');

    if (!dateInput.value) {
      formError.textContent = 'Please choose a date.';
      formError.classList.add('show');
      return;
    }
    if (!timeInput.value) {
      formError.textContent = 'Please choose an available time slot.';
      formError.classList.add('show');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    var body = new URLSearchParams({
      service_key: serviceKeyInput.value,
      appointment_date: dateInput.value,
      appointment_time: timeInput.value,
      notes: notesInput.value,
    });

    fetch('ajax/book-appointment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          formError.textContent = res.data.error || 'Something went wrong. Please try again.';
          formError.classList.add('show');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Request';
          // Slot was likely taken — refresh the grid
          if (dateInput.value) dateInput.dispatchEvent(new Event('change'));
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
        formError.textContent = 'Network error. Please try again.';
        formError.classList.add('show');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
      });
  });
});