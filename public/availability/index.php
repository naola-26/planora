<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/controllers/AvailabilityController.php';

requireLogin();
$user  = currentUser();
$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = AvailabilityController::handleSave($user['id']);
    if (!empty($result['error'])) {
        $error = $result['error'];
    } else {
        $saved = true;
    }
}

$availability = Availability::forUser($user['id']);

$days = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    0 => 'Sunday',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planora — Availability</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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
      --success: #2eab6f;
      --radius: 12px;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--paper); color: var(--ink);
      min-height: 100vh; -webkit-font-smoothing: antialiased;
    }

    nav {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1rem 2.5rem; background: white;
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
    }
    .nav-logo { display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 600; color: var(--ink); text-decoration: none; }
    .nav-logo-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; }
    .nav-links { display: flex; align-items: center; gap: 2rem; }
    .nav-links a { font-size: 0.85rem; color: var(--ink-3); text-decoration: none; transition: color 150ms; }
    .nav-links a:hover { color: var(--ink); }
    .nav-links a.active { color: var(--accent); font-weight: 500; }
    .nav-user { display: flex; align-items: center; gap: 10px; }
    .nav-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--accent); color: white; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; justify-content: center; }
    .nav-name { font-size: 0.82rem; font-weight: 500; color: var(--ink-2); }
    .nav-logout { font-size: 0.78rem; color: var(--ink-3); text-decoration: none; padding: 4px 10px; border: 1px solid var(--border); border-radius: 99px; transition: all 150ms; }
    .nav-logout:hover { border-color: var(--error); color: var(--error); }

    .container { max-width: 680px; margin: 0 auto; padding: 3rem 1.5rem; }

    .page-eyebrow { font-size: 0.72rem; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.6rem; }
    .page-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2rem; color: var(--ink); letter-spacing: -0.02em; margin-bottom: 0.4rem; }
    .page-sub { font-size: 0.9rem; color: var(--ink-3); margin-bottom: 2.5rem; }

    .error-box { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--error); font-size: 0.85rem; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; }
    .success-box { background: #f0faf5; border: 1px solid #a8e0c4; color: var(--success); font-size: 0.85rem; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; }

    .days-grid { display: flex; flex-direction: column; gap: 8px; margin-bottom: 1.5rem; }

    .day-card {
      background: white; border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1rem 1.25rem;
      transition: border-color 150ms, box-shadow 150ms;
    }
    .day-card.active { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,110,245,0.08); }

    .day-top { display: flex; align-items: center; gap: 1rem; }

    .toggle { position: relative; width: 36px; height: 20px; flex-shrink: 0; }
    .toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
    .toggle-track { position: absolute; inset: 0; background: var(--border); border-radius: 99px; cursor: pointer; transition: background 200ms; }
    .toggle input:checked + .toggle-track { background: var(--accent); }
    .toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: white; transition: transform 200ms; pointer-events: none; }
    .toggle input:checked ~ .toggle-thumb { transform: translateX(16px); }

    .day-name { font-size: 0.95rem; font-weight: 500; color: var(--ink); flex: 1; }
    .hours-display { font-size: 0.85rem; font-weight: 500; color: var(--accent); min-width: 55px; text-align: right; }

    .slider-row { display: none; margin-top: 0.875rem; padding-top: 0.875rem; border-top: 1px solid var(--paper-2); align-items: center; gap: 1rem; }
    .slider-row.visible { display: flex; }
    .slider-label { font-size: 0.75rem; color: var(--ink-3); white-space: nowrap; }
    input[type="range"] { flex: 1; accent-color: var(--accent); height: 4px; cursor: pointer; }

    .summary { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; font-size: 0.875rem; }
    .summary-label { color: var(--ink-3); }
    .summary-value { font-weight: 600; color: var(--ink); font-size: 1rem; }

    .btn-save { width: 100%; padding: 0.8rem; background: var(--accent); color: white; font-family: 'DM Sans', system-ui, sans-serif; font-size: 0.95rem; font-weight: 500; border: none; border-radius: var(--radius); cursor: pointer; transition: opacity 150ms; }
    .btn-save:hover { opacity: 0.9; }

    .next-step { display: flex; justify-content: flex-end; margin-top: 1.25rem; }
    .btn-next { display: inline-flex; align-items: center; gap: 6px; background: var(--ink); color: white; font-size: 0.85rem; font-weight: 500; padding: 0.7rem 1.4rem; border-radius: 10px; text-decoration: none; transition: background 150ms; }
    .btn-next:hover { background: var(--accent); }
  </style>
