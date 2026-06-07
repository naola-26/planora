<?php

// Load .env
$env = parse_ini_file(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    putenv("$key=$value");
}

// Autoload
require_once __DIR__ . '/../config/database.php';

// Test
try {
    $pdo = getDB();
    echo "Connected successfully.";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage();
}