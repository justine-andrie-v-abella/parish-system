<?php
$page_title = 'Staff Sign In — ' . 'San Vicente Ferrer Parish';
require_once 'includes/auth-header.php';
$error    = flash_error();
$oldEmail = old('email');
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
      <span class="eyebrow">Staff Access</span>
      <h1>The office, organized.</h1>
      <p>For the Priest, Secretary, and Treasurer — manage requests, schedules, payments, and records from one place.</p>
    </div>

    <blockquote class="auth-visual-quote">
      "Each of you should use whatever gift you have received to serve others."
      <cite>1 Peter 4:10</cite>
    </blockquote>
  </div>

  <div class="auth-form-panel">
    <div class="auth-card">
      <a href="index.php" class="auth-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Back to homepage
      </a>

      <h2>Staff sign in</h2>
      <p class="sub">For Priest, Secretary, and Treasurer accounts.</p>

      <?php if ($error): ?>
        <div class="form-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form action="staff-login-process.php" method="POST">
        <div class="form-group">
          <label for="staff-email">Email address</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
            <input type="email" id="staff-email" name="email" placeholder="you@svfparish.ph" value="<?php echo $oldEmail; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="staff-password">Password</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <input type="password" id="staff-password" name="password" placeholder="••••••••" required style="padding-right:42px;">
            <button type="button" class="pw-toggle" data-target="staff-password" aria-label="Show password">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="form-meta">
          <label class="checkbox-row"><input type="checkbox" name="remember"> Remember me</label>
          <a href="#">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-gold btn-block">Sign In</button>
      </form>

      <p class="auth-switch" style="margin-top:22px;">Setting up a staff account for the first time? <a href="staff-register.php">Create staff account</a></p>

      <div class="auth-links-row">
        <a href="login.php">← Parishioner login</a>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>