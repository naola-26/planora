<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/models/Schedule.php';
require_once __DIR__ . '/../../src/models/Subject.php';
require_once __DIR__ . '/../../src/models/Availability.php';

requireLogin();
$user = currentUser();

$schedules    = Schedule::forUser($user['id']);
$subjects     = Subject::allForUser($user['id']);
$availability = Availability::forUser($user['id']);
$stats        = Schedule::stats($user['id']);

$successMsg = $_SESSION['schedule_success'] ?? '';
$errorMsg   = $_SESSION['schedule_error']   ?? '';
unset($_SESSION['schedule_success'], $_SESSION['schedule_error']);

// Group sessions by date
$byDate = [];
foreach ($schedules as $session) {
    $byDate[$session['scheduled_date']][] = $session;
}
ksort($byDate);

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StudyPlanner — Dashboard</title>
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
      --radius: 10px;
    }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    nav {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1rem 2rem; background: white;
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

    .container { max-width: 780px; margin: 0 auto; padding: 2.5rem 1.5rem; }

    /* STATS */
    .stats-grid {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 10px; margin-bottom: 2rem;
    }
    .stat-card {
      background: white; border: 1px solid var(--border);
      border-radius: 12px; padding: 1rem 1.25rem;
    }
    .stat-num { font-size: 1.75rem; font-weight: 600; color: var(--ink); line-height: 1; margin-bottom: 4px; }
    .stat-label { font-size: 0.75rem; color: var(--ink-3); letter-spacing: 0.04em; }

    /* MESSAGES */
    .msg-success {
      background: #f0faf5; border: 1px solid #a8e0c4;
      color: var(--success); font-size: 0.85rem;
      padding: 0.75rem 1rem; border-radius: var(--radius);
      margin-bottom: 1.5rem;
    }
    .msg-error {
      background: #fdf2f2; border: 1px solid #f5c6c6;
      color: var(--error); font-size: 0.85rem;
      padding: 0.75rem 1rem; border-radius: var(--radius);
      margin-bottom: 1.5rem;
    }

    /* GENERATE BTN */
    .generate-bar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    .section-title { font-size: 1rem; font-weight: 600; color: var(--ink); }

    .btn-generate {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--accent); color: white;
      font-size: 0.85rem; font-weight: 500;
      padding: 0.6rem 1.2rem; border-radius: var(--radius);
      border: none; cursor: pointer;
      transition: opacity 150ms;
    }
    .btn-generate:hover { opacity: 0.9; }

    /* EMPTY */
    .empty {
      text-align: center; padding: 3rem 1rem;
      color: var(--ink-3); font-size: 0.9rem;
      border: 1.5px dashed var(--border);
      border-radius: 12px;
    }
    .empty-icon { font-size: 2rem; margin-bottom: 0.75rem; }

    /* SCHEDULE */
    .date-group { margin-bottom: 1.75rem; }

    .date-heading {
      font-size: 0.78rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--ink-3); margin-bottom: 8px;
      display: flex; align-items: center; gap: 8px;
    }
    .date-heading.today-label { color: var(--accent); }
    .today-badge {
      font-size: 0.68rem; background: var(--accent);
      color: white; padding: 1px 7px; border-radius: 99px;
      font-weight: 500; letter-spacing: 0.05em;
    }

    .session-card {
      background: white; border: 1px solid var(--border);
      border-radius: 10px; padding: 0.9rem 1.1rem;
      display: flex; align-items: center; gap: 1rem;
      margin-bottom: 6px;
      transition: border-color 150ms;
    }
    .session-card.completed { opacity: 0.55; }
    .session-card.missed { border-color: #f5c6c6; }

    .session-color {
      width: 3px; height: 36px; border-radius: 99px; flex-shrink: 0;
    }

    .session-info { flex: 1; min-width: 0; }
    .session-name {
      font-size: 0.9rem; font-weight: 500; color: var(--ink);
      margin-bottom: 2px;
    }
    .session-note {
      font-size: 0.78rem; color: var(--ink-3);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .session-duration {
      font-size: 0.8rem; font-weight: 500;
      color: var(--ink-2); white-space: nowrap;
    }

    .session-actions { display: flex; gap: 6px; }

    .btn-status {
      font-size: 0.72rem; font-weight: 500;
      padding: 4px 10px; border-radius: 99px;
      border: 1px solid var(--border);
      background: white; cursor: pointer; color: var(--ink-2);
      transition: all 150ms;
    }
    .btn-done:hover  { background: #f0faf5; border-color: #a8e0c4; color: var(--success); }
    .btn-missed:hover { background: #fdf2f2; border-color: #f5c6c6; color: var(--error); }

    .status-badge {
      font-size: 0.7rem; font-weight: 500;
      padding: 3px 8px; border-radius: 99px;
    }
    .badge-completed { background: #f0faf5; color: var(--success); }
    .badge-missed    { background: #fdf2f2; color: var(--error); }
  </style>
</head>
<body>

<nav>
  <a href="/dashboard/" class="nav-logo">
    <div class="nav-logo-dot"></div>
    StudyPlanner
  </a>
  <div class="nav-links">
    <a href="/subjects/">Subjects</a>
    <a href="/availability/">Availability</a>
    <a href="/dashboard/" class="active">Dashboard</a>
  </div>
  <span class="nav-user"><?= htmlspecialchars($user['name']) ?> &nbsp;·&nbsp;
    <a href="/auth/?action=logout" style="color:var(--ink-3);text-decoration:none;">Log out</a>
  </span>
</nav>

<div class="container">

  <?php if ($successMsg): ?>
    <div class="msg-success">✓ <?= htmlspecialchars($successMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg-error">⚠ <?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <p class="stat-num"><?= (int)($stats['total'] ?? 0) ?></p>
      <p class="stat-label">Total sessions</p>
    </div>
    <div class="stat-card">
      <p class="stat-num"><?= (int)($stats['completed'] ?? 0) ?></p>
      <p class="stat-label">Completed</p>
    </div>
    <div class="stat-card">
      <p class="stat-num"><?= (int)($stats['missed'] ?? 0) ?></p>
      <p class="stat-label">Missed</p>
    </div>
    <div class="stat-card">
      <p class="stat-num"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></p>
      <p class="stat-label">Total hours</p>
    </div>
  </div>

  <!-- SCHEDULE -->
  <div class="generate-bar">
    <p class="section-title">Study schedule</p>
    <form method="POST" action="/schedule/generate.php">
      <button type="submit" class="btn-generate">
        ✦ <?= empty($schedules) ? 'Generate schedule' : 'Regenerate' ?>
      </button>
    </form>
  </div>

  <?php if (empty($schedules)): ?>
    <div class="empty">
      <div class="empty-icon">🗓</div>
      <p>No schedule yet.<br>
      <?php if (empty($subjects)): ?>
        <a href="/subjects/" style="color:var(--accent)">Add your subjects</a> to get started.
      <?php elseif (empty($availability)): ?>
        <a href="/availability/" style="color:var(--accent)">Set your availability</a> to get started.
      <?php else: ?>
        Hit <strong>Generate schedule</strong> and let the AI build your plan.
      <?php endif; ?>
      </p>
    </div>

  <?php else: ?>
    <?php foreach ($byDate as $date => $sessions): ?>
      <?php
        $isToday  = $date === $today;
        $isPast   = $date < $today;
        $label    = $isToday
          ? 'Today'
          : date('l, M j', strtotime($date));
      ?>
      <div class="date-group">
        <div class="date-heading <?= $isToday ? 'today-label' : '' ?>">
          <?= $label ?>
          <?php if ($isToday): ?><span class="today-badge">Today</span><?php endif; ?>
        </div>

        <?php foreach ($sessions as $session): ?>
          <div class="session-card <?= $session['status'] !== 'pending' ? $session['status'] : '' ?>">
            <div class="session-color"
                 style="background:<?= htmlspecialchars($session['color']) ?>"></div>
            <div class="session-info">
              <p class="session-name"><?= htmlspecialchars($session['subject_name']) ?></p>
              <?php if ($session['note']): ?>
                <p class="session-note"><?= htmlspecialchars($session['note']) ?></p>
              <?php endif; ?>
            </div>
            <span class="session-duration">
              <?= $session['duration_hours'] ?> hr<?= $session['duration_hours'] != 1 ? 's' : '' ?>
            </span>

            <?php if ($session['status'] === 'pending'): ?>
              <div class="session-actions">
                <form method="POST" action="/schedule/status.php" style="margin:0">
                  <input type="hidden" name="id" value="<?= $session['id'] ?>">
                  <input type="hidden" name="status" value="completed">
                  <button type="submit" class="btn-status btn-done">✓ Done</button>
                </form>
                <form method="POST" action="/schedule/status.php" style="margin:0">
                  <input type="hidden" name="id" value="<?= $session['id'] ?>">
                  <input type="hidden" name="status" value="missed">
                  <button type="submit" class="btn-status btn-missed">✕ Missed</button>
                </form>
              </div>
            <?php else: ?>
              <span class="status-badge badge-<?= $session['status'] ?>">
                <?= ucfirst($session['status']) ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>