<?php

require_once __DIR__ . '/../../config/database.php';

class Subject {

    public static function allForUser(int $userId): array {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM subjects WHERE user_id = ? ORDER BY exam_date ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create(
        int $userId,
        string $name,
        int $difficulty,
        string $examDate,
        string $color,
        ?string $topics = null
    ): int {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO subjects (user_id, name, difficulty, topics, exam_date, color)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $difficulty, $topics, $examDate, $color]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id, int $userId): bool {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'DELETE FROM subjects WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function countForUser(int $userId): int {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM subjects WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}