<?php require_once 'includes/header.php'; ?>
<!-- index.php -->
<!-- ===================== Hero ===================== -->
<header class="hero" id="home" style="background-image: linear-gradient(180deg, rgba(15,26,48,0.88) 0%, rgba(15,26,48,0.72) 45%, rgba(15,26,48,0.92) 100%), url('assets/images/landing-page-background.jpeg'); background-size: cover; background-position: center;">
  <div class="container hero-inner">
    <div class="reveal visible">
      <span class="eyebrow hero-eyebrow"><?php echo htmlspecialchars($parish['des']); ?></span>
      <h1>Welcome home to <em>Our Lady of Mt. Carmel</em> Parish</h1>
      <p class="lede">Book baptisms, weddings, and Masses without the waiting line. Our appointment system carries the same warmth as walking through our doors — now from wherever you are.</p>
      <div class="hero-ctas">
        <a href="#services" class="btn btn-gold">Book an Appointment</a>
        <a href="#services" class="btn btn-outline btn-on-navy">View Services</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><strong>6</strong><span>Sacraments Offered</span></div>
        <div class="hero-stat"><strong>428</strong><span>Yrs. Since Founding</span></div>
        <div class="hero-stat"><strong>7</strong><span>Masses Weekly</span></div>
      </div>
    </div>

    <div class="hero-frame reveal visible">
      <img src="assets/images/landing-page-background.jpeg" alt="Parish church" style="width:100%; height:100%; object-fit:cover; border-radius: inherit;">
    </div>
  </div>
</header>

<!-- ===================== About / Church Info ===================== -->
<section id="about">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow">About the Parish</span>
      <h2>Rooted in tradition, open to everyone</h2>
      <p>Get to know our mission, mass schedule, and the priest who shepherds our community.</p>
    </div>

    <div class="info-grid">
      <div class="info-card reveal">
        <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 21C12 21 5 15.5 5 9.5C5 6.5 7.5 4 10.5 4C11.9 4 12 5 12 5C12 5 12.1 4 13.5 4C16.5 4 19 6.5 19 9.5C19 15.5 12 21 12 21Z"/></svg>
        <h3>Our Mission</h3>
        <p>To gather the faithful of Tagbilaran in worship, sacrament, and service — carrying Christ's love into every home we touch.</p>
      </div>
      <div class="info-card reveal">
        <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 2L14.5 8.5L21 9L16 13.5L17.5 20L12 16.5L6.5 20L8 13.5L3 9L9.5 8.5L12 2Z"/></svg>
        <h3>Our Vision</h3>
        <p>A parish where every sacrament is received with dignity, and every parishioner is known by name, not by number.</p>
      </div>
      <div class="info-card reveal">
        <h3>Mass Schedule</h3>
        <div style="margin-top:14px;">
          <?php foreach ($mass_schedule as $row): ?>
            <div class="sched-row"><span><?php echo htmlspecialchars($row[0]); ?></span><b><?php echo htmlspecialchars($row[1]); ?></b></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="info-card reveal">
        <h3>Office Hours</h3>
        <div style="margin-top:14px;">
          <?php foreach ($office_hours as $row): ?>
            <div class="sched-row"><span><?php echo htmlspecialchars($row[0]); ?></span><b><?php echo htmlspecialchars($row[1]); ?></b></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="info-card priest-card reveal" style="margin-top:22px; max-width:520px;">
      <div class="priest-avatar">FS</div>
      <div>
        <h3 style="margin-bottom:2px;"><?php echo htmlspecialchars($parish['priest']); ?></h3>
        <p style="margin:0; color:var(--gold-dim); font-family:var(--font-mono); font-size:12px; letter-spacing:0.5px;"><?php echo htmlspecialchars($parish['priest_role']); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ===================== Services ===================== -->
<section id="services" class="alt-bg">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow">Parish Services</span>
      <h2>Sacraments &amp; appointments</h2>
      <p>Every card below opens into a guided request form — choose a date, upload your documents, and we'll take it from there.</p>
    </div>

    <div class="services-grid">
      <?php
      $icons = [
        'dove'   => '<path d="M3 12c3-4 7-6 9-2 2-4 6-2 9 2-3 6-7 4-9 2-2 2-6 4-9-2Z"/><circle cx="12" cy="9" r="1" fill="currentColor" stroke="none"/>',
        'flame'  => '<path d="M12 2c1 4-3 5-3 9a3 3 0 0 0 6 0c0-1.5-1-2-1-2s2 1 2 4a5 5 0 0 1-10 0C6 8 10 7 12 2Z"/>',
        'rings'  => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/>',
        'cross'  => '<path d="M12 3v18M6 9h12"/>',
        'candle' => '<path d="M12 2c1 2-1 2.5-1 4a1 1 0 0 0 2 0c0-1.5-2-2-1-4Z"/><rect x="9" y="8" width="6" height="13" rx="1"/><path d="M9 12h6"/>',
        'vessel' => '<path d="M8 3h8M12 3v4"/><path d="M6 9c0-1.1 2.7-2 6-2s6 .9 6 2-2.7 8-6 10c-3.3-2-6-8.9-6-10Z"/>',
      ];
      foreach ($services as $svc): ?>
        <div class="service-card reveal">
          <div class="service-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[$svc['icon']]; ?></svg>
          </div>
          <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
          <p class="desc"><?php echo htmlspecialchars($svc['desc']); ?></p>
          <div class="service-fee">Estimated fee: <b><?php echo $svc['fee'] > 0 ? '₱' . number_format($svc['fee']) : 'Free'; ?></b></div>
          <a href="#" class="btn btn-outline btn-sm">Learn More</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== Requirements ===================== -->
<section id="requirements">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow">Before You Book</span>
      <h2>Requirements per sacrament</h2>
      <p>Prepare these documents ahead of time to keep your appointment on schedule.</p>
    </div>

    <div class="req-list">
      <?php $i = 0; foreach ($services as $svc): $i++; ?>
        <div class="req-item reveal<?php echo $i === 1 ? ' open' : ''; ?>">
          <button class="req-trigger">
            <span><?php echo htmlspecialchars($svc['name']); ?></span>
            <span class="plus">+</span>
          </button>
          <div class="req-body">
            <ul>
              <?php foreach ($requirements[$svc['key']] as $doc): ?>
                <li><?php echo htmlspecialchars($doc); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== Contact ===================== -->
<section id="contact" class="alt-bg">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow">Get in Touch</span>
      <h2>Visit or reach the parish office</h2>
    </div>

    <div class="contact-grid">
      <div class="contact-map reveal">
        <iframe
          src="https://maps.google.com/maps?q=Tagbilaran%20City%20Bohol%20Church&t=&z=14&ie=UTF8&iwloc=&output=embed"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          title="Parish location map"></iframe>
      </div>

      <div class="reveal">
        <ul class="contact-list">
          <li>
            <span class="ci-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
            <div><h4>Address</h4><p><?php echo htmlspecialchars($parish['address']); ?></p></div>
          </li>
          <li>
            <span class="ci-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.9.7 2.7a2 2 0 0 1-.4 2.1L8.1 9.8a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2.2Z"/></svg></span>
            <div><h4>Phone</h4><p><?php echo htmlspecialchars($parish['phone']); ?></p></div>
          </li>
          <li>
            <span class="ci-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg></span>
            <div><h4>Email</h4><p><?php echo htmlspecialchars($parish['email']); ?></p></div>
          </li>
          <li>
            <span class="ci-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/></svg></span>
            <div>
              <h4>Follow Us</h4>
              <div class="social-row">
                <a href="#" aria-label="Facebook"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/></svg></a>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>