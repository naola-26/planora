<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
foreach ($env as $key => $value) putenv("$key=$value");
require_once __DIR__ . '/../../src/helpers/auth.php';
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
</head>
<body>
  <h1>Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
  <p>You are logged in as <?= htmlspecialchars($user['email']) ?></p>
  <a href="/auth/?action=logout">Log out</a>
</body>
</html>