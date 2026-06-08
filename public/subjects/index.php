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
$today    = date('Y-m-d');
$minDate  = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StudyPlanner — Subjects</title>
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
      -webkit-font-smoothing: antialiased;
    }

    /* NAV */
    nav {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1rem 2rem;
      background: white;
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
    }
    .nav-logo { font-size: 1rem; font-weight: 600; color: var(--ink); text-decoration: none; display: flex; align-items: center; gap: 8px; }
    .nav-logo-dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; }
    .nav-links { display: flex; align-items: center; gap: 1.5rem; }
    .nav-links a { font-size: 0.85rem; color: var(--ink-2); text-decoration: none; }
    .nav-links a:hover { color: var(--ink); }
    .nav-links a.active { color: var(--accent); font-weight: 500; }
    .nav-user { font-size: 0.8rem; color: var(--ink-3); }

    /* LAYOUT */
    .container { max-width: 720px; margin: 0 auto; padding: 2.5rem 1.5rem; }

    .page-header { margin-bottom: 2rem; }
    .page-title { font-size: 1.5rem; font-weight: 600; color: var(--ink); margin-bottom: 0.35rem; }
    .page-sub { font-size: 0.875rem; color: var(--ink-3); }

    /* ERROR */
    .error-box {
      background: #fdf2f2; border: 1px solid #f5c6c6;
      color: var(--error); font-size: 0.85rem;
      padding: 0.75rem 1rem; border-radius: var(--radius);
      margin-bottom: 1.5rem;
    }

    /* SUBJECT LIST */
    .subjects-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 2rem; }

    .subject-card {
      background: white; border: 1px solid var(--border);
      border-radius: 12px; padding: 1rem 1.25rem;
      display: flex; align-items: center; gap: 1rem;
    }

    .subject-color {
      width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }

    .subject-info { flex: 1; min-width: 0; }
    .subject-name { font-size: 0.95rem; font-weight: 500; color: var(--ink); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .subject-meta { font-size: 0.78rem; color: var(--ink-3); display: flex; gap: 1rem; }

    .subject-difficulty { display: flex; gap: 3px; }
    .dot-filled { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
    .dot-empty  { width: 7px; height: 7px; border-radius: 50%; background: var(--border); }

    .subject-days {
      font-size: 0.75rem; font-weight: 500;
      padding: 2px 8px; border-radius: 99px;
      background: var(--paper-2); color: var(--ink-2);
    }
    .subject-days.urgent { background: #fdf2f2; color: var(--error); }

    .btn-delete {
      background: none; border: none; cursor: pointer;
      color: var(--ink-3); font-size: 1.1rem; padding: 4px;
      border-radius: 6px; transition: color 150ms, background 150ms;
      line-height: 1;
    }
    .btn-delete:hover { color: var(--error); background: #fdf2f2; }

    /* EMPTY STATE */
    .empty {
      text-align: center; padding: 3rem 1rem;
      color: var(--ink-3); font-size: 0.9rem;
      border: 1.5px dashed var(--border);
      border-radius: 12px; margin-bottom: 2rem;
    }
    .empty-icon { font-size: 2rem; margin-bottom: 0.75rem; }
    .empty p { line-height: 1.6; }

    /* ADD FORM */
    .form-card {
      background: white; border: 1px solid var(--border);
      border-radius: 12px; padding: 1.5rem;
    }
    .form-card-title {
      font-size: 0.875rem; font-weight: 600;
      color: var(--ink); margin-bottom: 1.25rem;
    }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-group.full { grid-column: 1 / -1; }

    label { font-size: 0.78rem; font-weight: 500; color: var(--ink-2); letter-spacing: 0.02em; }

    input[type="text"],
    input[type="date"] {
      padding: 0.65rem 0.85rem;
      font-size: 0.9rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--paper);
      color: var(--ink);
      outline: none;
      width: 100%;
      transition: border-color 150ms, box-shadow 150ms;
    }
    input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59,110,245,0.1);
      background: white;
    }

    /* DIFFICULTY PICKER */
    .difficulty-picker { display: flex; gap: 8px; align-items: center; padding: 0.5rem 0; }
    .diff-btn {
      width: 32px; height: 32px; border-radius: 50%;
      border: 2px solid var(--border);
      background: white; cursor: pointer;
      font-size: 0.78rem; font-weight: 600;
      color: var(--ink-3);
      transition: all 150ms ease;
      display: flex; align-items: center; justify-content: center;
    }
    .diff-btn:hover { border-color: var(--accent); color: var(--accent); }
    .diff-btn.selected { background: var(--accent); border-color: var(--accent); color: white; }
    .diff-label { font-size: 0.75rem; color: var(--ink-3); margin-left: 4px; }
    input[name="difficulty"] { display: none; }

    .btn-submit {
      width: 100%; padding: 0.75rem;
      background: var(--accent); color: white;
      font-size: 0.9rem; font-weight: 500;
      border: none; border-radius: var(--radius);
      cursor: pointer; margin-top: 0.5rem;
      transition: opacity 150ms, transform 100ms;
    }
    .btn-submit:hover { opacity: 0.9; }
    .btn-submit:active { transform: scale(0.99); }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

    .count-badge {
      font-size: 0.78rem; color: var(--ink-3);
      margin-bottom: 1rem;
      display: flex; align-items: center; justify-content: space-between;
    }

    .next-link {
      display: flex; align-items: center; justify-content: flex-end;
      margin-top: 1.5rem;
    }
    .btn-next {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--ink); color: white;
      font-size: 0.85rem; font-weight: 500;
      padding: 0.7rem 1.4rem; border-radius: var(--radius);
      text-decoration: none;
      transition: background 150ms;
    }
    .btn-next:hover { background: var(--accent); }
  </style>
