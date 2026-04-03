<?php
$page_title = 'Home';
$page_id = 'home';
$ui_theme = 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>XPLabs — Computer Lab System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-main: #0f172a;
      --bg-card: #1e293b;
      --border: #334155;
      --text: #e2e8f0;
      --text-muted: #94a3b8;
      --accent: #6366f1;
      --accent-glow: rgba(99, 102, 241, 0.3);
      --green: #22c55e;
      --yellow: #eab308;
      --red: #ef4444;
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      background: var(--bg-main);
      color: var(--text);
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
    }
    
    .navbar {
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      padding: 1rem 0;
    }
    .navbar-brand {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text) !important;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .navbar-brand i { color: var(--accent); }
    
    .btn-login {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-login:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    .hero-section {
      position: relative;
      min-height: calc(100vh - 120px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 4rem 2rem;
      overflow: hidden;
    }
    
    .hero-bg {
      position: absolute;
      inset: 0;
      background: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
      z-index: 0;
    }
    
    .hero-grid {
      position: absolute;
      inset: 0;
      background-image: 
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 50px 50px;
      z-index: 0;
    }
    
    .hero-content {
      position: relative;
      z-index: 1;
      text-align: center;
      max-width: 800px;
      margin: 0 auto;
    }
    
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(99, 102, 241, 0.3);
      color: var(--accent);
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
    }
    
    .hero-badge i { font-size: 1rem; }
    
    .hero-title {
      font-size: clamp(2.5rem, 6vw, 4rem);
      font-weight: 800;
      line-height: 1.1;
      margin-bottom: 1.5rem;
      background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .hero-subtitle {
      font-size: 1.15rem;
      color: var(--text-muted);
      line-height: 1.7;
      margin-bottom: 2rem;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      justify-content: center;
      margin-bottom: 4rem;
    }
    
    .btn-hero {
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
    }
    
    .btn-hero-primary {
      background: var(--accent);
      color: #fff;
      border: none;
    }
    .btn-hero-primary:hover {
      background: #4f46e5;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 4px 20px var(--accent-glow);
    }
    
    .btn-hero-outline {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border);
    }
    .btn-hero-outline:hover {
      border-color: var(--accent);
      color: var(--accent);
      transform: translateY(-2px);
    }
    
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }
    
    .feature-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
      transition: all 0.3s;
    }
    .feature-card:hover {
      border-color: var(--accent);
      transform: translateY(-4px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    
    .feature-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin: 0 auto 1rem;
    }
    
    .feature-icon.purple { background: rgba(99, 102, 241, 0.15); color: var(--accent); }
    .feature-icon.green { background: rgba(34, 197, 94, 0.15); color: var(--green); }
    .feature-icon.yellow { background: rgba(234, 179, 8, 0.15); color: var(--yellow); }
    .feature-icon.red { background: rgba(239, 68, 68, 0.15); color: var(--red); }
    
    .feature-card h5 {
      color: var(--text);
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .feature-card p {
      color: var(--text-muted);
      font-size: 0.85rem;
      line-height: 1.5;
      margin: 0;
    }
    
    .footer-nav {
      background: var(--bg-card);
      border-top: 1px solid var(--border);
      padding: 1.5rem 0;
      text-align: center;
    }
    
    .footer-nav p {
      color: var(--text-muted);
      font-size: 0.85rem;
      margin: 0;
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="#" class="navbar-brand">
        <i class="bi bi-flask"></i> XPLabs
      </a>
      <a href="login.php" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-1"></i> Login
      </a>
    </div>
  </nav>

  <section class="hero-section">
    <div class="hero-grid"></div>
    <div class="hero-bg"></div>
    <div class="hero-content">
      <div class="hero-badge">
        <i class="bi bi-controller"></i> Lab-ready · Attendance & quests
      </div>
      <h1 class="hero-title">Computer Lab System</h1>
      <p class="hero-subtitle">
        Check in fast, submit assignments, and climb the leaderboard — built for real class flow with gamified rewards.
      </p>
      <div class="hero-actions">
        <a href="login.php" class="btn-hero btn-hero-primary">
          <i class="bi bi-box-arrow-in-right"></i> Go to Login
        </a>
        <a href="dashboard_student.php" class="btn-hero btn-hero-outline">
          <i class="bi bi-mortarboard"></i> Student Hub
        </a>
        <a href="dashboard_teacher.php" class="btn-hero btn-hero-outline">
          <i class="bi bi-person-workspace"></i> Teacher Hub
        </a>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon purple"><i class="bi bi-qr-code"></i></div>
          <h5>QR Check-in</h5>
          <p>Fast attendance with QR code scanning at the lab kiosk</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon yellow"><i class="bi bi-trophy"></i></div>
          <h5>Gamification</h5>
          <p>Earn points, unlock achievements, climb the leaderboard</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon green"><i class="bi bi-journal-check"></i></div>
          <h5>Assignments</h5>
          <p>Submit work online, track grades, never miss a deadline</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon red"><i class="bi bi-display"></i></div>
          <h5>Lab Monitor</h5>
          <p>Real-time seat plan showing active stations and occupancy</p>
        </div>
      </div>
    </div>
  </section>

  <footer class="footer-nav">
    <p>&copy; 2024 XPLabs — Gamified Computer Laboratory Management System</p>
  </footer>
</body>
</html>