<?php

class GeminiService {

    private static string $endpoint =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent';

    public static function generateSchedule(
        array $subjects,
        array $availability,
        string $today
    ): array {

        // --- MOCK MODE: remove this block once rate limit lifts ---
        return self::mockSchedule($subjects, $availability, $today);
        // ----------------------------------------------------------

        $apiKey = getenv('GEMINI_API_KEY');

        $subjectLines = '';
        foreach ($subjects as $s) {
            $daysLeft = (int) ceil((strtotime($s['exam_date']) - strtotime($today)) / 86400);
            $subjectLines .= "- {$s['name']} | difficulty: {$s['difficulty']}/5 | exam in {$daysLeft} days ({$s['exam_date']})\n";
        }

        $dayNames   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $availLines = '';
        foreach ($availability as $day => $row) {
            $availLines .= "- {$dayNames[$day]}: {$row['hours_available']} hours\n";
        }

        $prompt = <<<PROMPT
You are a smart study planner. Generate a personalized study schedule in JSON format.

SUBJECTS:
{$subjectLines}

WEEKLY AVAILABILITY:
{$availLines}

TODAY: {$today}

RULES:
1. Schedule sessions starting from tomorrow.
2. Only schedule on the available days listed above.
3. Each session duration must not exceed the hours available for that day.
4. Prioritize subjects with higher difficulty and closer exam dates.
5. Stop scheduling a subject after its exam date.
6. Spread sessions across multiple days.
7. Each session should have a short, specific study tip (max 12 words).
8. Generate enough sessions to cover all subjects adequately before their exams.

RESPONSE FORMAT — return ONLY valid JSON, no markdown, no explanation:
{
  "sessions": [
    {
      "subject_name": "exact subject name from list",
      "date": "YYYY-MM-DD",
      "duration_hours": 1.5,
      "note": "short study tip for this session"
    }
  ]
}
PROMPT;

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.4,
                'maxOutputTokens' => 2048,
            ]
        ];

        $ch = curl_init(self::$endpoint . '?key=' . $apiKey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            throw new Exception("Gemini API error — HTTP $httpCode");
        }

        $decoded = json_decode($response, true);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $text = preg_replace('/^```json\s*/i', '', trim($text));
        $text = preg_replace('/```$/', '', trim($text));

        $schedule = json_decode(trim($text), true);

        if (!isset($schedule['sessions']) || !is_array($schedule['sessions'])) {
            throw new Exception('Invalid schedule format returned by AI.');
        }

        return $schedule['sessions'];
    }

    private static function mockSchedule(
        array $subjects,
        array $availability,
        string $today
    ): array {

        $sessions  = [];
        $dayNames  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $tips      = [
            'Focus on key concepts and definitions first.',
            'Practice past exam questions for this topic.',
            'Review your notes and summarize key points.',
            'Work through examples step by step.',
            'Focus on areas you find most challenging.',
            'Create a mind map of the main topics.',
            'Quiz yourself without looking at notes.',
        ];

        // Sort subjects by exam date ascending
        usort($subjects, fn($a, $b) => strtotime($a['exam_date']) - strtotime($b['exam_date']));

        // Get available day numbers
        $availDays = array_keys($availability);

        if (empty($availDays)) return [];

        // Generate sessions for the next 14 days
        $date    = new DateTime($today);
        $date->modify('+1 day');
        $endDate = new DateTime($today);
        $endDate->modify('+14 days');

        $tipIndex = 0;

        while ($date <= $endDate) {
            $dayOfWeek = (int) $date->format('w');
            $dateStr   = $date->format('Y-m-d');

            if (in_array($dayOfWeek, $availDays)) {
                $hoursLeft = (float) $availability[$dayOfWeek]['hours_available'];

                // Pick subjects for this day — prioritize harder + closer exams
                foreach ($subjects as $subject) {
                    if ($hoursLeft <= 0) break;
                    if ($dateStr >= $subject['exam_date']) continue;

                    // Allocate hours based on difficulty
                    $sessionHours = min(
                        round($subject['difficulty'] * 0.5, 1),
                        $hoursLeft
                    );
                    if ($sessionHours < 0.5) continue;

                    $sessions[] = [
                        'subject_name' => $subject['name'],
                        'date'         => $dateStr,
                        'duration_hours' => $sessionHours,
                        'note'         => $tips[$tipIndex % count($tips)],
                    ];

                    $hoursLeft -= $sessionHours;
                    $tipIndex++;
                }
            }

            $date->modify('+1 day');
        }

        return $sessions;
    }
}