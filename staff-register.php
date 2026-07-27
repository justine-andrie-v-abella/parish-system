<?php
$page_title = 'Staff Account Setup — ' . 'San Vicente Ferrer Parish';
require_once 'includes/auth-header.php';
$error = flash_error();
$o = [
  'role'     => old('role', 'priest'),
  'fullname' => old('fullname'),
  'email'    => old('email'),
];
clear_old();
?>

<div class="auth-page">
  <div class="auth-visual staff">
    <svg class="auth-rose" viewBox="0 0 480 480" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <circle cx="240" cy="240" r="220" stroke="#E7C883" stroke-width="1"/>
      <circle cx="240" cy="240" r="160" stroke="#E7C883" stroke-width="1"/>
      <circle cx="240" cy="240" r="90" stroke="#E7C883" stroke-width="1"/>
    </svg>

    <div class="auth-visual-top">
      <svg width="34" height="34" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="23" fill="#0B1424"/><circle cx="24" cy="24" r="23" stroke="#C6A15B" stroke-width="1"/><path d="M24 10V38M14 18H34M24 10C20 14 20 18 24 21C28 18 28 14 24 10Z" stroke="#E7C883" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span><strong style="font-family:var(--font-display); font-size:18px; color:#fff;"><?php echo htmlspecialchars($parish['name']); ?></strong><span>Internal Staff Portal</span></span>
    </div>

    <div class="auth-visual-mid">
      <span class="eyebrow">Initial Setup Only</span>
      <h1>Provision your office account.</h1>
      <p>This page creates the first login for a Priest, Secretary, or Treasurer. Once every role has an account, it should be taken down or locked behind an admin invite.</p>
    </div>

    <blockquote class="auth-visual-quote">
      "Well done, good and faithful servant."
      <cite>Matthew 25:23</cite>
    </blockquote>
  </div>

  <div class="auth-form-panel">
    <div class="auth-card" style="max-width:460px;">
      <a href="staff-login.php" class="auth-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Back to staff sign in
      </a>

      <h2>Create staff account</h2>
      <p class="sub">Temporary setup page — for initial provisioning only.</p>

      <?php if ($error): ?>
        <div class="form-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <div class="temp-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <span>This page is meant to be disabled after the Priest, Secretary, and Treasurer accounts are created. In the full build, gate it behind an admin-issued invite code.</span>
      </div>

      <form action="staff-register-process.php" method="POST">
        <div class="form-group">
          <label>Role</label>
          <div class="role-grid">
            <?php foreach ($staff_roles as $key => $role): ?>
              <div class="role-card">
                <input type="radio" name="role" id="role-<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $o['role'] === $key ? 'checked' : ''; ?>>
                <label for="role-<?php echo $key; ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                  <?php echo htmlspecialchars($role['label']); ?>
                  <span style="font-size:10.5px; color:var(--ink-soft); font-weight:400;"><?php echo htmlspecialchars($role['sub']); ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label for="staff-fullname">Full name</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
            <input type="text" id="staff-fullname" name="fullname" placeholder="Rev. Fr. Juan Dela Cruz" value="<?php echo $o['fullname']; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="staff-reg-email">Parish email address</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
            <input type="email" id="staff-reg-email" name="email" placeholder="you@svfparish.ph" value="<?php echo $o['email']; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="invite-code">Admin invite code</label>
          <div class="input-wrap no-icon">
            <input type="text" id="invite-code" name="invite_code" placeholder="e.g. SVF-2026-XXXX" required>
          </div>
          <p class="code-hint">Provided by the Parish Priest / Administrator. Prevents public sign-up to internal roles.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="staff-reg-password">Password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="staff-reg-password" name="password" placeholder="••••••••" required style="padding-right:42px;">
              <button type="button" class="pw-toggle" data-target="staff-reg-password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label for="staff-confirm-password">Confirm password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="staff-confirm-password" name="confirm_password" placeholder="••••••••" required style="padding-right:42px;">
              <button type="button" class="pw-toggle" data-target="staff-confirm-password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-gold btn-block">Create Staff Account</button>
      </form>

      <p class="auth-switch">Already have an account? <a href="staff-login.php">Sign in</a></p>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>