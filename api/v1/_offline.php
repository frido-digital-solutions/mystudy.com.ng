<?php
/**
 * The pieces the offline quiz feature is made of.
 *
 * An offline pack is a normal quiz paper that also carries the answer keys, so
 * the phone can mark itself with no network. That is the one place in this API
 * where keys leave the server, and it is safe for exactly one reason: the pack
 * is only ever handed out for a free quiz the student can already sit, and the
 * real mark is recorded by re-marking the answers on the server at sync time
 * (see offline.php). A tampered local key changes the number on the screen,
 * never the number in the database.
 */

require_once __DIR__ . '/_paper.php';

/**
 * offline_attempts remembers which device submission became which attempt, so
 * a retry of the same result lands once. Created on first use, like every other
 * table this API needs - no migration is ever handed to the owner to run.
 */
function offline_table_ready(PDO $pdo): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS offline_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            quiz_id INT NOT NULL,
            client_ref CHAR(36) NOT NULL,
            attempt_id INT NOT NULL,
            synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY client_ref (client_ref),
            KEY user_id (user_id),
            CONSTRAINT offline_attempts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ready = true;
    } catch (Throwable $e) {
        error_log('offline_attempts unavailable: ' . $e->getMessage());
        return false;
    }
    return true;
}

/** How long an unmarked claim may sit before another posting is allowed to take it over. */
const OFFLINE_CLAIM_SECONDS = 60;

/**
 * Take ownership of a receipt number before any result is written.
 *
 * The phone's receipt is unique in offline_attempts, so the claim itself is the
 * race: whoever inserts it first is the one posting that creates the attempt.
 * Every other posting of the same result either hears the finished number,
 * waits out the one still being written, or - if the winner died mid-write -
 * adopts the abandoned claim through an UPDATE that only one caller can make
 * change. Without this, a retry that timed out at the wrong moment could book
 * the same sitting twice and count it twice in the student's record.
 *
 * @return array{state:string,attempt_id?:int}
 */
function offline_claim(PDO $pdo, string $ref, int $userId, int $quizId): array
{
    $insert = $pdo->prepare('INSERT INTO offline_attempts (user_id, quiz_id, client_ref, attempt_id) VALUES (?, ?, ?, 0)');
    $read = $pdo->prepare('SELECT user_id, attempt_id FROM offline_attempts WHERE client_ref = ? LIMIT 1');
    // The interval is a constant in code, never input, and NOW() is compared
    // against the column's own clock so a PHP/MySQL timezone gap cannot matter.
    $adopt = $pdo->prepare(
        'UPDATE offline_attempts SET synced_at = NOW()
          WHERE client_ref = ? AND attempt_id = 0
            AND synced_at < DATE_SUB(NOW(), INTERVAL ' . OFFLINE_CLAIM_SECONDS . ' SECOND)'
    );

    for ($try = 0; $try < 3; $try++) {
        try {
            $insert->execute([$userId, $quizId, $ref]);
            return ['state' => 'mine'];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                throw $e;
            }
        }

        $read->execute([$ref]);
        $seen = $read->fetch(PDO::FETCH_ASSOC);
        if ($seen === false) continue;
        if ((int)$seen['user_id'] !== $userId) return ['state' => 'conflict'];
        if ((int)$seen['attempt_id'] > 0) return ['state' => 'done', 'attempt_id' => (int)$seen['attempt_id']];

        $adopt->execute([$ref]);
        if ($adopt->rowCount() === 1) return ['state' => 'mine'];

        usleep(400000);
    }

    return ['state' => 'busy'];
}

/** Give an unmarked claim back, so a refused or failed sitting never blocks a retry. */
function offline_release_claim(PDO $pdo, string $ref, int $userId): void
{
    try {
        $pdo->prepare('DELETE FROM offline_attempts WHERE client_ref = ? AND user_id = ? AND attempt_id = 0')
            ->execute([$ref, $userId]);
    } catch (Throwable $e) {
        error_log('offline claim release failed: ' . $e->getMessage());
    }
}

/** A set may travel only if it costs nothing and is not an arena event. */
function offline_is_free(array $quiz): bool
{
    return empty($quiz['is_paid']) && ($quiz['mode'] ?? 'normal') !== 'challenge';
}

/**
 * The paper with its keys attached: the same question order, the same options,
 * plus the correct option id, the expected word for a theory question, and the
 * explanation, so the corrections page works in a bus station as well as it
 * works at home.
 */
function offline_pack(PDO $pdo, array $quiz, array $questions): array
{
    $paper = [];
    foreach ($questions as $q) {
        $options = [];
        $correctId = null;
        foreach (($q['options'] ?? []) as $o) {
            if ((int)$o['is_correct'] === 1) {
                $correctId = (int)$o['id'];
            }
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
            'correct_option_id' => $correctId,
            'correct_answer' => (string)($q['correct_answer'] ?? ''),
            'explanation' => (string)($q['explanation'] ?? ''),
        ];
    }

    return [
        'quiz_id' => (int)$quiz['id'],
        'title' => (string)$quiz['title'],
        'subject' => (string)($quiz['subject'] ?? ''),
        'level' => (string)($quiz['level'] ?? ''),
        'tags' => quiz_tags_of($quiz),
        'description' => (string)($quiz['description'] ?? ''),
        'minutes' => quiz_minutes_of($quiz),
        'duration_seconds' => (int)($quiz['duration_seconds'] ?? 900),
        'question_count' => count($paper),
        'total_phases' => (int)($quiz['total_phases'] ?? 1),
        'current_phase' => (int)($quiz['current_phase'] ?? 1),
        'saved_at' => time(),
        'questions' => $paper,
    ];
}

/**
 * The short receipt a queued result turns into. Deliberately not the full web
 * result payload: the phone already drew that page from its own keys, and this
 * only has to say the mark was accepted, and what it actually came out as.
 */
function offline_receipt(PDO $pdo, int $attemptId, int $userId): array
{
    $stmt = $pdo->prepare('SELECT quiz_id, score, total, duration_used, completed_at
                             FROM quiz_attempts WHERE id = ? AND user_id = ?');
    $stmt->execute([$attemptId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['attempt_id' => $attemptId];
    }
    $total = (int)$row['total'];
    return [
        'attempt_id' => $attemptId,
        'quiz_id' => (int)$row['quiz_id'],
        'score' => (int)$row['score'],
        'total' => $total,
        'percent' => $total > 0 ? (int)round((int)$row['score'] / $total * 100) : 0,
        'duration_used' => (int)$row['duration_used'],
        'certificate_url' => SITE_URL . '/certificate.php?attempt_id=' . $attemptId,
    ];
}
