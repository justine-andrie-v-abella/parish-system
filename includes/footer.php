<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <svg width="30" height="30" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="23" fill="#0B1424"/><circle cx="24" cy="24" r="23" stroke="#C6A15B" stroke-width="1"/><path d="M24 10V38M14 18H34M24 10C20 14 20 18 24 21C28 18 28 14 24 10Z" stroke="#E7C883" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <strong><?php echo htmlspecialchars($parish['name']); ?></strong>
        </div>
        <p style="max-width:34ch; font-size:14px;">A place of prayer, sacrament, and community in the heart of <?php echo explode(',', $parish['address'])[1] ?? 'the city'; ?>. Every appointment is a small act of welcome.</p>
      </div>
      <div>
        <h5>Quick Links</h5>
        <ul>
          <li><a href="#about">About the Parish</a></li>
          <li><a href="#services">Services &amp; Fees</a></li>
          <li><a href="#requirements">Requirements</a></li>
          <li><a href="login.php">Login / Register</a></li>
        </ul>
      </div>
      <div>
        <h5>Parish Information</h5>
        <ul>
          <li><?php echo htmlspecialchars($parish['address']); ?></li>
          <li><?php echo htmlspecialchars($parish['phone']); ?></li>
          <li><?php echo htmlspecialchars($parish['email']); ?></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($parish['name']); ?>. All rights reserved.</span>
      <span>Built with care for the parish community.</span>
    </div>
  </div>
</footer>

<!-- ===================== Chatbot ===================== -->
<button class="chat-fab" id="chatFab" aria-label="Open parish assistant chat">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</button>

<div class="chat-panel" id="chatPanel" role="dialog" aria-label="Parish assistant chat">
  <div class="chat-head">
    <div class="avatar">
      <svg width="18" height="18" viewBox="0 0 48 48" fill="none"><path d="M24 6V42M12 16H36" stroke="#16223F" stroke-width="3" stroke-linecap="round"/></svg>
    </div>
    <div>
      <strong>Parish Assistant</strong>
      <span>Usually replies instantly</span>
    </div>
    <button class="chat-close" id="chatClose" aria-label="Close chat">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="chat-body" id="chatBody"></div>
  <div class="suggested" id="chatSuggested"></div>
  <form class="chat-input" id="chatForm">
    <input type="text" id="chatInput" placeholder="Ask about requirements, fees, hours…" autocomplete="off">
    <button type="submit" aria-label="Send message">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2 15 22l-4-9-9-4 20-7z"/></svg>
    </button>
  </form>
</div>

<script>
  window.PARISH_DATA = <?php echo json_encode([
      'parish' => $parish,
      'office_hours' => $office_hours,
      'mass_schedule' => $mass_schedule,
      'services' => $services,
      'requirements' => $requirements,
  ], JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="assets/js/main.js"></script>
<script src="assets/js/chatbot.js"></script>
</body>
</html>