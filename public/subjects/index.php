<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/controllers/SubjectController.php';

requireLogin();
$user  = currentUser();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $result = SubjectController::handleCreate($user['id']);
        if (!empty($result['error'])) $error = $result['error'];
    }
    if ($action === 'delete') {
        SubjectController::handleDelete($user['id']);
    }
    if (empty($error)) {
        header('Location: /subjects/');
        exit;
    }
}

$subjects = Subject::allForUser($user['id']);
$count    = count($subjects);
$minDate  = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planora — Subjects</title>
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
    .nav-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--accent); color: white; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .nav-name { font-size: 0.82rem; font-weight: 500; color: var(--ink-2); }
    .nav-logout { font-size: 0.78rem; color: var(--ink-3); text-decoration: none; padding: 4px 10px; border: 1px solid var(--border); border-radius: 99px; transition: all 150ms; }
    .nav-logout:hover { border-color: var(--error); color: var(--error); }

    .page { max-width: 680px; margin: 0 auto; padding: 3rem 1.5rem; }

    .page-eyebrow { font-size: 0.72rem; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.6rem; }
    .page-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2rem; color: var(--ink); letter-spacing: -0.02em; margin-bottom: 0.4rem; }
    .page-sub { font-size: 0.9rem; color: var(--ink-3); margin-bottom: 2.5rem; }

    .error-box { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--error); font-size: 0.85rem; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; }

    .progress-row { display: flex; align-items: center; gap: 1rem; background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; }
    .progress-label { font-size: 0.8rem; color: var(--ink-3); white-space: nowrap; }
    .bar-track { flex: 1; height: 5px; background: var(--paper-2); border-radius: 99px; overflow: hidden; }
    .bar-fill { height: 100%; background: var(--accent); border-radius: 99px; }
    .progress-count { font-size: 0.8rem; font-weight: 500; color: var(--ink); white-space: nowrap; }

    .subject-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; background: white; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
    .subject-table thead tr { border-bottom: 1px solid var(--border); }
    .subject-table thead th { padding: 0.65rem 1rem; font-size: 0.72rem; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); text-align: left; }
    .subject-table tbody tr { border-bottom: 1px solid var(--paper-2); transition: background 150ms; }
    .subject-table tbody tr:last-child { border-bottom: none; }
    .subject-table tbody tr:hover { background: var(--paper); }
    .subject-table td { padding: 0.85rem 1rem; vertical-align: middle; }

    .td-name { font-size: 0.9rem; font-weight: 500; color: var(--ink); }
    .td-name-wrap { display: flex; align-items: center; gap: 10px; }
    .color-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
    .diff-pips { display: inline-flex; gap: 3px; vertical-align: middle; }
    .pip { width: 7px; height: 7px; border-radius: 50%; display: inline-block; background: var(--paper-2); }
    .pip.on { background: var(--accent); }
    .td-exam { font-size: 0.82rem; color: var(--ink-2); white-space: nowrap; }
    .days-badge { font-size: 0.72rem; font-weight: 600; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
    .days-badge.urgent  { background: #fdf2f2; color: var(--error); }
    .days-badge.soon    { background: #fef9ec; color: #e67e22; }
    .days-badge.relaxed { background: var(--paper-2); color: var(--ink-3); }
    .td-action { text-align: right; }
    .btn-delete { background: none; border: none; cursor: pointer; color: var(--ink-3); font-size: 0.85rem; padding: 5px 8px; border-radius: 6px; transition: color 150ms, background 150ms; }
    .btn-delete:hover { color: var(--error); background: #fdf2f2; }

    .empty { text-align: center; padding: 3rem 1rem; border: 1.5px dashed var(--border); border-radius: var(--radius); margin-bottom: 2rem; }
    .empty-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    .empty p { font-size: 0.9rem; color: var(--ink-3); }

    .next-row { display: flex; justify-content: flex-end; margin-bottom: 2rem; }
    .btn-next { display: inline-flex; align-items: center; gap: 6px; background: var(--ink); color: white; font-size: 0.85rem; font-weight: 500; padding: 0.7rem 1.4rem; border-radius: 10px; text-decoration: none; transition: background 150ms; }
    .btn-next:hover { background: var(--accent); }

    .form-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.75rem; }
    .form-card-title { font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 1.5rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-group.full { grid-column: 1 / -1; }

    label { font-size: 0.78rem; font-weight: 500; color: var(--ink-2); }

    .form-input {
      padding: 0.7rem 0.9rem; font-size: 0.9rem;
      font-family: 'DM Sans', system-ui, sans-serif;
      border: 1px solid var(--border); border-radius: 10px;
      background: var(--paper); color: var(--ink);
      outline: none; width: 100%;
      transition: border-color 150ms, box-shadow 150ms, background 150ms;
    }
    .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,110,245,0.1); background: white; }

    .diff-slider-wrap { padding: 0.4rem 0; }
    input[type="range"] { width: 100%; accent-color: var(--accent); cursor: pointer; }
    .diff-labels { display: flex; justify-content: space-between; margin-top: 4px; }
    .diff-labels span { font-size: 0.7rem; color: var(--ink-3); }
    .diff-value { font-size: 0.78rem; color: var(--accent); font-weight: 500; margin-top: 4px; }

    .btn-submit {
      width: 100%; padding: 0.8rem; background: var(--accent); color: white;
      font-family: 'DM Sans', system-ui, sans-serif; font-size: 0.9rem; font-weight: 500;
      border: none; border-radius: 10px; cursor: pointer; margin-top: 0.5rem;
      transition: opacity 150ms;
    }
    .btn-submit:hover { opacity: 0.9; }
    .btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }
  </style>
</head>
<body>

<nav>
  <a href="/dashboard/" class="nav-logo">
    <div class="nav-logo-dot"></div>
    Planora
  </a>
  <div class="nav-links">
    <a href="/subjects/" class="active">Subjects</a>
    <a href="/availability/">Availability</a>
    <a href="/dashboard/">Dashboard</a>
  </div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
    <a href="/auth/?action=logout" class="nav-logout">Log out</a>
  </div>
</nav>

<div class="page">

  <p class="page-eyebrow">Step 1 of 2</p>
  <h1 class="page-title">Your subjects</h1>
  <p class="page-sub">Add what you're studying and when each exam is.</p>

  <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="progress-row">
    <span class="progress-label">Subjects added</span>
    <div class="bar-track">
      <div class="bar-fill" style="width:<?= ($count / 10) * 100 ?>%"></div>
    </div>
    <span class="progress-count"><?= $count ?> / 10</span>
  </div>

  <?php if (empty($subjects)): ?>
    <div class="empty">
      <div class="empty-icon">📚</div>
      <p>No subjects yet. Add your first one below.</p>
    </div>
  <?php else: ?>
    <table class="subject-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th>Difficulty</th>
          <th>Exam date</th>
          <th>Time left</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $s):
          $daysLeft = (int) ceil((strtotime($s['exam_date']) - time()) / 86400);
          $urgency  = $daysLeft <= 3 ? 'urgent' : ($daysLeft <= 7 ? 'soon' : 'relaxed');
          $label    = $daysLeft <= 0 ? 'Passed' : ($daysLeft === 1 ? 'Tomorrow' : "{$daysLeft}d left");
        ?>
          <tr>
            <td class="td-name">
              <div class="td-name-wrap">
                <span class="color-dot" style="background:<?= htmlspecialchars($s['color']) ?>"></span>
                <?= htmlspecialchars($s['name']) ?>
              </div>
            </td>
            <td>
              <span class="diff-pips">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="pip <?= $i <= $s['difficulty'] ? 'on' : '' ?>"></span>
                <?php endfor; ?>
              </span>
            </td>
            <td class="td-exam"><?= date('M j, Y', strtotime($s['exam_date'])) ?></td>
            <td><span class="days-badge <?= $urgency ?>"><?= $label ?></span></td>
            <td class="td-action">
              <form method="POST" action="/subjects/">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn-delete"
                        onclick="return confirm('Remove <?= htmlspecialchars(addslashes($s['name'])) ?>?')">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="next-row">
      <a href="/availability/" class="btn-next">Set availability →</a>
    </div>
  <?php endif; ?>

  <?php if ($count < 10): ?>
    <div class="form-card">
      <p class="form-card-title">Add a subject</p>
      <form method="POST" action="/subjects/">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group full">
            <label for="name">Subject name</label>
            <input class="form-input" type="text" id="name" name="name"
                  placeholder="e.g. Database Systems" required maxlength="100">
          </div>
          <div class="form-group full">
            <label for="topics">
              Key topics <span style="color:var(--ink-3);font-weight:400;">(optional, but recommended)</span>
            </label>
            <input class="form-input" type="text" id="topics" name="topics"
                  placeholder="e.g. normalization, indexing, SQL joins, transactions"
                  maxlength="500">
          </div>
          <div class="form-group">
            <label>Difficulty — <span id="diff-value-label" style="color:var(--accent);font-weight:500;">Medium</span></label>
            <div class="diff-slider-wrap">
              <input type="range" id="diff-slider" min="1" max="5" step="1" value="3">
              <div class="diff-labels">
                <span>Very easy</span>
                <span>Very hard</span>
              </div>
            </div>
            <input type="hidden" name="difficulty" id="difficulty-input" value="3">
          </div>
          <div class="form-group">
            <label for="exam_date">Exam date</label>
            <input class="form-input" type="date" id="exam_date" name="exam_date"
                  min="<?= $minDate ?>" required>
          </div>
        </div>  
        <button type="submit" class="btn-submit" id="submit-btn" disabled>
          Add subject →
        </button>
      </form>
    </div>
  <?php endif; ?>

</div>

<script>
  const slider    = document.getElementById('diff-slider');
  const diffInput = document.getElementById('difficulty-input');
  const diffLabel = document.getElementById('diff-value-label');
  const submit    = document.getElementById('submit-btn');
  const name      = document.getElementById('name');
  const date      = document.getElementById('exam_date');
  const hints     = ['', 'Very easy', 'Easy', 'Medium', 'Hard', 'Very hard'];

  diffInput.value = slider.value;
  diffLabel.textContent = hints[slider.value];

  slider.addEventListener('input', () => {
    diffInput.value = slider.value;
    diffLabel.textContent = hints[slider.value];
  });

  function check() {
    submit.disabled = !(name.value.trim() && date.value.trim());
  }

  name.addEventListener('input', check);
  date.addEventListener('input', check);
</script>

</body>
</html