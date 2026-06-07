<?php

// Bootstrap
$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/controllers/AuthController.php';

startSession();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
}
// Already logged in — send to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

$error  = '';
$mode   = $_GET['mode'] ?? 'login'; // 'login' or 'register'

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $result = AuthController::handleRegister();
        if (!empty($result['success'])) {
            header('Location: /dashboard/');
            exit;
        }
        $error = $result['error'] ?? 'Something went wrong.';
        $mode  = 'register';
    }

    if ($action === 'login') {
        $result = AuthController::handleLogin();
        if (!empty($result['success'])) {
            header('Location: /dashboard/');
            exit;
        }
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
      --radius: 10px;
    }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      -webkit-font-smoothing: antialiased;
    }

    .card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      width: 100%;
      max-width: 420px;
    }

    .logo {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .logo-dot {
      width: 8px; height: 8px;
      background: var(--accent);
      border-radius: 50%;
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
      padding: 0.6rem;
      text-align: center;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--ink-3);
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      transition: all 150ms ease;
    }

    .tab.active {
      background: white;
      color: var(--ink);
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    /* Form */
    .form-group { margin-bottom: 1.1rem; }

    label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--ink-2);
      margin-bottom: 0.4rem;
      letter-spacing: 0.02em;
    }

    input {
      width: 100%;
      padding: 0.7rem 0.9rem;
      font-size: 0.95rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--paper);
      color: var(--ink);
      outline: none;
      transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59,110,245,0.1);
      background: white;
    }

    .error-box {
      background: #fdf2f2;
      border: 1px solid #f5c6c6;
      color: var(--error);
      font-size: 0.85rem;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn {
      width: 100%;
      padding: 0.8rem;
      background: var(--accent);
      color: white;
      font-size: 0.95rem;
      font-weight: 500;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      margin-top: 0.5rem;
      transition: opacity 150ms ease, transform 100ms ease;
    }

    .btn:hover { opacity: 0.92; }
    .btn:active { transform: scale(0.99); }

    .footer-text {
      text-align: center;
      font-size: 0.8rem;
      color: var(--ink-3);
      margin-top: 1.5rem;
    }

    .footer-text a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }
  </style>
</head>
<body>
<div class="card">

  <div class="logo">
    <div class="logo-dot"></div>
    StudyPlanner
  </div>

  <div class="tabs">
    <a href="?mode=login"
       class="tab <?= $mode === 'login' ? 'active' : '' ?>">
      Sign in
    </a>
    <a href="?mode=register"
       class="tab <?= $mode === 'register' ? 'active' : '' ?>">
      Create account
    </a>
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
      <button type="submit" class="btn">Create account</button>
    </form>
    <p class="footer-text">
      Already have an account? <a href="?mode=login">Sign in</a>
    </p>

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
      <button type="submit" class="btn">Sign in</button>
    </form>
    <p class="footer-text">
      No account yet? <a href="?mode=register">Create one</a>
    </p>
  <?php endif; ?>

</div>
</body>
</html>