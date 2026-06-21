<?php
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    foreach ($env as $key => $value) putenv("$key=$value");
}

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

$byDate = [];
foreach ($schedules as $session) {
    $byDate[$session['scheduled_date']][] = $session;
}
ksort($byDate);

$subjectStats = [];
foreach ($subjects as $sub) {
    $subjectStats[$sub['id']] = [
        'name'      => $sub['name'],
        'color'     => $sub['color'],
        'exam_date' => $sub['exam_date'],
        'difficulty'=> $sub['difficulty'],
        'total'     => 0,
        'completed' => 0,
        'missed'    => 0,
        'hours'     => 0,
    ];
}
foreach ($schedules as $session) {
    $sid = $session['subject_id'];
    if (!isset($subjectStats[$sid])) continue;
    $subjectStats[$sid]['total']++;
    $subjectStats[$sid]['hours'] += $session['duration_hours'];
    if ($session['status'] === 'completed') $subjectStats[$sid]['completed']++;
    if ($session['status'] === 'missed')    $subjectStats[$sid]['missed']++;
}

$today     = date('Y-m-d');
$total     = (int)($stats['total']      ?? 0);
$completed = (int)($stats['completed'] ?? 0);
$missed    = (int)($stats['missed']    ?? 0);
$pending   = (int)($stats['pending']   ?? 0);
$hours     = (float)($stats['total_hours'] ?? 0);
$rate      = $total > 0 ? round(($completed / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planora — Dashboard</title>
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
      --warning: #e67e22;
      --radius: 12px;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    nav {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1rem 2.5rem; background: white;
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
    }
    .nav-logo { display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 600; color: var(--ink); text-decoration: none; flex-shrink: 0; }
    .nav-logo-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; }
    .nav-links { display: flex; align-items: center; gap: 2rem; }
    .nav-links a { font-size: 0.85rem; color: var(--ink-3); text-decoration: none; transition: color 150ms; }
    .nav-links a:hover { color: var(--ink); }
    .nav-links a.active { color: var(--accent); font-weight: 500; }

    .nav-user { display: flex; align-items: center; gap: 10px; flex-shrink: 0; white-space: nowrap; }
    .nav-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: var(--accent); color: white;
      font-size: 0.75rem; font-weight: 600;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; line-height: 1;
      font-family: 'DM Sans', system-ui, sans-serif;
    }
    .nav-name { font-size: 0.82rem; font-weight: 500; color: var(--ink-2); white-space: nowrap; }
    .nav-logout {
      font-size: 0.78rem; color: var(--ink-3); text-decoration: none;
      padding: 4px 10px; border: 1px solid var(--border); border-radius: 99px;
      transition: all 150ms; white-space: nowrap;
    }
    .nav-logout:hover { border-color: var(--error); color: var(--error); }

    .container { max-width: 960px; margin: 0 auto; padding: 2.5rem 2rem; }

    .msg { font-size: 0.85rem; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; }
    .msg-success { background: #f0faf5; border: 1px solid #a8e0c4; color: var(--success); }
    .msg-error   { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--error); }

    .stats-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 1.25rem; }
    .stat-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.1rem 1.25rem; }
    .stat-num { font-size: 1.75rem; font-weight: 700; color: var(--ink); line-height: 1; margin-bottom: 4px; }
    .stat-num.c-accent  { color: var(--accent); }
    .stat-num.c-success { color: var(--success); }
    .stat-num.c-error   { color: var(--error); }
    .stat-label { font-size: 0.7rem; color: var(--ink-3); letter-spacing: 0.06em; text-transform: uppercase; }

    .completion-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
    .completion-label { font-size: 0.82rem; color: var(--ink-2); white-space: nowrap; font-weight: 500; }
    .bar-wrap { flex: 1; background: var(--paper-2); border-radius: 99px; height: 7px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 99px; transition: width 600ms ease; }
    .bar-fill.good    { background: var(--success); }
    .bar-fill.warning { background: var(--warning); }
    .bar-fill.danger  { background: var(--error); }
    .bar-fill.zero    { background: var(--border); }
    .completion-pct { font-size: 0.9rem; font-weight: 700; color: var(--ink); min-width: 38px; text-align: right; }

    .two-col { display: grid; grid-template-columns: 1fr 380px; gap: 1.25rem; align-items: start; }
    .section-title { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 0.75rem; }

    .subject-progress-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 1.5rem; }
    .sp-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.25rem; }
    .sp-top { display: flex; align-items: center; gap: 10px; margin-bottom: 0.6rem; }
    .sp-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .sp-name { font-size: 0.9rem; font-weight: 500; color: var(--ink); flex: 1; min-width: 0; }
    .sp-countdown { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 99px; white-space: nowrap; flex-shrink: 0; }
    .sp-countdown.urgent  { background: #fdf2f2; color: var(--error); }
    .sp-countdown.soon    { background: #fef9ec; color: var(--warning); }
    .sp-countdown.relaxed { background: var(--paper-2); color: var(--ink-3); }
    .sp-bar-wrap { background: var(--paper-2); border-radius: 99px; height: 4px; overflow: hidden; margin-bottom: 0.6rem; }
    .sp-bar-fill { height: 100%; border-radius: 99px; transition: width 600ms ease; }
    .sp-meta { display: flex; gap: 1rem; font-size: 0.72rem; color: var(--ink-3); }

    .generate-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
    .btn-generate {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--accent); color: white;
      font-size: 0.8rem; font-weight: 500;
      padding: 0.55rem 1.1rem; border-radius: var(--radius);
      border: none; cursor: pointer; font-family: 'DM Sans', system-ui, sans-serif;
      transition: opacity 150ms;
    }
    .btn-generate:hover { opacity: 0.88; }

    .empty { text-align: center; padding: 2.5rem 1rem; color: var(--ink-3); font-size: 0.875rem; border: 1.5px dashed var(--border); border-radius: var(--radius); }
    .empty-icon { font-size: 1.75rem; margin-bottom: 0.5rem; }

    .schedule-panel { background: white; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; position: sticky; top: 80px; }
    .panel-header { padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .panel-title { font-size: 0.9rem; font-weight: 600; color: var(--ink); }
    .panel-count { font-size: 0.75rem; color: var(--ink-3); }
    .panel-body { max-height: 560px; overflow-y: auto; }

    .date-group { padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--paper-2); }
    .date-group:last-child { border-bottom: none; }
    .date-label { font-size: 0.68rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
    .date-label.today { color: var(--accent); }
    .today-pill { font-size: 0.6rem; background: var(--accent); color: white; padding: 1px 6px; border-radius: 99px; }

    .session-row { display: grid; grid-template-columns: 3px 1fr auto auto; gap: 10px; align-items: start; padding: 8px 0; border-bottom: 1px solid var(--paper-2); }
    .session-row:last-child { border-bottom: none; }
    .session-row.completed { opacity: 0.5; }
    .session-row.missed { opacity: 0.65; }
    .session-bar { width: 3px; border-radius: 99px; min-height: 28px; align-self: stretch; }
    .session-info { min-width: 0; }
    .session-name { font-size: 0.82rem; font-weight: 500; color: var(--ink); line-height: 1.3; margin-bottom: 2px; }
    .session-note { font-size: 0.72rem; color: var(--ink-3); line-height: 1.4; }
    .session-dur  { font-size: 0.75rem; color: var(--ink-3); white-space: nowrap; padding-top: 2px; }

    .session-actions { display: flex; gap: 4px; align-items: center; padding-top: 1px; }
    .btn-s {
      font-size: 0.65rem; font-weight: 600; padding: 3px 7px;
      border-radius: 99px; border: 1px solid var(--border);
      background: white; cursor: pointer; color: var(--ink-2);
      transition: all 120ms; font-family: 'DM Sans', system-ui, sans-serif;
    }
    .btn-s.done:hover { background: #f0faf5; border-color: #a8e0c4; color: var(--success); }
    .btn-s.miss:hover { background: #fdf2f2; border-color: #f5c6c6; color: var(--error); }

    .status-pill { font-size: 0.65rem; font-weight: 600; padding: 2px 7px; border-radius: 99px; white-space: nowrap; }
    .pill-completed { background: #f0faf5; color: var(--success); }
    .pill-missed    { background: #fdf2f2; color: var(--error); }

    .empty-schedule { padding: 2rem 1rem; text-align: center; font-size: 0.82rem; color: var(--ink-3); line-height: 1.6; }

    @media (max-width: 720px) {
      .two-col { grid-template-columns: 1fr; }
      .stats-row { grid-template-columns: repeat(3, 1fr); }
      .schedule-panel { position: static; }
    }
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
    <a href="/availability/">Availability</a>
    <a href="/dashboard/" class="active">Dashboard</a>
  </div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
    <a href="/auth/?action=logout" class="nav-logout">Log out</a>
  </div>
</nav>

<div class="container">

  <?php if ($successMsg): ?>
    <div class="msg msg-success">✓ <?= htmlspecialchars($successMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg msg-error">⚠ <?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card">
      <p class="stat-num c-accent"><?= $total ?></p>
      <p class="stat-label">Sessions</p>
    </div>
    <div class="stat-card">
      <p class="stat-num c-success"><?= $completed ?></p>
      <p class="stat-label">Completed</p>
    </div>
    <div class="stat-card">
      <p class="stat-num c-error"><?= $missed ?></p>
      <p class="stat-label">Missed</p>
    </div>
    <div class="stat-card">
      <p class="stat-num"><?= $pending ?></p>
      <p class="stat-label">Pending</p>
    </div>
    <div class="stat-card">
      <p class="stat-num"><?= number_format($hours, 1) ?></p>
      <p class="stat-label">Hours planned</p>
    </div>
  </div>

  <?php $barClass = $total === 0 ? 'zero' : ($rate >= 70 ? 'good' : ($rate >= 40 ? 'warning' : 'danger')); ?>
  <div class="completion-card">
    <span class="completion-label">Overall completion</span>
    <div class="bar-wrap">
      <div class="bar-fill <?= $barClass ?>" style="width:<?= $rate ?>%"></div>
    </div>
    <span class="completion-pct"><?= $rate ?>%</span>
  </div>

  <div class="two-col">
    <div>
      <?php if (!empty($subjectStats)): ?>
        <p class="section-title">Subject progress</p>
        <div class="subject-progress-list">
          <?php foreach ($subjectStats as $sid => $ss):
            $daysLeft = (int) ceil((strtotime($ss['exam_date']) - time()) / 86400);
            $spRate   = $ss['total'] > 0 ? round(($ss['completed'] / $ss['total']) * 100) : 0;
            $urgency  = $daysLeft <= 3 ? 'urgent' : ($daysLeft <= 7 ? 'soon' : 'relaxed');
            $cdLabel  = $daysLeft <= 0 ? 'Exam passed' : ($daysLeft === 1 ? 'Tomorrow!' : "{$daysLeft} days left");
          ?>
            <div class="sp-card">
              <div class="sp-top">
                <div class="sp-dot" style="background:<?= htmlspecialchars($ss['color']) ?>"></div>
                <span class="sp-name"><?= htmlspecialchars($ss['name']) ?></span>
                <span class="sp-countdown <?= $urgency ?>"><?= $cdLabel ?></span>
              </div>
              <div class="sp-bar-wrap">
                <div class="sp-bar-fill" style="width:<?= $spRate ?>%;background:<?= htmlspecialchars($ss['color']) ?>"></div>
              </div>
              <div class="sp-meta">
                <span>✓ <?= $ss['completed'] ?> done</span>
                <span>✕ <?= $ss['missed'] ?> missed</span>
                <span>◷ <?= number_format($ss['hours'], 1) ?> hrs</span>
                <span><?= $spRate ?>% complete</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="generate-bar">
        <p class="section-title" style="margin:0">Schedule</p>
        <form method="POST" action="/schedule/generate.php">
          <button type="submit" class="btn-generate">
            ✦ <?= empty($schedules) ? 'Generate schedule' : 'Regenerate' ?>
          </button>
        </form>
      </div>

      <?php if (empty($subjects)): ?>
        <div class="empty">
          <div class="empty-icon">📚</div>
          <p><a href="/subjects/" style="color:var(--accent)">Add your subjects</a> to get started.</p>
        </div>
      <?php elseif (empty($availability)): ?>
        <div class="empty">
          <div class="empty-icon">📅</div>
          <p><a href="/availability/" style="color:var(--accent)">Set your availability</a> to get started.</p>
        </div>
      <?php elseif (empty($schedules)): ?>
        <div class="empty">
          <div class="empty-icon">🗓</div>
          <p>Hit <strong>Generate schedule</strong> to build your plan.</p>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <p class="section-title">Upcoming sessions</p>
      <div class="schedule-panel">
        <div class="panel-header">
          <span class="panel-title">Your plan</span>
          <span class="panel-count"><?= count($schedules) ?> sessions</span>
        </div>
        <div class="panel-body">
          <?php if (empty($schedules)): ?>
            <div class="empty-schedule">No sessions yet.<br>Generate a schedule to begin.</div>
          <?php else: ?>
            <?php foreach ($byDate as $date => $sessions):
              $isToday = $date === $today;
              $label   = $isToday ? 'Today' : date('D, M j', strtotime($date));
            ?>
              <div class="date-group">
                <div class="date-label <?= $isToday ? 'today' : '' ?>">
                  <?= $label ?>
                  <?php if ($isToday): ?><span class="today-pill">Today</span><?php endif; ?>
                </div>
                <?php foreach ($sessions as $s): ?>
                  <div class="session-row <?= $s['status'] !== 'pending' ? $s['status'] : '' ?>">
                    <div class="session-bar" style="background:<?= htmlspecialchars($s['color']) ?>"></div>
                    <div class="session-info">
                      <p class="session-name"><?= htmlspecialchars($s['subject_name']) ?></p>
                      <?php if ($s['note']): ?>
                        <p class="session-note"><?= htmlspecialchars($s['note']) ?></p>
                      <?php endif; ?>
                    </div>
                    <span class="session-dur"><?= $s['duration_hours'] ?>h</span>
                    <?php if ($s['status'] === 'pending'): ?>
                      <div class="session-actions">
                        <form method="POST" action="/schedule/status.php" style="margin:0">
                          <input type="hidden" name="id" value="<?= $s['id'] ?>">
                          <input type="hidden" name="status" value="completed">
                          <button type="submit" class="btn-s done">✓</button>
                        </form>
                        <form method="POST" action="/schedule/status.php" style="margin:0">
                          <input type="hidden" name="id" value="<?= $s['id'] ?>">
                          <input type="hidden" name="status" value="missed">
                          <button type="submit" class="btn-s miss">✕</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="status-pill pill-<?= $s['status'] ?>">
                        <?= ucfirst($s['status']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>