<?php

require_once __DIR__ . '/../../config/database.php';

class Schedule {

    public static function forUser(int $userId): array {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT s.*, sub.name as subject_name, sub.color
             FROM schedules s
             JOIN subjects sub ON s.subject_id = sub.id
             WHERE s.user_id = ?
             ORDER BY s.scheduled_date ASC, s.id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function clearForUser(int $userId): void {
        $pdo = getDB();
        $pdo->prepare('DELETE FROM schedules WHERE user_id = ?')
            ->execute([$userId]);
    }

    public static function insertMany(array $sessions): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO schedules
             (user_id, subject_id, scheduled_date, duration_hours, status, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($sessions as $s) {
            $stmt->execute([
                $s['user_id'],
                $s['subject_id'],
                $s['scheduled_date'],
                $s['duration_hours'],
                'pending',
                $s['note'] ?? null,
            ]);
        }
    }

    public static function updateStatus(int $id, int $userId, string $status): bool {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'UPDATE schedules SET status = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$status, $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function stats(int $userId): array {
        $pdo = getDB();

        $stmt = $pdo->prepare(
            'SELECT
               COUNT(*) as total,
               SUM(status = "completed") as completed,
               SUM(status = "missed") as missed,
               SUM(status = "pending") as pending,
               SUM(duration_hours) as total_hours
             FROM schedules WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}