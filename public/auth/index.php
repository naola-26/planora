<?php

$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/controllers/AuthController.php';

startSession();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
}

if (isLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

$error = '';
$mode  = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $result = AuthController::handleRegister();
        if (!empty($result['success'])) { header('Location: /dashboard/'); exit; }
        $error = $result['error'] ?? 'Something went wrong.';
        $mode  = 'register';
    }

    if ($action === 'login') {
        $result = AuthController::handleLogin();
        if (!empty($result['success'])) { header('Location: /dashboard/'); exit; }
        $error = $result['error'] ?? 'Something went wrong.';
        $mode  = 'login';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StudyPlanner — <?= $mode === 'login' ? 'Sign in' : 'Create account' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --ink: #1a1a18;
      --ink-2: #5a5a56;
      --ink-3: #9a9a94;
      --paper: #f8f7f3;
      --paper-2: #efede6;
      --accent: #3b6ef5;
      --border: #e0ddd4;
      --error: #c0392b;
      --radius: 12px;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
      -webkit-font-smoothing: antialiased;
    }

    /* LEFT PANEL */
    .left-panel {
      background: var(--ink);
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 100vh;
    }

    .left-logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .left-logo-dot {
      width: 10px; height: 10px;
      background: var(--accent);
      border-radius: 50%;
    }

    .left-logo-name {
      font-size: 1rem;
      font-weight: 500;
      color: white;
      letter-spacing: 0.02em;
    }

    .left-headline {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(2rem, 3.5vw, 3rem);
      line-height: 1.15;
      color: white;
      letter-spacing: -0.02em;
    }

    .left-headline em {
      font-style: italic;
      color: #a0b4fa;
    }

    .left-footer {
      font-size: 0.8rem;
      color: #5a5a56;
      line-height: 1.6;
    }

    .features {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .feature {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }

    .feature-dot {
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      margin-top: 6px;
      flex-shrink: 0;
    }

    .feature-text {
      font-size: 0.875rem;
      color: #9a9a94;
      line-height: 1.5;
    }

    .feature-text strong {
      color: white;
      font-weight: 500;
      display: block;
    }

    /* RIGHT PANEL */
    .right-panel {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 2rem;
      background: var(--paper);
    }

    .form-wrap {
      width: 100%;
      max-width: 400px;
    }

    .form-title {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.75rem;
      color: var(--ink);
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }

    .form-sub {
      font-size: 0.875rem;
      color: var(--ink-3);
      margin-bottom: 2rem;
    }

    /* Tabs */
    .tabs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: var(--paper-2);
      border-radius: var(--radius);
      padding: 3px;
      margin-bottom: 2rem;
    }

    .tab {
      padding: 0.65rem;
      text-align: center;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--ink-3);
      border-radius: 10px;
      cursor: pointer;
      text-decoration: none;
      transition: all 150ms ease;
    }

    .tab.active {
      background: white;
      color: var(--ink);
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }

    .form-group { margin-bottom: 1.1rem; }

    label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--ink-2);
      margin-bottom: 0.4rem;
    }

    input {
      width: 100%;
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      font-family: 'DM Sans', system-ui, sans-serif;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: white;
      color: var(--ink);
      outline: none;
      transition: border-color 150ms, box-shadow 150ms;
    }

    input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59,110,245,0.1);
    }

    .error-box {
      background: #fdf2f2;
      border: 1px solid #f5c6c6;
      color: var(--error);
      font-size: 0.85rem;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      margin-bottom: 1.25rem;
    }

    .btn {
      width: 100%;
      padding: 0.85rem;
      background: var(--accent);
      color: white;
      font-family: 'DM Sans', system-ui, sans-serif;
      font-size: 0.95rem;
      font-weight: 500;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      margin-top: 0.5rem;
      transition: opacity 150ms, transform 100ms;
    }

    .btn:hover { opacity: 0.9; }
    .btn:active { transform: scale(0.99); }

    .footer-text {
      text-align: center;
      font-size: 0.82rem;
      color: var(--ink-3);
      margin-top: 1.5rem;
    }

    .footer-text a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }

    @media (max-width: 680px) {
      body { grid-template-columns: 1fr; }
      .left-panel { display: none; }
    }

    .demo-divider {
  display: flex; align-items: center; gap: 0.75rem;
  margin: 1.25rem 0 1rem;
  color: var(--ink-3); font-size: 0.8rem;
}
.demo-divider::before,
.demo-divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

.btn-demo {
  display: block; width: 100%; padding: 0.8rem;
  background: white; color: var(--ink);
  font-family: 'DM Sans', system-ui, sans-serif;
  font-size: 0.9rem; font-weight: 500;
  border: 1.5px solid var(--border); border-radius: var(--radius);
  text-align: center; text-decoration: none;
  transition: border-color 150ms, background 150ms;
}
.btn-demo:hover { border-color: var(--ink); background: var(--paper); }

  </style>
</head>
<body>

<!-- LEFT -->
<div class="left-panel">
  <div class="left-logo">
    <div class="left-logo-dot"></div>
    <span class="left-logo-name">StudyPlanner</span>
  </div>

  <div>
    <h1 class="left-headline">
      Study smarter.<br>
      Not <em>harder.</em>
    </h1>
  </div>

  <div class="features">
    <div class="feature">
      <div class="feature-dot"></div>
      <div class="feature-text">
        <strong>AI-powered schedules</strong>
        Built around your exams, your availability, and your difficulty level.
      </div>
    </div>
    <div class="feature">
      <div class="feature-dot"></div>
      <div class="feature-text">
        <strong>Adaptive rescheduling</strong>
        Miss a session? The plan adjusts automatically.
      </div>
    </div>
    <div class="feature">
      <div class="feature-dot"></div>
      <div class="feature-text">
        <strong>Progress tracking</strong>
        See exactly how prepared you are for each exam.
      </div>
    </div>
  </div>

  <p class="left-footer">Built for students who take their results seriously.</p>
</div>

<!-- RIGHT -->
<div class="right-panel">
  <div class="form-wrap">

    <h2 class="form-title">
      <?= $mode === 'login' ? 'Welcome back.' : 'Get started.' ?>
    </h2>
    <p class="form-sub">
      <?= $mode === 'login'
        ? 'Sign in to your study planner.'
        : 'Create your free account.' ?>
    </p>

    <div class="tabs">
      <a href="?mode=login"
         class="tab <?= $mode === 'login' ? 'active' : '' ?>">Sign in</a>
      <a href="?mode=register"
         class="tab <?= $mode === 'register' ? 'active' : '' ?>">Create account</a>
    </div>

    <?php if ($error): ?>
      <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'register'): ?>
      <form method="POST" action="/auth/">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name"
                 placeholder="Naol Shimelis" required autofocus>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="At least 8 characters" required>
        </div>
        <button type="submit" class="btn">Create account →</button>
      </form>
      <p class="footer-text">
  No account yet? <a href="?mode=register">Create one</a>
</p>

<div class="demo-divider">
  <span>or</span>
</div>

<a href="/auth/demo.php" class="btn-demo">
  Try the demo →
</a>

    <?php else: ?>
      <form method="POST" action="/auth/">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Your password" required>
        </div>
        <button type="submit" class="btn">Sign in →</button>
      </form>
      <p class="footer-text">
        No account yet? <a href="?mode=register">Create one</a>
      </p>
    <?php endif; ?>

  </div>
</div>

</body>
</html>