</head>
<body>

<nav>
  <a href="/dashboard/" class="nav-logo">
    <div class="nav-logo-dot"></div>
    StudyPlanner
  </a>
  <div class="nav-links">
    <a href="/subjects/" class="active">Subjects</a>
    <a href="/availability/">Availability</a>
    <a href="/dashboard/">Dashboard</a>
  </div>
  <span class="nav-user"><?= htmlspecialchars($user['name']) ?></span>
</nav>

<div class="container">

  <div class="page-header">
    <h1 class="page-title">Your subjects</h1>
    <p class="page-sub">Add the subjects you're studying and when each exam is.</p>
  </div>

  <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- SUBJECT LIST -->
  <?php if (empty($subjects)): ?>
    <div class="empty">
      <div class="empty-icon">📚</div>
      <p>No subjects yet.<br>Add your first one below.</p>
    </div>
  <?php else: ?>
    <div class="count-badge">
      <span><?= $count ?> of 10 subjects</span>
      <?php if ($count >= 2): ?>
        <a href="/availability/" class="btn-next">
          Set availability →
        </a>
      <?php endif; ?>
    </div>
    <div class="subjects-list">
      <?php foreach ($subjects as $s):
        $daysLeft = (int) ceil((strtotime($s['exam_date']) - time()) / 86400);
        $urgent   = $daysLeft <= 7;
      ?>
        <div class="subject-card">
          <div class="subject-color" style="background:<?= htmlspecialchars($s['color']) ?>"></div>
          <div class="subject-info">
            <p class="subject-name"><?= htmlspecialchars($s['name']) ?></p>
            <div class="subject-meta">
              <div class="subject-difficulty">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <div class="<?= $i <= $s['difficulty'] ? 'dot-filled' : 'dot-empty' ?>"></div>
                <?php endfor; ?>
              </div>
              <span>Exam <?= date('M j, Y', strtotime($s['exam_date'])) ?></span>
            </div>
          </div>
          <span class="subject-days <?= $urgent ? 'urgent' : '' ?>">
            <?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> left
          </span>
          <form method="POST" action="/subjects/" style="margin:0">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn-delete"
                    onclick="return confirm('Remove <?= htmlspecialchars($s['name']) ?>?')"
                    title="Remove subject">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ADD FORM -->
  <?php if ($count < 10): ?>
    <div class="form-card">
      <p class="form-card-title">Add a subject</p>
      <form method="POST" action="/subjects/" id="subject-form">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group full">
            <label for="name">Subject name</label>
            <input type="text" id="name" name="name"
                   placeholder="e.g. Database Systems" required maxlength="100">
          </div>
          <div class="form-group">
            <label>Difficulty</label>
            <div class="difficulty-picker">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="diff-btn" data-value="<?= $i ?>"><?= $i ?></button>
              <?php endfor; ?>
              <span class="diff-label" id="diff-label">Select</span>
            </div>
            <input type="hidden" name="difficulty" id="difficulty-input" required>
          </div>
          <div class="form-group">
            <label for="exam_date">Exam date</label>
            <input type="date" id="exam_date" name="exam_date"
                   min="<?= $minDate ?>" required>
          </div>
        </div>
        <button type="submit" class="btn-submit" id="submit-btn" disabled>
          Add subject
        </button>
      </form>
    </div>
  <?php else: ?>
    <div class="empty">
      <p>You've reached the maximum of 10 subjects.</p>
    </div>
  <?php endif; ?>

</div>

<script>
  const diffBtns  = document.querySelectorAll('.diff-btn');
  const diffInput = document.getElementById('difficulty-input');
  const diffLabel = document.getElementById('diff-label');
  const submitBtn = document.getElementById('submit-btn');
  const nameInput = document.getElementById('name');
  const dateInput = document.getElementById('exam_date');

  const labels = ['', 'Very easy', 'Easy', 'Medium', 'Hard', 'Very hard'];

  let diffSelected = false;

  diffBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      diffBtns.forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      diffInput.value = btn.dataset.value;
      diffLabel.textContent = labels[btn.dataset.value];
      diffSelected = true;
      checkForm();
    });
  });

  function checkForm() {
    const ready = nameInput.value.trim() && dateInput.value && diffSelected;
    submitBtn.disabled = !ready;
  }

  nameInput.addEventListener('input', checkForm);
  dateInput.addEventListener('change', checkForm);
</script>

</body>
</html>