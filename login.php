<?php
$page_title = 'Sign In — ' . 'San Vicente Ferrer Parish';
require_once 'includes/auth-header.php';
$error     = flash_error();
$oldEmail  = old('email');
clear_old();
?>

<div class="auth-page">
  <div class="auth-visual" style="background-image: linear-gradient(180deg, rgba(11,20,36,0.88) 0%, rgba(11,20,36,0.72) 45%, rgba(11,20,36,0.92) 100%), url('assets/images/login.jpeg'); background-size: cover; background-position: center;">

    <div class="auth-visual-top">
      <img src="assets/images/logo.jpeg" alt="<?php echo htmlspecialchars($parish['name']); ?> logo" width="34" height="34" style="border-radius:50%; object-fit:cover;">
      <span><strong style="font-family:var(--font-display); font-size:18px; color:#fff;"><?php echo htmlspecialchars($parish['name']); ?></strong><span><?php echo htmlspecialchars($parish['des']); ?></span></span>
    </div>

    <div class="auth-visual-mid">
      <span class="eyebrow">Parishioner Portal</span>
      <h1>Welcome back to the parish family.</h1>
      <p>Sign in to book appointments, track your requests, and stay close to your parish — wherever you are.</p>
    </div>

    <blockquote class="auth-visual-quote">
      "Let all that you do be done in love."
      <cite>1 Corinthians 16:14</cite>
    </blockquote>
  </div>

  <div class="auth-form-panel">
    <div class="auth-card">
      <a href="index.php" class="auth-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Back to homepage
      </a>

      <h2>Sign in</h2>
      <p class="sub">Access your appointments and requests.</p>

      <?php if ($error): ?>
        <div class="form-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <form action="login-process.php" method="POST">
        <div class="form-group">
          <label for="email">Email address</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
            <input type="email" id="email" name="email" placeholder="you@email.com" value="<?php echo $oldEmail; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <input type="password" id="password" name="password" placeholder="••••••••" required style="padding-right:42px;">
            <button type="button" class="pw-toggle" data-target="password" aria-label="Show password">
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

      <p class="auth-switch" style="margin-top:22px;">New to the parish? <a href="register.php">Create an account</a></p>

      <div class="auth-links-row">
        <a href="staff-login.php">Priest / Secretary / Treasurer login →</a>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>