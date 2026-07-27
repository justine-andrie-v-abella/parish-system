// Sticky navbar shadow
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 12);
}, { passive: true });

// Mobile menu: clone links into a collapsible panel
const navToggle = document.getElementById('navToggle');
const mobileMenu = document.getElementById('mobileMenu');
const desktopLinks = document.querySelector('.nav-links');
if (navToggle && mobileMenu && desktopLinks) {
  mobileMenu.innerHTML = desktopLinks.innerHTML +
    '<li><a href="login.php" class="btn btn-outline btn-sm" style="width:100%; margin-top:4px;">Login</a></li>' +
    '<li><a href="#services" class="btn btn-gold btn-sm" style="width:100%;">Book an Appointment</a></li>';
  navToggle.addEventListener('click', () => {
    const isOpen = mobileMenu.style.display === 'flex';
    mobileMenu.style.display = isOpen ? 'none' : 'flex';
    navToggle.setAttribute('aria-expanded', String(!isOpen));
  });
  mobileMenu.addEventListener('click', (e) => {
    if (e.target.tagName === 'A') mobileMenu.style.display = 'none';
  });
}

// Scroll-reveal
const revealEls = document.querySelectorAll('.reveal');
const io = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      io.unobserve(entry.target);
    }
  });
}, { threshold: 0.15 });
revealEls.forEach(el => io.observe(el));

// Requirements accordion
document.querySelectorAll('.req-trigger').forEach(btn => {
  btn.addEventListener('click', () => {
    const item = btn.closest('.req-item');
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.req-item.open').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });
});