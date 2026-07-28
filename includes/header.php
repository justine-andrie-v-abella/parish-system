<?php
/**
 * includes/header.php
 * Shared <head> and navbar for public site pages.
 */
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($parish['name']); ?> — Appointment & Management System</title>
<meta name="description" content="Book baptisms, weddings, and parish sacraments online with <?php echo htmlspecialchars($parish['name']); ?>.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Jost:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="nav-inner">
    <a href="#home" class="brand">
      <img src="assets/images/logo.jpeg" alt="<?php echo htmlspecialchars($parish['name']); ?> logo" class="brand-mark">
      <span class="brand-text">
        <strong><?php echo htmlspecialchars($parish['name']); ?></strong>
        <span><?php echo htmlspecialchars($parish['des']); ?></span>
      </span>
    </a>

    <ul class="nav-links">
      <li><a href="#home">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#services">Services</a></li>
      <li><a href="#schedule">Schedule</a></li>
      <li><a href="#requirements">Requirements</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>

    <div class="nav-actions">
      <a href="login.php" class="btn btn-outline btn-sm">Login</a>
      <a href="#services" class="btn btn-gold btn-sm">Book an Appointment</a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16223F" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
  <ul class="nav-links" id="mobileMenu" style="display:none; flex-direction:column; padding:0 28px 20px; gap:16px;"></ul>
</nav>