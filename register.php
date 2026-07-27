<?php
$page_title = 'Create Account — ' . 'San Vicente Ferrer Parish';
require_once 'includes/auth-header.php';
$error = flash_error();
$o = [
  'fullname' => old('fullname'),
  'address'  => old('address'),
  'birthday' => old('birthday'),
  'contact'  => old('contact'),
  'email'    => old('email'),
];
clear_old();
?>

<div class="auth-page">
  <div class="auth-visual">
    <svg class="auth-rose" viewBox="0 0 480 480" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <circle cx="240" cy="240" r="220" stroke="#E7C883" stroke-width="1"/>
      <circle cx="240" cy="240" r="160" stroke="#E7C883" stroke-width="1"/>
      <circle cx="240" cy="240" r="90" stroke="#E7C883" stroke-width="1"/>
      <?php for ($i = 0; $i < 12; $i++): $a = $i * 30; ?>
        <line x1="240" y1="240" x2="<?php echo 240 + 220 * cos(deg2rad($a)); ?>" y2="<?php echo 240 + 220 * sin(deg2rad($a)); ?>" stroke="#E7C883" stroke-width="0.75"/>
      <?php endfor; ?>
    </svg>

    <div class="auth-visual-top">
      <svg width="34" height="34" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="23" fill="#0B1424"/><circle cx="24" cy="24" r="23" stroke="#C6A15B" stroke-width="1"/><path d="M24 10V38M14 18H34M24 10C20 14 20 18 24 21C28 18 28 14 24 10Z" stroke="#E7C883" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span><strong style="font-family:var(--font-display); font-size:18px; color:#fff;"><?php echo htmlspecialchars($parish['name']); ?></strong><span><?php echo htmlspecialchars($parish['diocese']); ?></span></span>
    </div>

    <div class="auth-visual-mid">
      <span class="eyebrow">Parishioner Portal</span>
      <h1>Join the parish community online.</h1>
      <p>Create your account once, and every future baptism, wedding, or Mass request is just a few taps away.</p>
    </div>

    <blockquote class="auth-visual-quote">
      "Where two or three gather in my name, there am I with them."
      <cite>Matthew 18:20</cite>
    </blockquote>
  </div>

  <div class="auth-form-panel">
    <div class="auth-card" style="max-width:460px;">
      <a href="index.php" class="auth-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Back to homepage
      </a>

      <h2>Create your account</h2>
      <p class="sub">Registration is only required once — as a parishioner.</p>

      <?php if ($error): ?>
        <div class="form-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form action="register-process.php" method="POST">
        <div class="form-group">
          <label for="fullname">Full name</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
            <input type="text" id="fullname" name="fullname" placeholder="Juan D. Dela Cruz" value="<?php echo $o['fullname']; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="address">Home address</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text" id="address" name="address" placeholder="Street, Barangay, City" value="<?php echo $o['address']; ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="birthday">Birthday</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
              <input type="date" id="birthday" name="birthday" value="<?php echo $o['birthday']; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label for="contact">Contact number</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.9.7 2.7a2 2 0 0 1-.4 2.1L8.1 9.8a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2.2Z"/></svg>
              <input type="tel" id="contact" name="contact" placeholder="09XX XXX XXXX" value="<?php echo $o['contact']; ?>" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="reg-email">Email address</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
            <input type="email" id="reg-email" name="email" placeholder="you@email.com" value="<?php echo $o['email']; ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="reg-password">Password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="reg-password" name="password" placeholder="••••••••" required style="padding-right:42px;">
              <button type="button" class="pw-toggle" data-target="reg-password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm-password">Confirm password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="confirm-password" name="confirm_password" placeholder="••••••••" required style="padding-right:42px;">
              <button type="button" class="pw-toggle" data-target="confirm-password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 11s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="11" r="3"/></svg>
              </button>
            </div>
          </div>
        </div>

        <label class="checkbox-row" style="margin-bottom:20px;">
          <input type="checkbox" name="agree" required> I agree to the parish's data privacy and terms of use
        </label>

        <button type="submit" class="btn btn-gold btn-block">Create Account</button>
      </form>

      <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>