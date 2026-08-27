document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('serviceModal');
  var modalTitle = document.getElementById('serviceModalTitle');
  var svcId = document.getElementById('svcId');
  var svcKey = document.getElementById('svcKey');
  var svcName = document.getElementById('svcName');
  var svcDesc = document.getElementById('svcDesc');
  var svcFee = document.getElementById('svcFee');
  var svcRequirements = document.getElementById('svcRequirements');
  var svcFees = document.getElementById('svcFees');
  var svcCategory = document.getElementById('svcCategory');
  var schedSection = document.getElementById('schedSection');
  var svcCertFields = document.getElementById('svcCertFields');
  var certFieldsSection = document.getElementById('certFieldsSection');
  var serviceError = document.getElementById('serviceError');
  var serviceSave = document.getElementById('serviceSave');

  // Sacraments get Schedule Rules (they're booked on a date); certificate
  // requests get Certificate Form Fields instead (no date, but a
  // staff-defined set of inputs the requestor fills in) — mutually exclusive.
  function applyCategoryVisibility() {
    var isCertificate = svcCategory && svcCategory.value === 'certificate';
    if (schedSection) schedSection.style.display = isCertificate ? 'none' : '';
    if (certFieldsSection) certFieldsSection.style.display = isCertificate ? '' : 'none';
  }
  if (svcCategory) {
    svcCategory.addEventListener('change', applyCategoryVisibility);
  }

  // ---------------- Schedule rule builder ----------------
  var schedRulesList = document.getElementById('schedRulesList');
  var schedRuleTemplate = document.getElementById('schedRuleTemplate');
  var addScheduleRuleBtn = document.getElementById('addScheduleRuleBtn');
  var ruleCounter = 0;

  // Which .sched-field groups are shown for each rule_type
  var FIELD_MAP = {
    weekly:            ['dow', 'time', 'label'],
    nth_weekday:        ['dow', 'occ', 'time', 'label'],
    conditional:        ['trigger', 'time', 'label'],
    by_arrangement:     ['note'],
    always_available:   [],
  };

  function applyFieldVisibility(ruleEl) {
    var type = ruleEl.querySelector('.sr-type').value;
    var shown = FIELD_MAP[type] || [];
    ['dow', 'occ', 'trigger', 'time', 'label', 'note'].forEach(function (f) {
      var el = ruleEl.querySelector('.sr-field-' + f);
      if (!el) return;
      el.classList.toggle('show', shown.indexOf(f) !== -1);
    });
  }

  function addScheduleRule(prefill) {
    if (!schedRuleTemplate) return; // schedules migration not applied yet
    ruleCounter++;
    var frag = schedRuleTemplate.content.cloneNode(true);
    var ruleEl = frag.querySelector('.sched-rule');

    // Make radio name unique per rule instance so multiple rules don't clash
    ruleEl.querySelectorAll('.sr-dow').forEach(function (input, i) {
      var newName = 'sr-dow-' + ruleCounter;
      var newId = newName + '-' + i;
      var lbl = input.nextElementSibling;
      input.name = newName;
      input.id = newId;
      if (lbl) lbl.setAttribute('for', newId);
    });
    ruleEl.querySelectorAll('.sr-occ').forEach(function (input, i) {
      var newId = 'sr-occ-' + ruleCounter + '-' + i;
      var lbl = input.nextElementSibling;
      input.id = newId;
      if (lbl) lbl.setAttribute('for', newId);
    });

    var typeSelect = ruleEl.querySelector('.sr-type');
    typeSelect.addEventListener('change', function () { applyFieldVisibility(ruleEl); });

    ruleEl.querySelector('.sched-remove').addEventListener('click', function () {
      ruleEl.remove();
    });

    if (prefill) {
      typeSelect.value = prefill.rule_type || 'weekly';
      if (prefill.day_of_week !== null && prefill.day_of_week !== undefined && prefill.day_of_week !== '') {
        var dowInput = ruleEl.querySelector('.sr-dow[value="' + prefill.day_of_week + '"]');
        if (dowInput) dowInput.checked = true;
      }
      if (prefill.occurrences) {
        prefill.occurrences.split(',').map(function (s) { return s.trim(); }).forEach(function (v) {
          var occInput = ruleEl.querySelector('.sr-occ[value="' + v + '"]');
          if (occInput) occInput.checked = true;
        });
      }
      if (prefill.trigger_event) {
        var triggerSelect = ruleEl.querySelector('.sr-trigger');
        if (triggerSelect) triggerSelect.value = prefill.trigger_event;
      }
      if (prefill.offset_days !== null && prefill.offset_days !== undefined) {
        ruleEl.querySelector('.sr-offset').value = prefill.offset_days;
      }
      if (prefill.start_time) {
        ruleEl.querySelector('.sr-time').value = prefill.start_time;
      }
      if (prefill.label) {
        ruleEl.querySelector('.sr-label').value = prefill.label;
      }
      if (prefill.note) {
        ruleEl.querySelector('.sr-note').value = prefill.note;
      }
    }

    schedRulesList.appendChild(ruleEl);
    applyFieldVisibility(ruleEl);
  }

  if (addScheduleRuleBtn) {
    addScheduleRuleBtn.addEventListener('click', function () { addScheduleRule(null); });
  }

  function collectScheduleRules() {
    if (!schedRulesList) return [];
    var rules = [];
    schedRulesList.querySelectorAll('.sched-rule').forEach(function (ruleEl) {
      var type = ruleEl.querySelector('.sr-type').value;
      var dowInput = ruleEl.querySelector('.sr-dow:checked');
      var occInputs = ruleEl.querySelectorAll('.sr-occ:checked');
      var triggerSelect = ruleEl.querySelector('.sr-trigger');
      var offsetInput = ruleEl.querySelector('.sr-offset');
      var timeInput = ruleEl.querySelector('.sr-time');
      var labelInput = ruleEl.querySelector('.sr-label');
      var noteInput = ruleEl.querySelector('.sr-note');

      rules.push({
        rule_type: type,
        day_of_week: (type === 'weekly' || type === 'nth_weekday') && dowInput ? dowInput.value : null,
        occurrences: type === 'nth_weekday' && occInputs.length ? Array.prototype.map.call(occInputs, function (i) { return i.value; }).join(',') : null,
        trigger_event: type === 'conditional' && triggerSelect ? triggerSelect.value : null,
        offset_days: type === 'conditional' && offsetInput && offsetInput.value !== '' ? offsetInput.value : null,
        start_time: (type === 'weekly' || type === 'nth_weekday' || type === 'conditional') && timeInput ? timeInput.value : null,
        label: labelInput ? labelInput.value.trim() : '',
        note: type === 'by_arrangement' && noteInput ? noteInput.value.trim() : '',
      });
    });
    return rules;
  }

  function validateScheduleRules(rules) {
    for (var i = 0; i < rules.length; i++) {
      var r = rules[i];
      if ((r.rule_type === 'weekly' || r.rule_type === 'nth_weekday') && !r.day_of_week) {
        return 'Please choose a day of week for each weekly/specific-week rule.';
      }
      if (r.rule_type === 'nth_weekday' && !r.occurrences) {
        return 'Please choose which week(s) of the month for each "specific week" rule.';
      }
      if (r.rule_type === 'conditional' && !r.offset_days) {
        return 'Please enter how many days to wait for each "depends on another date" rule.';
      }
      if ((r.rule_type === 'weekly' || r.rule_type === 'nth_weekday' || r.rule_type === 'conditional') && !r.start_time) {
        return 'Please set a time for each scheduled rule.';
      }
      if (r.rule_type === 'by_arrangement' && !r.note) {
        return 'Please add a short note for each "by arrangement" rule.';
      }
    }
    return null;
  }

  function resetModal() {
    svcId.value = '';
    svcKey.value = '';
    svcKey.disabled = false;
    svcName.value = '';
    svcDesc.value = '';
    svcFee.value = '';
    svcRequirements.value = '';
    if (svcFees) svcFees.value = '';
    if (svcCertFields) svcCertFields.value = '';
    if (svcCategory) svcCategory.value = 'sacrament';
    document.querySelectorAll('input[name="svcIcon"]').forEach(function (r) { r.checked = false; });
    if (schedRulesList) schedRulesList.innerHTML = '';
    serviceError.classList.remove('show');
    applyCategoryVisibility();
  }

  function openModal() {
    modal.classList.add('open');
  }
  function closeModal() {
    modal.classList.remove('open');
  }

  document.getElementById('addServiceBtn').addEventListener('click', function () {
    resetModal();
    modalTitle.textContent = 'Add New Service';
    openModal();
  });

  document.querySelectorAll('.edit-service-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      resetModal();
      modalTitle.textContent = 'Edit ' + btn.dataset.name;
      svcId.value = btn.dataset.id;
      svcKey.value = btn.dataset.key;
      svcKey.disabled = true; // key is locked after creation
      svcName.value = btn.dataset.name;
      svcDesc.value = btn.dataset.desc;
      svcFee.value = btn.dataset.fee;
      svcRequirements.value = btn.dataset.requirements;
      if (svcFees) svcFees.value = btn.dataset.fees || '';
      if (svcCertFields) svcCertFields.value = btn.dataset.certFields || '';
      if (svcCategory) svcCategory.value = btn.dataset.category || 'sacrament';
      applyCategoryVisibility();
      var iconInput = document.getElementById('icon-' + btn.dataset.icon);
      if (iconInput) iconInput.checked = true;

      if (schedRulesList && btn.dataset.schedules) {
        try {
          var existingRules = JSON.parse(btn.dataset.schedules);
          existingRules.forEach(function (r) { addScheduleRule(r); });
        } catch (e) { /* no schedules yet for this service */ }
      }

      openModal();
    });
  });

  document.getElementById('serviceCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  serviceSave.addEventListener('click', function () {
    var icon = document.querySelector('input[name="svcIcon"]:checked');

    if (!svcId.value && !svcKey.value.trim()) {
      serviceError.textContent = 'Please enter a service key.';
      serviceError.classList.add('show');
      return;
    }
    if (!svcName.value.trim() || !svcDesc.value.trim()) {
      serviceError.textContent = 'Please fill in the name and description.';
      serviceError.classList.add('show');
      return;
    }
    if (svcFee.value === '' || Number(svcFee.value) < 0) {
      serviceError.textContent = 'Please enter a valid fee (0 or more).';
      serviceError.classList.add('show');
      return;
    }
    if (!icon) {
      serviceError.textContent = 'Please choose an icon.';
      serviceError.classList.add('show');
      return;
    }

    var scheduleRules = collectScheduleRules();
    var scheduleError = validateScheduleRules(scheduleRules);
    if (scheduleError) {
      serviceError.textContent = scheduleError;
      serviceError.classList.add('show');
      return;
    }

    if (svcFees) {
      var feeLines = svcFees.value.split('\n').map(function (l) { return l.trim(); }).filter(function (l) { return l !== ''; });
      for (var fi = 0; fi < feeLines.length; fi++) {
        var parts = feeLines[fi].split('|').map(function (p) { return p.trim(); });
        if (!parts[0] || parts[1] === undefined || parts[1] === '' || isNaN(Number(parts[1]))) {
          serviceError.textContent = 'Each fee line needs a label and a numeric amount, e.g. "Sponsors | 100".';
          serviceError.classList.add('show');
          return;
        }
      }
    }

    serviceError.classList.remove('show');
    serviceSave.disabled = true;
    serviceSave.textContent = 'Saving…';

    fetch('ajax/save-service.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        id: svcId.value,
        key: svcKey.value.trim(),
        name: svcName.value.trim(),
        description: svcDesc.value.trim(),
        fee: svcFee.value,
        icon: icon.value,
        requirements: svcRequirements.value,
        fees: svcFees ? svcFees.value : '',
        category: svcCategory ? svcCategory.value : 'sacrament',
        cert_fields: svcCertFields ? svcCertFields.value : '',
      }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          serviceError.textContent = res.data.error || 'Something went wrong.';
          serviceError.classList.add('show');
          serviceSave.disabled = false;
          serviceSave.textContent = 'Save Service';
          return null;
        }
        // Service key needed for the schedule save — either the existing
        // key (edit) or the one just typed in (add new).
        var savedKey = svcKey.value.trim();
        if (!schedRulesList) {
          window.location.reload();
          return null;
        }
        return fetch('ajax/save-schedule.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ service_key: savedKey, rules: scheduleRules }),
        }).then(function (r2) { return r2.json().then(function (d2) { return { ok: r2.ok, data: d2 }; }); });
      })
      .then(function (schedRes) {
        if (schedRes === null) return; // already handled above
        if (!schedRes.ok || schedRes.data.error) {
          serviceError.textContent = 'Service saved, but schedule failed: ' + (schedRes.data.error || 'unknown error');
          serviceError.classList.add('show');
          serviceSave.disabled = false;
          serviceSave.textContent = 'Save Service';
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        serviceError.textContent = 'Network error. Please try again.';
        serviceError.classList.add('show');
        serviceSave.disabled = false;
        serviceSave.textContent = 'Save Service';
      });
  });

  // ---------------- Activate / Deactivate ----------------
  document.querySelectorAll('.toggle-service-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var willActivate = btn.dataset.active === '0';
      var verb = willActivate ? 'reactivate' : 'deactivate';
      if (!confirm('Are you sure you want to ' + verb + ' this service?')) return;

      fetch('ajax/toggle-service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: btn.dataset.id }),
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            return;
          }
          window.location.reload();
        })
        .catch(function () { alert('Network error. Please try again.'); });
    });
  });

  // ---------------- Delete ----------------
  document.querySelectorAll('.delete-service-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var usage = parseInt(btn.dataset.usage, 10);
      if (usage > 0) {
        alert('"' + btn.dataset.name + '" has ' + usage + ' appointment(s) on file and can\'t be deleted. Deactivate it instead to preserve history.');
        return;
      }
      if (!confirm('Permanently delete "' + btn.dataset.name + '"? This cannot be undone.')) return;

      fetch('ajax/delete-service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: btn.dataset.id }),
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (!res.ok || res.data.error) {
            alert(res.data.error || 'Something went wrong.');
            return;
          }
          window.location.reload();
        })
        .catch(function () { alert('Network error. Please try again.'); });
    });
  });
});