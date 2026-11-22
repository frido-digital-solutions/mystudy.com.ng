<?php
/**
 * The question paper as it leaves the API.
 *
 * Both exam endpoints - the practice quiz (quiz.php) and the battle arena
 * (battle_play.php) - serve their questions through this one function, because
 * a student who battles on the same set must see the same words and the same
 * choices as the student sitting it alone. The answer keys never travel: only
 * the question text, its type and its options.
 */

/**
 * What a question looks like on the way out: the words, never the answer.
 */
function quiz_paper(array $questions): array
{
    $paper = [];
    foreach ($questions as $q) {
        $options = [];
        foreach (($q['options'] ?? []) as $o) {
            $options[] = [
                'id' => (int)$o['id'],
                'text' => (string)$o['option_text'],
                'image' => api_url($o['image_path'] ?? null),
            ];
        }
        $paper[] = [
            'id' => (int)$q['id'],
            'question' => (string)$q['question'],
            'type' => (string)($q['question_type'] ?? 'objective'),
            'image' => api_url($q['image_path'] ?? null),
            'options' => $options,
        ];
    }
    return $paper;
}

/** The two words every exam card uses to describe length and audience. */
function quiz_minutes_of(array $quiz): int
{
    return (int)ceil(((int)($quiz['duration_seconds'] ?? 900)) / 60);
}

function quiz_tags_of(array $quiz): array
{
    $et = trim((string)($quiz['exam_types'] ?? ''));
    if ($et === '' && ($quiz['level'] ?? '') === 'Secondary') {
        $et = 'WAEC,NECO,JAMB';
    }
    return array_values(array_filter(array_map('trim', explode(',', $et))));
}
