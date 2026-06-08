<?php

require_once __DIR__ . '/../../src/models/Availability.php';

class AvailabilityController {

    public static function handleSave(int $userId): array {
        $days = $_POST['days'] ?? [];

        if (empty($days)) {
            return ['error' => 'Select at least one available day.'];
        }

        $cleaned = [];
        foreach ($days as $day => $hours) {
            $day   = (int) $day;
            $hours = (float) $hours;
            if ($day < 0 || $day > 6)     continue;
            if ($hours < 0.5 || $hours > 8) continue;
            $cleaned[$day] = $hours;
        }

        if (empty($cleaned)) {
            return ['error' => 'Each selected day needs at least 0.5 hours.'];
        }

        Availability::save($userId, $cleaned);
        return ['success' => true];
    }
}