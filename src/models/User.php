<?php

require_once __DIR__ . '/../../config/database.php';

class User {

    public static function findByEmail(string $email): array|false {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function create(string $name, string $email, string $password): int {
        $pdo = getDB();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash]);
        return (int) $pdo->lastInsertId();
    }

    public static function emailExists(string $email): bool {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }
}