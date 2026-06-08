<?php

require_once __DIR__ . '/../../config/database.php';

class Availability {

    public static function forUser(int $userId): array {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM availability WHERE user_id = ? ORDER BY day_of_week ASC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        // Key by day_of_week for easy lookup
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int)$row['day_of_week']] = $row;
        }
        return $keyed;
    }

    public static function save(int $userId, array $days): void {
        $pdo = getDB();

        // Delete existing and re-insert cleanly
        $pdo->prepare('DELETE FROM availability WHERE user_id = ?')
            ->execute([$userId]);

        $stmt = $pdo->prepare(
            'INSERT INTO availability (user_id, day_of_week, hours_available)
             VALUES (?, ?, ?)'
        );

        foreach ($days as $day => $hours) {
            if ($hours > 0) {
                $stmt->execute([$userId, (int)$day, (float)$hours]);
            }
        }
    }

    public static function totalHoursPerWeek(int $userId): float {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT SUM(hours_available) FROM availability WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (float) $stmt->fetchColumn();
    }
}