</head>
<body>

<nav>
  <a href="/dashboard/" class="nav-logo">
    <div class="nav-logo-dot"></div>
    Planora
  </a>
  <div class="nav-links">
    <a href="/subjects/">Subjects</a>
    <a href="/availability/" class="active">Availability</a>
    <a href="/dashboard/">Dashboard</a>
  </div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
    <a href="/auth/?action=logout" class="nav-logout">Log out</a>
  </div>
</nav>

<div class="container">

  <p class="page-eyebrow">Step 2 of 2</p>
  <h1 class="page-title">Your availability</h1>
  <p class="page-sub">Choose which days you can study and how many hours each day.</p>

  <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($saved): ?>
    <div class="success-box">✓ Availability saved successfully.</div>
  <?php endif; ?>

  <form method="POST" action="/availability/" id="avail-form">
    <div class="days-grid">
      <?php foreach ($days as $num => $label):
        $saved_hours = $availability[$num]['hours_available'] ?? 0;
        $is_active   = $saved_hours > 0;
        $hours       = $is_active ? $saved_hours : 2.0;
      ?>
        <div class="day-card <?= $is_active ? 'active' : '' ?>" id="card-<?= $num ?>">
          <div class="day-top">
            <label class="toggle">
              <input type="checkbox"
                     id="toggle-<?= $num ?>"
                     data-day="<?= $num ?>"
                     <?= $is_active ? 'checked' : '' ?>
                     onchange="toggleDay(<?= $num ?>, this.checked)">
              <div class="toggle-track"></div>
              <div class="toggle-thumb"></div>
            </label>
            <span class="day-name"><?= $label ?></span>
            <span class="hours-display" id="hours-label-<?= $num ?>">
              <?= $is_active ? $hours . ' hrs' : '—' ?>
            </span>
          </div>
          <div class="slider-row <?= $is_active ? 'visible' : '' ?>" id="slider-row-<?= $num ?>">
            <span class="slider-label">0.5</span>
            <input type="range"
                   name="days[<?= $num ?>]"
                   id="slider-<?= $num ?>"
                   min="0.5" max="8" step="0.5"
                   value="<?= $hours ?>"
                   oninput="updateHours(<?= $num ?>, this.value)"
                   <?= $is_active ? '' : 'disabled' ?>>
            <span class="slider-label">8</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="summary">
      <span class="summary-label">Total hours per week</span>
      <span class="summary-value" id="total-hours">0 hrs</span>
    </div>

    <button type="submit" class="btn-save">Save availability</button>
  </form>

  <?php if (!empty($availability)): ?>
    <div class="next-step">
      <a href="/dashboard/" class="btn-next">Generate my schedule →</a>
    </div>
  <?php endif; ?>

</div>

<script>
  function toggleDay(day, on) {
    const card   = document.getElementById('card-' + day);
    const row    = document.getElementById('slider-row-' + day);
    const slider = document.getElementById('slider-' + day);
    const label  = document.getElementById('hours-label-' + day);

    card.classList.toggle('active', on);
    row.classList.toggle('visible', on);
    slider.disabled = !on;

    if (on) {
      slider.setAttribute('name', 'days[' + day + ']');
      label.textContent = slider.value + ' hrs';
    } else {
      slider.removeAttribute('name');
      label.textContent = '—';
      slider.value = 2;
    }
    updateTotal();
  }

  function updateHours(day, val) {
    document.getElementById('hours-label-' + day).textContent = val + ' hrs';
    updateTotal();
  }

  function updateTotal() {
    let total = 0;
    document.querySelectorAll('input[type="range"]:not([disabled])').forEach(s => {
      total += parseFloat(s.value);
    });
    document.getElementById('total-hours').textContent = total.toFixed(1) + ' hrs';
  }

  updateTotal();
</script>

</body>
</html>