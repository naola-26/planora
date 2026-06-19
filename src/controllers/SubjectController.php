<?php

require_once __DIR__ . '/../../src/models/Subject.php';

class SubjectController {

    private static array $colors = [
        '#3b6ef5', '#e05a2b', '#2eab6f',
        '#9b59b6', '#e67e22', '#1abc9c',
        '#e74c3c', '#2980b9', '#f39c12', '#27ae60'
    ];

    public static function handleCreate(int $userId): array {
        $name       = trim($_POST['name']       ?? '');
        $difficulty = (int)($_POST['difficulty'] ?? 0);
        $examDate   = trim($_POST['exam_date']  ?? '');
        $topics     = trim($_POST['topics']     ?? '');
    
        if (!$name || !$difficulty || !$examDate) {
            return ['error' => 'All fields are required.'];
        }
        if (strlen($name) > 100) {
            return ['error' => 'Subject name is too long.'];
        }
        if ($difficulty < 1 || $difficulty > 5) {
            return ['error' => 'Difficulty must be between 1 and 5.'];
        }
        if (strtotime($examDate) <= time()) {
            return ['error' => 'Exam date must be in the future.'];
        }
        if (strlen($topics) > 500) {
            return ['error' => 'Topics list is too long — keep it under 500 characters.'];
        }
        if (Subject::countForUser($userId) >= 10) {
            return ['error' => 'Maximum of 10 subjects allowed.'];
        }
    
        $count = Subject::countForUser($userId);
        $color = self::$colors[$count % count(self::$colors)];
    
        Subject::create($userId, $name, $difficulty, $examDate, $color, $topics ?: null);
        return ['success' => true];
    }

    public static function handleDelete(int $userId): array {
        $id = (int)($_POST['subject_id'] ?? 0);
        if (!$id) {
            return ['error' => 'Invalid subject.'];
        }
        Subject::delete($id, $userId);
        return ['success' => true];
    }
}