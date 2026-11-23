<?php
/**
 * The record of a quiz that is being sat right now.
 *
 * Mentor is switched off while a student is in a quiz - on the phone the
 * button is not even there, and this file is what makes the rule true on the
 * server as well, so it cannot be undone by a phone that was patched, or by a
 * student who asked the question from another device.
 *
 * A session is opened when the paper is opened and closed when it is handed
 * in. A student who closes the app mid-paper leaves a session open, so an
 * open session that is older than a few hours is treated as finished: the
 * paper was never handed in and the student is not cheating, they just went
 * away.
 */

if (!defined('QUIZ_SESSION_MAX_AGE_HOURS')) {
    define('QUIZ_SESSION_MAX_AGE_HOURS', 3);
}

/**
 * The table, made on the way past so the code works the moment it is deployed.
 */
function quiz_sessions_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS quiz_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT \'quiz\',
            ref_id INT NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME DEFAULT NULL,
            KEY idx_quiz_session_user (user_id, ended_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $ready = true;
    } catch (Throwable $e) {
        error_log('quiz_sessions unavailable: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/**
 * A paper was opened.
 */
function quiz_session_open(PDO $pdo, int $userId, string $kind = 'quiz', int $refId = 0): void
{
    if ($userId <= 0 || !quiz_sessions_ready($pdo)) return;
    try {
        // One open session per paper: starting the same paper twice does not
        // stack two rows, and the older one is closed in the same breath.
        $pdo->prepare('UPDATE quiz_sessions SET ended_at = NOW()
                       WHERE user_id = ? AND kind = ? AND ref_id = ? AND ended_at IS NULL')
            ->execute([$userId, $kind, $refId]);
        $pdo->prepare('INSERT INTO quiz_sessions (user_id, kind, ref_id, started_at)
                       VALUES (?, ?, ?, NOW())')
            ->execute([$userId, $kind, $refId]);
    } catch (Throwable $e) {
        error_log('quiz_session open: ' . $e->getMessage());
    }
}

/**
 * The paper was handed in, or the student left the screen.
 */
function quiz_session_close(PDO $pdo, int $userId, string $kind = 'quiz', int $refId = 0): void
{
    if ($userId <= 0 || !quiz_sessions_ready($pdo)) return;
    try {
        $pdo->prepare('UPDATE quiz_sessions SET ended_at = NOW()
                       WHERE user_id = ? AND kind = ? AND ref_id = ? AND ended_at IS NULL')
            ->execute([$userId, $kind, $refId]);
        // Any other paper this student left open is closed too: one student
        // sits one paper at a time.
        $pdo->prepare('UPDATE quiz_sessions SET ended_at = NOW()
                       WHERE user_id = ? AND ended_at IS NULL')
            ->execute([$userId]);
    } catch (Throwable $e) {
        error_log('quiz_session close: ' . $e->getMessage());
    }
}

/**
 * Is this student in a paper right now?
 */
function quiz_session_active(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !quiz_sessions_ready($pdo)) return null;
    try {
        $stmt = $pdo->prepare('SELECT id, kind, ref_id, started_at FROM quiz_sessions
                               WHERE user_id = ? AND ended_at IS NULL
                                 AND started_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                               ORDER BY started_at DESC LIMIT 1');
        $stmt->execute([$userId, QUIZ_SESSION_MAX_AGE_HOURS]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('quiz_session active: ' . $e->getMessage());
        return null;
    }
}
