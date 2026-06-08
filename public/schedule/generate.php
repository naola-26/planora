<?php

$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/models/Subject.php';
require_once __DIR__ . '/../../src/models/Availability.php';
require_once __DIR__ . '/../../src/models/Schedule.php';
require_once __DIR__ . '/../../src/services/GeminiService.php';

requireLogin();
$user = currentUser();

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/');
    exit;
}

$today        = date('Y-m-d');
$subjects     = Subject::allForUser($user['id']);
$availability = Availability::forUser($user['id']);

// Guards
if (empty($subjects)) {
    $_SESSION['schedule_error'] = 'Add at least one subject before generating a schedule.';
    header('Location: /subjects/');
    exit;
}

if (empty($availability)) {
    $_SESSION['schedule_error'] = 'Set your availability before generating a schedule.';
    header('Location: /availability/');
    exit;
}

try {
    // Call Gemini
    $sessions = GeminiService::generateSchedule($subjects, $availability, $today);

    // Map subject names to IDs
    $nameToId = [];
    foreach ($subjects as $s) {
        $nameToId[strtolower(trim($s['name']))] = $s['id'];
    }

    // Build insert rows
    $rows = [];
    foreach ($sessions as $session) {
        $name      = strtolower(trim($session['subject_name'] ?? ''));
        $subjectId = $nameToId[$name] ?? null;

        if (!$subjectId) continue; // Skip if AI hallucinated a subject name

        $rows[] = [
            'user_id'        => $user['id'],
            'subject_id'     => $subjectId,
            'scheduled_date' => $session['date'],
            'duration_hours' => (float) $session['duration_hours'],
            'note'           => $session['note'] ?? null,
        ];
    }

    // Clear old schedule and insert new one
    Schedule::clearForUser($user['id']);
    Schedule::insertMany($rows);

    $_SESSION['schedule_success'] = count($rows) . ' sessions scheduled successfully.';

} catch (Exception $e) {
    $_SESSION['schedule_error'] = 'Could not generate schedule: ' . $e->getMessage();
}

header('Location: /dashboard/');
exit;