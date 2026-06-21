<?php
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    foreach ($env as $key => $value) putenv("$key=$value");
}

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/models/Schedule.php';

requireLogin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id']     ?? 0);
    $status = $_POST['status'] ?? '';

    if ($id && in_array($status, ['completed', 'missed'])) {
        Schedule::updateStatus($id, $user['id'], $status);
    }
}

header('Location: /dashboard/');
exit;