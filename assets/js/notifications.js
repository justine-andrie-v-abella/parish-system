document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('rescheduleActionModal');
  if (!modal) return;

  var titleEl = document.getElementById('raService');
  var currentEl = document.getElementById('raCurrent');
  var proposedEl = document.getElementById('raProposed');
  var waitingEl = document.getElementById('raWaiting');
  var actionsEl = document.getElementById('raActions');
  var counterPanel = document.getElementById('raCounterPanel');
  var counterDate = document.getElementById('raCounterDate');
  var counterSlots = document.getElementById('raCounterSlots');
  var errorEl = document.getElementById('raError');
  var acceptBtn = document.getElementById('raAccept');
  var counterBtn = document.getElementById('raCounterToggle');
  var counterSubmit = document.getElementById('raCounterSubmit');
  var closeBtn = document.getElementById('raClose');

  var currentAppointmentId = null;
  var selectedTime = null;

  function fmtDate(d) {
    if (!d) return '';
    var dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }
  function fmtTime(t) {
    if (!t) return '';
    var parts = t.split(':');
    var h = parseInt(parts[0], 10);
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + parts[1] + ' ' + ampm;
  }

  function resetModal() {
    errorEl.classList.remove('show');
    counterPanel.classList.remove('show');
    counterSlots.innerHTML = '<div class="qmodal-slot-empty">Choose a date to see open times.</div>';
    counterDate.value = '';
    selectedTime = null;
    acceptBtn.disabled = false;
    acceptBtn.textContent = 'Confirm This Date';
    counterSubmit.disabled = false;
    counterSubmit.textContent = 'Send Suggested Date';
  }

  function slotsUrl(date) {
    return PMS_ROLE === 'parishioner'
      ? 'ajax/get-slots.php?date=' + encodeURIComponent(date)
      : 'ajax/get-slots-admin.php?date=' + encodeURIComponent(date) + '&exclude_id=' + encodeURIComponent(currentAppointmentId);
  }

  function openReschedule(appointmentId) {
    currentAppointmentId = appointmentId;
    resetModal();
    modal.classList.add('open');
    titleEl.textContent = 'Loading…';
    currentEl.textContent = '';
    proposedEl.textContent = '';
    waitingEl.classList.remove('show');
    actionsEl.style.display = 'none';

    fetch('ajax/get-reschedule-info.php?appointment_id=' + encodeURIComponent(appointmentId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          titleEl.textContent = 'Unable to load';
          currentEl.textContent = data.error;
          return;
        }
        titleEl.textContent = data.service_name + ' — Reschedule';
        currentEl.textContent = 'Current: ' + fmtDate(data.current_date) + (data.current_time ? ' at ' + fmtTime(data.current_time) : '');

        if (data.reschedule_status !== 'pending') {
          waitingEl.textContent = 'No pending reschedule on this request.';
          waitingEl.classList.add('show');
          return;
        }

        proposedEl.textContent = 'Proposed: ' + fmtDate(data.proposed_date) + ' at ' + fmtTime(data.proposed_time);

        if (data.can_act) {
          actionsEl.style.display = 'block';
        } else {
          waitingEl.textContent = 'Waiting for a response from the other party.';
          waitingEl.classList.add('show');
        }
      })
      .catch(function () {
        titleEl.textContent = 'Error';
        currentEl.textContent = 'Could not load reschedule details.';
      });
  }

  document.body.addEventListener('click', function (e) {
    var item = e.target.closest('[data-notif-id]');
    if (!item) return;

    var notifId = item.dataset.notifId;
    var apptId = item.dataset.appointmentId;

    if (item.classList.contains('unread')) {
      item.classList.remove('unread');
      fetch('ajax/mark-notification-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: notifId }),
      }).catch(function () {});
    }

    if (apptId) openReschedule(apptId);
  });

  closeBtn.addEventListener('click', function () { modal.classList.remove('open'); });
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('open'); });

  acceptBtn.addEventListener('click', function () {
    acceptBtn.disabled = true;
    acceptBtn.textContent = 'Confirming…';
    fetch('ajax/respond-reschedule.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id: currentAppointmentId, action: 'accept' }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          errorEl.textContent = res.data.error || 'Something went wrong.';
          errorEl.classList.add('show');
          acceptBtn.disabled = false;
          acceptBtn.textContent = 'Confirm This Date';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.add('show');
        acceptBtn.disabled = false;
        acceptBtn.textContent = 'Confirm This Date';
      });
  });

  counterBtn.addEventListener('click', function () {
    counterPanel.classList.toggle('show');
  });

  counterDate.addEventListener('change', function () {
    selectedTime = null;
    var date = counterDate.value;
    if (!date) return;
    counterSlots.innerHTML = '<div class="qmodal-slot-empty">Loading…</div>';
    fetch(slotsUrl(date))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          counterSlots.innerHTML = '<div class="qmodal-slot-empty">' + data.error + '</div>';
          return;
        }
        if (data.closed || !data.slots.length) {
          counterSlots.innerHTML = '<div class="qmodal-slot-empty">Office closed that day.</div>';
          return;
        }
        counterSlots.innerHTML = '';
        data.slots.forEach(function (slot) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'qmodal-slot-btn';
          b.textContent = slot.label;
          b.disabled = !slot.available;
          if (slot.available) {
            b.addEventListener('click', function () {
              counterSlots.querySelectorAll('.qmodal-slot-btn').forEach(function (s) { s.classList.remove('selected'); });
              b.classList.add('selected');
              selectedTime = slot.time;
            });
          }
          counterSlots.appendChild(b);
        });
      })
      .catch(function () {
        counterSlots.innerHTML = '<div class="qmodal-slot-empty">Couldn\'t load times.</div>';
      });
  });

  counterSubmit.addEventListener('click', function () {
    if (!counterDate.value || !selectedTime) {
      errorEl.textContent = 'Please choose a date and time.';
      errorEl.classList.add('show');
      return;
    }
    errorEl.classList.remove('show');
    counterSubmit.disabled = true;
    counterSubmit.textContent = 'Sending…';
    fetch('ajax/respond-reschedule.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        id: currentAppointmentId,
        action: 'counter',
        appointment_date: counterDate.value,
        appointment_time: selectedTime,
      }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          errorEl.textContent = res.data.error || 'Something went wrong.';
          errorEl.classList.add('show');
          counterSubmit.disabled = false;
          counterSubmit.textContent = 'Send Suggested Date';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.add('show');
        counterSubmit.disabled = false;
        counterSubmit.textContent = 'Send Suggested Date';
      });
  });
});