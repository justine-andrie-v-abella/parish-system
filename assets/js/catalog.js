document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('serviceModal');
  var modalTitle = document.getElementById('serviceModalTitle');
  var svcId = document.getElementById('svcId');
  var svcKey = document.getElementById('svcKey');
  var svcName = document.getElementById('svcName');
  var svcDesc = document.getElementById('svcDesc');
  var svcFee = document.getElementById('svcFee');
  var svcRequirements = document.getElementById('svcRequirements');
  var serviceError = document.getElementById('serviceError');
  var serviceSave = document.getElementById('serviceSave');

  function resetModal() {
    svcId.value = '';
    svcKey.value = '';
    svcKey.disabled = false;
    svcName.value = '';
    svcDesc.value = '';
    svcFee.value = '';
    svcRequirements.value = '';
    document.querySelectorAll('input[name="svcIcon"]').forEach(function (r) { r.checked = false; });
    serviceError.classList.remove('show');
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
      var iconInput = document.getElementById('icon-' + btn.dataset.icon);
      if (iconInput) iconInput.checked = true;
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
      }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          serviceError.textContent = res.data.error || 'Something went wrong.';
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