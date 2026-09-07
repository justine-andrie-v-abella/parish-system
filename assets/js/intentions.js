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

  // Step 1 elements
  var stepDetails = document.getElementById('stepDetails');
  var stepPayment = document.getElementById('stepPayment');
  var stepDot1 = document.getElementById('stepDot1');
  var stepDot2 = document.getElementById('stepDot2');
  var dateInput = document.getElementById('apptDate');
  var apptDateGroup = document.getElementById('apptDateGroup');
  var dateOfDeathGroup = document.getElementById('dateOfDeathGroup');
  var dateOfDeathInput = document.getElementById('dateOfDeath');
  var slotGrid = document.getElementById('slotGrid');
  var timeInput = document.getElementById('apptTimeInput');
  var notesInput = document.getElementById('apptNotes');
  var formErrorStep1 = document.getElementById('formErrorStep1');
  var goToPaymentBtn = document.getElementById('goToPayment');

  // Booking calendar (per-service day picker) elements
  var apptDateLoading = document.getElementById('apptDateLoading');
  var bookCalTrigger = document.getElementById('bookCalTrigger');
  var bookCalTriggerText = document.getElementById('bookCalTriggerText');
  var bookCal = document.getElementById('bookCal');
  var bookCalTitle = document.getElementById('bookCalTitle');
  var bookCalGrid = document.getElementById('bookCalGrid');
  var bookCalPrev = document.getElementById('bookCalPrev');
  var bookCalNext = document.getElementById('bookCalNext');
  var calMonth, calYear; // 1-12, full year — the month currently shown in the booking calendar
  var calDays = {}; // date string -> 'available' | 'full', for the month currently shown
  var datePickerRequestId = 0; // guards against a slow, stale fetch from a previously-opened service overwriting the current one

  // Moved to <body> once so position:fixed coordinates aren't clipped by
  // .modal-box's overflow-y:auto (same reasoning as .actions-menu elsewhere).
  document.body.appendChild(bookCal);

  function positionBookCal() {
    var margin = 12;
    var rect = bookCalTrigger.getBoundingClientRect();
    var panelWidth = bookCal.offsetWidth || 300;
    var panelHeight = bookCal.offsetHeight || 0;

    var left = Math.max(margin, Math.min(rect.left, window.innerWidth - panelWidth - margin));
    var top = rect.bottom + 8;
    if (top + panelHeight > window.innerHeight - margin && rect.top - panelHeight - 8 > margin) {
      top = rect.top - panelHeight - 8; // not enough room below — open upward
    }

    bookCal.style.left = left + 'px';
    bookCal.style.top = top + 'px';
  }

  function closeBookCal() {
    bookCal.classList.remove('open');
    bookCalTrigger.classList.remove('open');
    bookCalTrigger.setAttribute('aria-expanded', 'false');
  }

  function openBookCal() {
    bookCal.classList.add('open');
    bookCalTrigger.classList.add('open');
    bookCalTrigger.setAttribute('aria-expanded', 'true');
    positionBookCal();
  }

  bookCalTrigger.addEventListener('click', function (e) {
    e.stopPropagation();
    if (bookCal.classList.contains('open')) {
      closeBookCal();
    } else {
      openBookCal();
    }
  });
  bookCal.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', closeBookCal);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBookCal(); });
  window.addEventListener('resize', closeBookCal);
  window.addEventListener('scroll', closeBookCal, true);

  // Step 2 (documents) elements
  var backToDetailsBtn = document.getElementById('backToDetails');
  var docsFieldsContainer = document.getElementById('docsFieldsContainer');
  var docsNoneNote = document.getElementById('docsNoneNote');
  var formErrorStep2 = document.getElementById('formErrorStep2');
  var formSuccess = document.getElementById('formSuccess');
  var form = document.getElementById('bookingForm');
  var submitBtn = document.getElementById('modalSubmit');

  var currentRequirements = [];

  // Services whose schedule is 'conditional' (date derived from another
  // event, e.g. Burial Mass = date of death + N days). Keep this in sync
  // with whichever service_key(s) you give a 'conditional' rule in Catalog.
  var CONDITIONAL_SERVICE_KEYS = ['burial'];

  // True when the currently-loaded slot response doesn't need a specific
  // time picked (by_arrangement / always_available) — set only from the
  // actual server response type, never guessed from rendered text.
  var noSlotRequired = false;

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

  // One file input per requirement line, named req_doc_0, req_doc_1, ... in
  // the same order — the server re-derives this same requirement list from
  // the service itself rather than trusting anything the client sends about
  // what the requirements are.
  function renderDocumentSlots(reqs) {
    docsFieldsContainer.innerHTML = '';
    reqs.forEach(function (label, i) {
      var group = document.createElement('div');
      group.className = 'form-group';

      var lbl = document.createElement('label');
      lbl.setAttribute('for', 'reqDoc' + i);
      lbl.textContent = label;

      var input = document.createElement('input');
      input.type = 'file';
      input.id = 'reqDoc' + i;
      input.name = 'req_doc_' + i;
      input.accept = '.jpg,.jpeg,.png,.pdf';
      input.required = true;

      var hint = document.createElement('p');
      hint.className = 'slot-hint';
      hint.textContent = 'JPG, PNG, or PDF, up to 5MB.';

      group.appendChild(lbl);
      group.appendChild(input);
      group.appendChild(hint);
      docsFieldsContainer.appendChild(group);
    });
  }

  var MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function formatDateLabel(dateStr) {
    var parts = dateStr.split('-');
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  }

  function selectCalendarDate(dateStr, cell) {
    bookCalGrid.querySelectorAll('.book-cal-cell.bc-selected').forEach(function (c) { c.classList.remove('bc-selected'); });
    if (cell) cell.classList.add('bc-selected');
    dateInput.value = dateStr;
    bookCalTriggerText.textContent = formatDateLabel(dateStr);
    bookCalTriggerText.classList.remove('bct-placeholder');
    closeBookCal();
    fetchSlots();
  }

  function renderCalendarGrid() {
    var firstOfMonth = new Date(calYear, calMonth - 1, 1);
    var daysInMonth = new Date(calYear, calMonth, 0).getDate();
    var startWeekday = firstOfMonth.getDay();
    var today = todayStr();
    var selectedDate = dateInput.value;

    bookCalTitle.textContent = MONTH_NAMES[calMonth - 1] + ' ' + calYear;

    var html = '';
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function (d) { html += '<div class="book-cal-dow">' + d + '</div>'; });
    for (var i = 0; i < startWeekday; i++) html += '<div class="book-cal-cell empty"></div>';

    var anyOpen = false;
    for (var d = 1; d <= daysInMonth; d++) {
      var dateStr = calYear + '-' + String(calMonth).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var status = calDays[dateStr]; // 'available' | 'full' | undefined (not offered / past)
      var cls = 'book-cal-cell';
      if (status === 'available') { cls += ' bc-avail'; anyOpen = true; }
      else if (status === 'full') cls += ' bc-full';
      if (dateStr === today) cls += ' bc-today';
      if (dateStr === selectedDate && status === 'available') cls += ' bc-selected';
      html += '<div class="' + cls + '" data-date="' + dateStr + '" data-status="' + (status || '') + '">' + d + '</div>';
    }
    if (!anyOpen) {
      html += '<div class="book-cal-empty-note">No open dates this month — try another month.</div>';
    }

    bookCalGrid.innerHTML = html;

    bookCalGrid.querySelectorAll('.book-cal-cell.bc-avail').forEach(function (cell) {
      cell.addEventListener('click', function () {
        selectCalendarDate(cell.dataset.date, cell);
      });
    });

    var now = new Date();
    bookCalPrev.disabled = (calYear < now.getFullYear()) || (calYear === now.getFullYear() && calMonth <= now.getMonth() + 1);
  }

  function loadCalendarMonth() {
    bookCalGrid.innerHTML = '<div class="book-cal-empty-note">Loading&hellip;</div>';
    var url = 'ajax/get-service-calendar.php?service_key=' + encodeURIComponent(serviceKeyInput.value) + '&month=' + calMonth + '&year=' + calYear;
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        calDays = data.days || {};
        renderCalendarGrid();
        positionBookCal();
      })
      .catch(function () {
        bookCalGrid.innerHTML = '<div class="book-cal-empty-note">Couldn\'t load the calendar. Please try again.</div>';
      });
  }

  bookCalPrev.addEventListener('click', function () {
    calMonth--;
    if (calMonth < 1) { calMonth = 12; calYear--; }
    loadCalendarMonth();
  });
  bookCalNext.addEventListener('click', function () {
    calMonth++;
    if (calMonth > 12) { calMonth = 1; calYear++; }
    loadCalendarMonth();
  });

  // Decides, per service, whether to show the visual day-picker calendar
  // (weekly/nth_weekday schedules) or fall back to the plain native date
  // input (by_arrangement/always_available/no schedule configured yet).
  function setupDatePicker(serviceKey) {
    var now = new Date();
    calMonth = now.getMonth() + 1;
    calYear = now.getFullYear();
    calDays = {};
    closeBookCal();
    bookCalTriggerText.textContent = 'Choose a date';
    bookCalTriggerText.classList.add('bct-placeholder');

    // Show neither control until we know which one applies — avoids a
    // flash of the plain date field for a service that turns out to have
    // a real calendar (the schedule lookup is a network round trip).
    bookCalTrigger.style.display = 'none';
    dateInput.style.display = 'none';
    apptDateLoading.style.display = 'block';

    var requestId = ++datePickerRequestId;

    fetch('ajax/get-service-calendar.php?service_key=' + encodeURIComponent(serviceKey) + '&month=' + calMonth + '&year=' + calYear)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (requestId !== datePickerRequestId) return; // a newer service was opened meanwhile — ignore this stale response
        apptDateLoading.style.display = 'none';
        if (data.mode === 'calendar') {
          calDays = data.days || {};
          bookCalTrigger.style.display = 'flex';
          renderCalendarGrid();
        } else {
          dateInput.style.display = '';
        }
      })
      .catch(function () {
        if (requestId !== datePickerRequestId) return;
        apptDateLoading.style.display = 'none';
        dateInput.style.display = ''; // fall back to the plain date field on error
      });
  }

  function openModal(serviceKey, serviceName, requirements) {
    serviceKeyInput.value = serviceKey;
    modalServiceName.textContent = 'Request ' + serviceName;
    currentRequirements = requirements || [];

    dateInput.value = '';
    timeInput.value = '';
    notesInput.value = '';
    noSlotRequired = false;
    slotGrid.innerHTML = '<p class="slot-empty">Choose a date to see open times.</p>';

    var hasRequirements = currentRequirements.length > 0;
    docsNoneNote.style.display = hasRequirements ? 'none' : 'block';
    renderDocumentSlots(currentRequirements);

    var isConditionalService = CONDITIONAL_SERVICE_KEYS.indexOf(serviceKey) !== -1;

    if (dateOfDeathGroup) {
      dateOfDeathGroup.style.display = isConditionalService ? 'block' : 'none';
      if (dateOfDeathInput) dateOfDeathInput.value = '';
    }

    // For conditional services the appointment date is computed from the
    // trigger date (e.g. date of death), not chosen directly — hide the
    // Preferred Date field entirely so it can't be touched and accidentally
    // reset the selected slot via its own 'change' listener.
    if (apptDateGroup) {
      apptDateGroup.style.display = isConditionalService ? 'none' : 'block';
    }
    dateInput.required = !isConditionalService;

    if (!isConditionalService) {
      setupDatePicker(serviceKey);
    } else {
      datePickerRequestId++; // invalidate any in-flight lookup from a previously-opened service
      closeBookCal();
      bookCalTrigger.style.display = 'none';
      apptDateLoading.style.display = 'none';
      dateInput.style.display = 'none';
    }

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
    closeBookCal(); // bookCal lives in <body>, not inside the modal — must close explicitly
  }

  document.querySelectorAll('[data-book-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var requirements = [];
      try { requirements = JSON.parse(btn.dataset.requirements || '[]'); } catch (e) { /* no requirements */ }
      openModal(btn.dataset.serviceKey, btn.dataset.serviceName, requirements);
    });
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  function renderSlots(data) {
    noSlotRequired = false;
    timeInput.value = '';

    if (data.error) {
      slotGrid.innerHTML = '<p class="slot-empty">' + data.error + '</p>';
      return;
    }

    // Service has no fixed schedule — staff and requester coordinate a date.
    if (data.by_arrangement) {
      noSlotRequired = true;
      slotGrid.innerHTML = '<p class="slot-empty">' + data.note + '</p>';
      return;
    }

    // Service has no restriction at all — any date the parishioner picked is fine.
    if (data.always_available) {
      noSlotRequired = true;
      slotGrid.innerHTML = '<p class="slot-empty">' + data.message + '</p>';
      return;
    }

    // Date is derived from another event (e.g. date of death); nothing to
    // pick here besides confirming the computed slot.
    if (data.requires_trigger_date) {
      slotGrid.innerHTML = '<p class="slot-empty">' + data.message + '</p>';
      return;
    }

    if (data.closed || !data.slots || !data.slots.length) {
      slotGrid.innerHTML = '<p class="slot-empty">This service isn\'t offered on that date. Please pick a different day.</p>';
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
          // 'conditional' rules compute the real appointment date server-side;
          // reflect it back into the date field so the submitted record is correct.
          if (data.computed_date) {
            dateInput.value = data.computed_date;
          }
        });
      }
      slotGrid.appendChild(b);
    });
  }

  function fetchSlots() {
    var serviceKey = serviceKeyInput.value;
    var date = dateInput.value;
    var isConditional = CONDITIONAL_SERVICE_KEYS.indexOf(serviceKey) !== -1;

    if (isConditional) {
      if (!dateOfDeathInput || !dateOfDeathInput.value) {
        slotGrid.innerHTML = '<p class="slot-empty">Please enter the date of death first.</p>';
        return;
      }
    } else if (!date) {
      return;
    }

    slotGrid.innerHTML = '<p class="slot-empty">Loading available times&hellip;</p>';

    var url = 'ajax/get-slots.php?service_key=' + encodeURIComponent(serviceKey);
    url += '&date=' + encodeURIComponent(isConditional ? (date || dateOfDeathInput.value) : date);
    if (isConditional && dateOfDeathInput) {
      url += '&date_of_death=' + encodeURIComponent(dateOfDeathInput.value);
    }

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(renderSlots)
      .catch(function () {
        slotGrid.innerHTML = '<p class="slot-empty">Couldn\'t load times. Please try again.</p>';
      });
  }

  // ---------------- Step 1: date/time slots ----------------
  dateInput.addEventListener('change', function () {
    timeInput.value = '';
    formErrorStep1.classList.remove('show');
    fetchSlots();
  });

  if (dateOfDeathInput) {
    dateOfDeathInput.addEventListener('change', function () {
      timeInput.value = '';
      formErrorStep1.classList.remove('show');
      fetchSlots();
    });
  }

  goToPaymentBtn.addEventListener('click', function () {
    formErrorStep1.classList.remove('show');
    var isConditional = CONDITIONAL_SERVICE_KEYS.indexOf(serviceKeyInput.value) !== -1;

    if (isConditional) {
      if (!dateOfDeathInput || !dateOfDeathInput.value) {
        formErrorStep1.textContent = 'Please enter the date of death.';
        formErrorStep1.classList.add('show');
        return;
      }
    } else if (!dateInput.value) {
      formErrorStep1.textContent = 'Please choose a date.';
      formErrorStep1.classList.add('show');
      return;
    }

    if (!timeInput.value && !noSlotRequired) {
      formErrorStep1.textContent = 'Please choose an available time slot.';
      formErrorStep1.classList.add('show');
      return;
    }
    showStep(2);
  });

  backToDetailsBtn.addEventListener('click', function () {
    showStep(1);
  });

  // ---------------- Submit ----------------
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    formErrorStep2.classList.remove('show');
    formSuccess.classList.remove('show');

    for (var i = 0; i < currentRequirements.length; i++) {
      var reqInput = document.getElementById('reqDoc' + i);
      if (!reqInput || !reqInput.files || !reqInput.files.length) {
        formErrorStep2.textContent = 'Please upload a document for "' + currentRequirements[i] + '".';
        formErrorStep2.classList.add('show');
        return;
      }
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    var formData = new FormData();
    formData.append('service_key', serviceKeyInput.value);
    formData.append('appointment_date', dateInput.value);
    formData.append('appointment_time', timeInput.value);
    formData.append('notes', notesInput.value);
    if (dateOfDeathInput && dateOfDeathInput.value) {
      formData.append('date_of_death', dateOfDeathInput.value);
    }
    currentRequirements.forEach(function (label, i) {
      var reqInput = document.getElementById('reqDoc' + i);
      if (reqInput && reqInput.files && reqInput.files[0]) {
        formData.append('req_doc_' + i, reqInput.files[0]);
      }
    });

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
