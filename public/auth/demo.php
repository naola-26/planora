<?php
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    foreach ($env as $key => $value) putenv("$key=$value");
}

require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../config/database.php';

startSession();

// Find demo user
$pdo  = getDB();
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['demo@studyplanner.app']);
$user = $stmt->fetch();

if ($user) {
    loginUser($user['id'], $user['name'], $user['email']);
    header('Location: /dashboard/');
} else {
    header('Location: /auth/?error=demo_unavailable');
}
exit;