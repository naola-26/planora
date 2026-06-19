<?php

class GeminiService {

    private static string $endpoint =
        'https://api.groq.com/openai/v1/chat/completions';

    public static function generateSchedule(
        array $subjects,
        array $availability,
        string $today
    ): array {

        $apiKey = getenv('GROQ_API_KEY');

        $subjectLines = '';
            foreach ($subjects as $s) {
                $daysLeft = (int) ceil((strtotime($s['exam_date']) - strtotime($today)) / 86400);
                $topicsLine = !empty($s['topics'])
                    ? "topics to cover: {$s['topics']}"
                    : "no specific topics given — infer the 4-6 most standard topics typically taught in a course with this exact title";

                $subjectLines .= "- {$s['name']} | difficulty: {$s['difficulty']}/5 | exam in {$daysLeft} days ({$s['exam_date']}) | {$topicsLine}\n";
            }

        $dayNames   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $availLines = '';
        foreach ($availability as $day => $row) {
            $availLines .= "- {$dayNames[$day]} (day {$day}): {$row['hours_available']} hours available\n";
        }

        $forbiddenDays = [];
        for ($i = 0; $i <= 6; $i++) {
            if (!isset($availability[$i])) {
                $forbiddenDays[] = $dayNames[$i];
            }
        }
        $forbiddenLine = implode(', ', $forbiddenDays);

        $prompt = <<<PROMPT
You are a smart study planner. Generate a personalized study schedule in JSON format.

SUBJECTS:
{$subjectLines}

AVAILABLE STUDY DAYS (ONLY schedule on these days):
{$availLines}

DO NOT schedule on: {$forbiddenLine}

TODAY: {$today}

RULES:
1. Schedule sessions starting from tomorrow — never on {$today}.
2. ONLY use the available days listed above. Any session on a forbidden day is invalid.
3. Each session duration must not exceed the hours available for that day.
4. Prioritize subjects with higher difficulty and closer exam dates.
5. Stop scheduling a subject on or after its exam date.
6. Spread sessions across all available days — do not skip available days.
7. Each session's note must reference a SPECIFIC topic for that subject (e.g. "Practice SQL joins and subqueries" not "Study the material"). Rotate through different topics across sessions — don't repeat the same topic for every session of a subject.
8. Generate 20-30% MORE sessions than strictly needed — some may be filtered out.
9. If specific topics were given for a subject, distribute them across that subject's sessions so each session targets a different topic. If no topics were given, infer realistic topics from the course title and do the same.

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
            'model'    => 'llama-3.3-70b-versatile',
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are a study schedule generator. Always respond with valid JSON only, no markdown, no explanation.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.4,
            'max_tokens'  => 2048,
        ];

        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT    => 30,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
           throw new Exception("Groq API error — HTTP $httpCode — $curlError");
        }

        $decoded = json_decode($response, true);
        $text    = $decoded['choices'][0]['message']['content'] ?? '';

        $text = preg_replace('/^```json\s*/i', '', trim($text));
        $text = preg_replace('/```$/',         '', trim($text));

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

        $sessions = [];
        $tips     = [
            'Focus on key concepts and definitions first.',
            'Practice past exam questions for this topic.',
            'Review your notes and summarize key points.',
            'Work through examples step by step.',
            'Focus on areas you find most challenging.',
            'Create a mind map of the main topics.',
            'Quiz yourself without looking at notes.',
        ];

        usort($subjects, fn($a, $b) => strtotime($a['exam_date']) - strtotime($b['exam_date']));

        $availDays = array_keys($availability);
        if (empty($availDays)) return [];

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

                foreach ($subjects as $subject) {
                    if ($hoursLeft <= 0) break;
                    if ($dateStr >= $subject['exam_date']) continue;

                    $sessionHours = min(round($subject['difficulty'] * 0.5, 1), $hoursLeft);
                    if ($sessionHours < 0.5) continue;

                    $sessions[] = [
                        'subject_name'   => $subject['name'],
                        'date'           => $dateStr,
                        'duration_hours' => $sessionHours,
                        'note'           => $tips[$tipIndex % count($tips)],
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