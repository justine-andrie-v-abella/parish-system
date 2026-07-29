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
      if (!confirm('Approve and confirm this appointment request?')) return;
      btn.disabled = true;
      btn.textContent = 'Approving…';
      postForm('ajax/approve-request.php', { id: btn.dataset.approveId })
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
    postForm('ajax/reject-request.php', { id: currentRejectId, reason: reason })
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
    postForm('ajax/request-documents.php', { id: currentDocsId, message: msg })
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

  // ---------------- Reschedule ----------------
  var rescheduleModal = document.getElementById('rescheduleModal');
  var rescheduleDate = document.getElementById('rescheduleDate');
  var rescheduleSlots = document.getElementById('rescheduleSlots');
  var rescheduleError = document.getElementById('rescheduleError');
  var rescheduleConfirm = document.getElementById('rescheduleConfirm');
  var currentRescheduleId = null;
  var selectedTime = null;

  document.querySelectorAll('[data-reschedule-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentRescheduleId = btn.dataset.rescheduleId;
      selectedTime = null;
      rescheduleDate.value = btn.dataset.currentDate || '';
      rescheduleError.classList.remove('show');
      rescheduleSlots.innerHTML = '<div class="qmodal-slot-empty">Choose a date to see open times.</div>';
      rescheduleModal.classList.add('open');
      if (rescheduleDate.value) rescheduleDate.dispatchEvent(new Event('change'));
    });
  });
  document.getElementById('rescheduleCancel').addEventListener('click', function () { rescheduleModal.classList.remove('open'); });
  rescheduleModal.addEventListener('click', function (e) { if (e.target === rescheduleModal) rescheduleModal.classList.remove('open'); });

  rescheduleDate.addEventListener('change', function () {
    selectedTime = null;
    rescheduleError.classList.remove('show');
    var date = rescheduleDate.value;
    if (!date) return;

    rescheduleSlots.innerHTML = '<div class="qmodal-slot-empty">Loading…</div>';

    fetch('ajax/get-slots-admin.php?date=' + encodeURIComponent(date) + '&exclude_id=' + encodeURIComponent(currentRescheduleId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          rescheduleSlots.innerHTML = '<div class="qmodal-slot-empty">' + data.error + '</div>';
          return;
        }
        if (data.closed || !data.slots.length) {
          rescheduleSlots.innerHTML = '<div class="qmodal-slot-empty">Office closed that day.</div>';
          return;
        }
        rescheduleSlots.innerHTML = '';
        data.slots.forEach(function (slot) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'qmodal-slot-btn';
          b.textContent = slot.label;
          b.disabled = !slot.available;
          if (slot.available) {
            b.addEventListener('click', function () {
              rescheduleSlots.querySelectorAll('.qmodal-slot-btn').forEach(function (s) { s.classList.remove('selected'); });
              b.classList.add('selected');
              selectedTime = slot.time;
            });
          }
          rescheduleSlots.appendChild(b);
        });
      })
      .catch(function () {
        rescheduleSlots.innerHTML = '<div class="qmodal-slot-empty">Couldn\'t load times.</div>';
      });
  });

  rescheduleConfirm.addEventListener('click', function () {
    if (!rescheduleDate.value) {
      rescheduleError.textContent = 'Please choose a date.';
      rescheduleError.classList.add('show');
      return;
    }
    if (!selectedTime) {
      rescheduleError.textContent = 'Please choose an available time slot.';
      rescheduleError.classList.add('show');
      return;
    }
    rescheduleError.classList.remove('show');
    rescheduleConfirm.disabled = true;
    rescheduleConfirm.textContent = 'Saving…';
    postForm('ajax/reschedule-request.php', {
      id: currentRescheduleId,
      appointment_date: rescheduleDate.value,
      appointment_time: selectedTime,
    })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          rescheduleError.textContent = res.data.error || 'Something went wrong.';
          rescheduleError.classList.add('show');
          rescheduleConfirm.disabled = false;
          rescheduleConfirm.textContent = 'Confirm New Date';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        rescheduleError.textContent = 'Network error. Please try again.';
        rescheduleError.classList.add('show');
        rescheduleConfirm.disabled = false;
        rescheduleConfirm.textContent = 'Confirm New Date';
      });
  });
});