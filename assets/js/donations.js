document.addEventListener('DOMContentLoaded', function () {
  // One button per tab panel (Mass Intentions / Sacraments), same modal.
  var donateBtns = document.querySelectorAll('.donate-trigger');
  var modal = document.getElementById('donateModal');
  if (!donateBtns.length || !modal) return;

  var form = document.getElementById('donateForm');
  var donorName = document.getElementById('donorName');
  var donorEmail = document.getElementById('donorEmail');
  var amount = document.getElementById('donationAmount');
  var message = document.getElementById('donationMessage');
  var methodHint = document.getElementById('donationMethodHint');
  var errorEl = document.getElementById('donateError');
  var successEl = document.getElementById('donateSuccess');
  var submitBtn = document.getElementById('donateSubmit');

  function openModal() {
    form.reset();
    errorEl.classList.remove('show');
    successEl.classList.remove('show');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Donate Now';
    methodHint.style.display = 'none';
    modal.classList.add('open');
  }
  function closeModal() {
    modal.classList.remove('open');
  }

  donateBtns.forEach(function (btn) { btn.addEventListener('click', openModal); });
  document.getElementById('donateModalClose').addEventListener('click', closeModal);
  document.getElementById('donateCancel').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  // Only GCash is charged automatically right now — everything else is a
  // manual "the office will confirm" flow, same as Cash works elsewhere.
  document.querySelectorAll('input[name="donationMethod"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      methodHint.style.display = radio.value !== 'gcash' && radio.checked ? 'block' : methodHint.style.display;
    });
  });
  form.addEventListener('change', function () {
    var checked = form.querySelector('input[name="donationMethod"]:checked');
    methodHint.style.display = checked && checked.value !== 'gcash' ? 'block' : 'none';
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errorEl.classList.remove('show');
    successEl.classList.remove('show');

    if (!donorEmail.value.trim()) {
      errorEl.textContent = 'Please enter your email address.';
      errorEl.classList.add('show');
      return;
    }
    if (!amount.value || Number(amount.value) <= 0) {
      errorEl.textContent = 'Please enter a donation amount.';
      errorEl.classList.add('show');
      return;
    }

    var purpose = form.querySelector('input[name="donationPurpose"]:checked').value;
    var method = form.querySelector('input[name="donationMethod"]:checked').value;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    fetch('ajax/submit-donation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        donor_name: donorName.value.trim(),
        email: donorEmail.value.trim(),
        amount: amount.value,
        purpose: purpose,
        payment_method: method,
        message: message.value.trim(),
      }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || res.data.error) {
          errorEl.textContent = res.data.error || 'Something went wrong. Please try again.';
          errorEl.classList.add('show');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Donate Now';
          return;
        }
        if (res.data.checkout_url) {
          window.location.href = res.data.checkout_url;
          return;
        }
        successEl.textContent = res.data.message || 'Thank you for your donation!';
        successEl.classList.add('show');
        submitBtn.textContent = 'Submitted';
        setTimeout(closeModal, 1800);
      })
      .catch(function () {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.add('show');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Donate Now';
      });
  });
});
