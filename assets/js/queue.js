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

  // ---------------- Mark Completed ----------------
  document.querySelectorAll('[data-complete-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Mark this appointment as completed? This confirms the service was delivered.')) return;
      btn.disabled = true;
      btn.textContent = 'Saving…';
      postForm('ajax/mark-completed.php', { id: btn.dataset.completeId })
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

  // ---------------- Mark No-show ----------------
  document.querySelectorAll('[data-noshow-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Mark this appointment as a no-show? The parishioner will be notified.')) return;
      btn.disabled = true;
      btn.textContent = 'Saving…';
      postForm('ajax/mark-noshow.php', { id: btn.dataset.noshowId })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            btn.disabled = false;
            btn.textContent = 'Mark No-show';
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.textContent = 'Mark No-show';
        });
    });
  });

  // ---------------- Review uploaded documents ----------------
  var docsReviewModal = document.getElementById('docsReviewModal');
  var docsReviewList = document.getElementById('docsReviewList');
  var docsResubmitReason = document.getElementById('docsResubmitReason');
  var docsReviewError = document.getElementById('docsReviewError');
  var docsConfirmBtn = document.getElementById('docsConfirmBtn');
  var docsResubmitBtn = document.getElementById('docsResubmitBtn');
  var currentDocsReviewId = null;
  var resubmitReasonShown = false;

  function closeDocsReviewModal() {
    docsReviewModal.classList.remove('open');
    currentDocsReviewId = null;
  }

  document.querySelectorAll('[data-docs-review-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentDocsReviewId = btn.dataset.docsReviewId;
      docsReviewList.innerHTML = '<li>Loading…</li>';
      docsResubmitReason.style.display = 'none';
      docsResubmitReason.value = '';
      resubmitReasonShown = false;
      docsResubmitBtn.textContent = 'Request Resubmission';
      docsReviewError.classList.remove('show');
      docsReviewModal.classList.add('open');

      fetch('ajax/get-appointment-documents.php?id=' + encodeURIComponent(currentDocsReviewId))
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.documents || !res.documents.length) {
            docsReviewList.innerHTML = '<li>No documents were uploaded.</li>';
            return;
          }
          docsReviewList.innerHTML = '';
          res.documents.forEach(function (doc) {
            var li = document.createElement('li');
            if (doc.requirement_label) {
              var strong = document.createElement('strong');
              strong.textContent = doc.requirement_label + ': ';
              li.appendChild(strong);
            }
            var a = document.createElement('a');
            a.href = 'ajax/view-appointment-document.php?id=' + doc.id;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = doc.original_name || ('Document #' + doc.id);
            li.appendChild(a);
            docsReviewList.appendChild(li);
          });
        })
        .catch(function () {
          docsReviewList.innerHTML = '<li>Could not load documents.</li>';
        });
    });
  });

  document.getElementById('docsReviewCancel').addEventListener('click', closeDocsReviewModal);
  docsReviewModal.addEventListener('click', function (e) { if (e.target === docsReviewModal) closeDocsReviewModal(); });

  docsConfirmBtn.addEventListener('click', function () {
    if (!confirm('Confirm these documents are correct? The parishioner will be able to pay next.')) return;
    docsConfirmBtn.disabled = true;
    docsConfirmBtn.textContent = 'Confirming…';
    postForm('ajax/verify-documents.php', { id: currentDocsReviewId })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          docsReviewError.textContent = res.data.error || 'Something went wrong.';
          docsReviewError.classList.add('show');
          docsConfirmBtn.disabled = false;
          docsConfirmBtn.textContent = 'Confirm Documents';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        docsReviewError.textContent = 'Network error. Please try again.';
        docsReviewError.classList.add('show');
        docsConfirmBtn.disabled = false;
        docsConfirmBtn.textContent = 'Confirm Documents';
      });
  });

  docsResubmitBtn.addEventListener('click', function () {
    if (!resubmitReasonShown) {
      docsResubmitReason.style.display = 'block';
      docsResubmitReason.focus();
      docsResubmitBtn.textContent = 'Send';
      resubmitReasonShown = true;
      return;
    }
    var reason = docsResubmitReason.value.trim();
    if (!reason) {
      docsReviewError.textContent = 'Please explain what needs to be fixed.';
      docsReviewError.classList.add('show');
      return;
    }
    docsReviewError.classList.remove('show');
    docsResubmitBtn.disabled = true;
    docsResubmitBtn.textContent = 'Sending…';
    postForm('ajax/request-document-resubmission.php', { id: currentDocsReviewId, reason: reason })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          docsReviewError.textContent = res.data.error || 'Something went wrong.';
          docsReviewError.classList.add('show');
          docsResubmitBtn.disabled = false;
          docsResubmitBtn.textContent = 'Send';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        docsReviewError.textContent = 'Network error. Please try again.';
        docsReviewError.classList.add('show');
        docsResubmitBtn.disabled = false;
        docsResubmitBtn.textContent = 'Send';
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