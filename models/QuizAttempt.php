<?php
class QuizAttempt
{
    private PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            user_id INT NOT NULL,
            score INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            duration_used INT NOT NULL DEFAULT 0,
            completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            phase INT NOT NULL DEFAULT 1,
            INDEX (quiz_id), INDEX(user_id),
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $this->conn->exec("ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS phase INT NOT NULL DEFAULT 1"); } catch (Throwable $e) {}

        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_option_id INT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $this->conn->exec("ALTER TABLE quiz_answers ADD COLUMN IF NOT EXISTS text_answer VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
    }

    public function hasAttempted(int $quizId, int $userId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?");
        $stmt->execute([$quizId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * $allowedQuizIds / $presentedQuestionIds define the server-side question scope.
     * Multi-subject sessions pass the source quizzes of the questions they were served;
     * every other caller leaves them empty and is scoped to $quizId alone.
     */
    public function submitAttempt(int $quizId, int $userId, array $answers, int $durationUsed, array $allowedQuizIds = [], array $presentedQuestionIds = []): int
    {
        $allowedQuizIds = array_values(array_unique(array_filter(array_map('intval', $allowedQuizIds), fn($v) => $v > 0)));
        if (!$allowedQuizIds) { $allowedQuizIds = [$quizId]; }
        $presentedQuestionIds = array_values(array_unique(array_filter(array_map('intval', $presentedQuestionIds), fn($v) => $v > 0)));
        $quizIn = implode(',', array_fill(0, count($allowedQuizIds), '?'));

        $this->conn->beginTransaction();
        try {
            // Get quiz's current phase
            $phaseStmt = $this->conn->prepare("SELECT current_phase FROM quizzes WHERE id = ?");
            $phaseStmt->execute([$quizId]);
            $currentPhase = (int)($phaseStmt->fetchColumn() ?: 1);

            // Fetch question metadata (type + correct_answer for subjective), scoped to the allowed quizzes
            if ($presentedQuestionIds) {
                $qIn = implode(',', array_fill(0, count($presentedQuestionIds), '?'));
                $metaStmt = $this->conn->prepare("SELECT id, quiz_id, question_type, correct_answer FROM quiz_questions WHERE id IN ($qIn) AND quiz_id IN ($quizIn)");
                $metaStmt->execute(array_merge($presentedQuestionIds, $allowedQuizIds));
            } else {
                $metaStmt = $this->conn->prepare("SELECT id, quiz_id, question_type, correct_answer FROM quiz_questions WHERE quiz_id IN ($quizIn)");
                $metaStmt->execute($allowedQuizIds);
            }
            $questionMeta = [];
            foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $questionMeta[(int)$m['id']] = $m;
            }

            // The denominator is the question set the server served, never the posted answer count,
            // so a partial or forged submission cannot inflate the percentage.
            $total = count($questionMeta);
            $qIds = array_keys($questionMeta);
            if (!$qIds) { $qIds = [0]; }
            $in = implode(',', array_fill(0, count($qIds), '?'));

            // Anything posted for a question outside that set is discarded before scoring or storage
            $answers = array_intersect_key($answers, $questionMeta);
            foreach ($answers as $k => $v) {
                if (!is_scalar($v)) { $answers[$k] = null; }
            }

            // Fetch correct options for objective questions
            $stmt = $this->conn->prepare("SELECT qq.id as qid, qo.id as oid, qo.is_correct FROM quiz_questions qq JOIN quiz_options qo ON qo.question_id = qq.id WHERE qq.id IN ($in) AND qq.quiz_id IN ($quizIn)");
            $stmt->execute(array_merge($qIds, $allowedQuizIds));
            $correctMap = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (!isset($correctMap[$r['qid']])) $correctMap[$r['qid']] = [];
                $correctMap[$r['qid']][$r['oid']] = (int)$r['is_correct'] === 1;
            }

            $score = 0;
            foreach ($answers as $qid => $ans) {
                $qid = (int)$qid;
                $meta = $questionMeta[$qid] ?? null;
                $isSubjective = $meta && ($meta['question_type'] ?? 'objective') === 'subjective';
                $isCorrect = 0;

                if ($isSubjective) {
                    // Case-insensitive comparison for subjective
                    $userAnswer = is_string($ans) ? strtolower(trim($ans)) : '';
                    $expected = strtolower(trim($meta['correct_answer'] ?? ''));
                    if ($userAnswer !== '' && $expected !== '' && $userAnswer === $expected) {
                        $isCorrect = 1; $score++;
                    }
                } else {
                    // Objective: check option ID
                    $oid = $ans ? (int)$ans : null;
                    if ($oid && isset($correctMap[$qid][$oid]) && $correctMap[$qid][$oid] === true) {
                        $isCorrect = 1; $score++;
                    }
                }
            }

            // Save attempt with phase
            $aStmt = $this->conn->prepare('INSERT INTO quiz_attempts (quiz_id,user_id,score,total,duration_used,completed_at,phase) VALUES (:q,:u,:s,:t,:d, NOW(), :p)');
            $aStmt->execute([':q'=>$quizId, ':u'=>$userId, ':s'=>$score, ':t'=>$total, ':d'=>$durationUsed, ':p'=>$currentPhase]);
            $attemptId = (int)$this->conn->lastInsertId();

            // Save answers
            $ansStmt = $this->conn->prepare('INSERT INTO quiz_answers (attempt_id,question_id,selected_option_id,is_correct,text_answer) VALUES (:a,:q,:o,:c,:ta)');
            foreach ($answers as $qid=>$ans) {
                $qid = (int)$qid;
                $meta = $questionMeta[$qid] ?? null;
                $isSubjective = $meta && ($meta['question_type'] ?? 'objective') === 'subjective';
                $isCorrect = 0;
                $textAnswer = null;
                $oid = null;

                if ($isSubjective) {
                    $textAnswer = is_string($ans) ? trim($ans) : '';
                    $userAnswer = strtolower($textAnswer);
                    $expected = strtolower(trim($meta['correct_answer'] ?? ''));
                    if ($userAnswer !== '' && $expected !== '' && $userAnswer === $expected) { $isCorrect = 1; }
                } else {
                    $oid = $ans ? (int)$ans : null;
                    if ($oid && isset($correctMap[$qid][$oid]) && $correctMap[$qid][$oid] === true) { $isCorrect = 1; }
                }
                $ansStmt->execute([':a'=>$attemptId, ':q'=>$qid, ':o'=>$oid, ':c'=>$isCorrect, ':ta'=>$textAnswer]);
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) { $this->conn->rollBack(); }
            log_error('submitAttempt failed', ['err'=>$e->getMessage()]);
            throw $e;
        }

        // Everything below runs after the attempt is committed: it can never roll it back.
        $gamResult = ['points_earned' => 0, 'new_badges' => [], 'level_up' => false, 'new_level' => 0, 'streak' => 0];
        $quizTitle = 'Quiz';
        $isChallenge = false;
        $pct = $total > 0 ? round(($score / $total) * 100) : 0;

        try {
            require_once __DIR__ . '/Gamification.php';
            $gamification = new Gamification($this->conn);

            // Get quiz title and mode
            $quizInfoStmt = $this->conn->prepare("SELECT title, mode FROM quizzes WHERE id = ?");
            $quizInfoStmt->execute([$quizId]);
            $quizInfo = $quizInfoStmt->fetch(PDO::FETCH_ASSOC);
            $quizTitle = $quizInfo['title'] ?? 'Quiz';
            $isChallenge = ($quizInfo['mode'] ?? 'normal') === 'challenge';

            // Award gamification points and badges
            $gamResult = $gamification->awardQuizPoints($userId, $score, $total, $quizId, $isChallenge);
        } catch (Throwable $e) {
            log_error('Gamification award failed after attempt was saved', ['attempt_id'=>$attemptId, 'quiz_id'=>$quizId, 'user_id'=>$userId, 'err'=>$e->getMessage()]);
        }

        // Create notification for quiz completion
    try {
        require_once __DIR__ . '/Notification.php';
        $notifModel = new Notification($this->conn);
        $xpMsg = " (+{$gamResult['points_earned']} XP)";

        if ($isChallenge) {
            $title = "🏅 Challenge Complete!";
            $msg = "You scored {$score}/{$total} ({$pct}%) on \"{$quizTitle}\".{$xpMsg}";
            $link = SITE_URL . "/quiz_result.php?attempt={$attemptId}";
            $type = 'challenge_complete';
        } else {
            $title = "✅ Quiz Completed!";
            $msg = "You scored {$score}/{$total} ({$pct}%) on \"{$quizTitle}\".{$xpMsg}";
            $link = SITE_URL . "/quiz_result.php?attempt={$attemptId}";
            $type = 'quiz_result';
        }
        
        $notifModel->create($userId, $type, $title, $msg, $link);
        
        // Send level-up notification
        if ($gamResult['level_up']) {
            $notifModel->create($userId, 'level_up', "⭐ Level Up!", "Congratulations! You've reached Level {$gamResult['new_level']}! Keep learning to unlock more rewards.", SITE_URL . '/dashboard.php');
        }
        
        // Send badge notifications
        foreach ($gamResult['new_badges'] as $badgeName) {
            $badgeMeta = Gamification::BADGES[$badgeName] ?? null;
            if ($badgeMeta) {
                $notifModel->create($userId, 'badge_earned', "{$badgeMeta[0]} Badge Earned!", "You earned the \"{$badgeMeta[1]}\" badge! Check your profile to see all your achievements.", SITE_URL . '/dashboard.php');
            }
        }
        
        // Send streak notification
        if ($gamResult['streak'] > 1 && $gamResult['streak'] % 3 === 0) {
            $notifModel->create($userId, 'streak', "🔥 {$gamResult['streak']}-Day Streak!", "Amazing! You've maintained a {$gamResult['streak']}-day quiz streak. Keep it going!", SITE_URL . '/dashboard.php');
        }
        } catch (Throwable $e) {
            log_error('Quiz completion notification failed', ['attempt_id'=>$attemptId, 'quiz_id'=>$quizId, 'user_id'=>$userId, 'err'=>$e->getMessage()]);
        }

        return $attemptId;
    }

    public function getAttempt(int $attemptId, int $userId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quiz_attempts WHERE id=:id AND user_id=:u');
        $stmt->execute([':id'=>$attemptId, ':u'=>$userId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        return $a ?: null;
    }

    public function getAnswersDetailed(int $attemptId): array
    {
        // Get answers joined with question/options to show corrections and subjects.
        // The pictures travel too: an option can be nothing but a diagram, and a
        // review that cannot show it tells the student nothing about their mark.
        $stmt = $this->conn->prepare('SELECT qa.*, qq.question, qq.image_path as question_image, qq.explanation, qq.question_type, qq.correct_answer as expected_answer, q.subject, qo.option_text as selected_text, qo.image_path as selected_image FROM quiz_answers qa LEFT JOIN quiz_questions qq ON qq.id=qa.question_id LEFT JOIN quizzes q ON q.id=qq.quiz_id LEFT JOIN quiz_options qo ON qo.id=qa.selected_option_id WHERE qa.attempt_id=:a ORDER BY qa.id ASC');
        $stmt->execute([':a'=>$attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Load correct option per question
        if ($rows) {
            $qIds = array_values(array_unique(array_map(fn($r)=> (int)$r['question_id'], $rows)));
            $in = implode(',', array_fill(0, count($qIds), '?'));
            $cStmt = $this->conn->prepare("SELECT question_id, option_text, image_path FROM quiz_options WHERE question_id IN ($in) AND is_correct=1");
            $cStmt->execute($qIds);
            $correct = [];
            $correctImages = [];
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $correct[$r['question_id']] = $r['option_text'];
                if (!empty($r['image_path'])) { $correctImages[(int)$r['question_id']] = $r['image_path']; }
            }
            foreach ($rows as &$r) {
                $r['correct_text'] = $correct[$r['question_id']] ?? null;
                $r['correct_image'] = $correctImages[(int)$r['question_id']] ?? null;
            }
        }
        return $rows;
    }

    public function topScores(?int $quizId = null, ?string $type = null, int $limit = 20): array
    {
        $params = [];
        $where = [];
        if ($quizId) { $where[] = 'qa.quiz_id = :q'; $params[':q'] = $quizId; }
        if ($type) { $where[] = 'q.type = :t'; $params[':t'] = $type; }
        $w = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        
        // Check if quiz uses phases
        if ($quizId) {
            $phaseCheckStmt = $this->conn->prepare("SELECT total_phases FROM quizzes WHERE id = ?");
            $phaseCheckStmt->execute([$quizId]);
            $totalPhases = (int)($phaseCheckStmt->fetchColumn() ?: 1);
            
            if ($totalPhases > 1) {
                // Cumulative scoring: sum scores across all phases per user
                $sql = "SELECT 
                    SUM(qa.score) as score,
                    SUM(qa.total) as total,
                    SUM(qa.duration_used) as duration_used,
                    MAX(qa.completed_at) as completed_at,
                    qa.user_id,
                    qa.quiz_id,
                    u.name, 
                    u.user_type, 
                    u.profile_picture, 
                    u.email
                FROM quiz_attempts qa 
                JOIN users u ON u.id=qa.user_id 
                JOIN quizzes q ON q.id=qa.quiz_id 
                $w 
                GROUP BY qa.user_id, qa.quiz_id, u.name, u.user_type, u.profile_picture, u.email
                ORDER BY score DESC, duration_used ASC, completed_at ASC 
                LIMIT :l";
            } else {
                // Single phase: one row per student, at their best sitting.
                $sql = $this->bestPerUserSql($w);
            }
        } else {
            // No specific quiz: still one best sitting per student per quiz.
            $sql = $this->bestPerUserSql($w);
        }
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * The board query that lists each student once.
     *
     * Listing raw attempts made a person who sat the same quiz three times
     * appear on that quiz's board three times, as if they were three students -
     * which also let one student take three shares of a prize pool. The row a
     * student keeps is their best sitting: highest score, then the fastest run
     * at that score, then whoever got there first. The columns are the attempt's
     * own, so every caller reads the board exactly as before.
     */
    private function bestPerUserSql(string $w): string
    {
        return "SELECT best.*, u.name, u.user_type, u.profile_picture, u.email
                  FROM (
                    SELECT (SELECT pick_qa.id
                              FROM quiz_attempts pick_qa
                             WHERE pick_qa.user_id = per.user_id
                               AND pick_qa.quiz_id = per.quiz_id
                             ORDER BY pick_qa.score DESC, pick_qa.duration_used ASC,
                                      pick_qa.completed_at ASC, pick_qa.id ASC
                             LIMIT 1) AS attempt_id
                      FROM (
                        SELECT DISTINCT qa.user_id, qa.quiz_id
                          FROM quiz_attempts qa
                          JOIN quizzes q ON q.id = qa.quiz_id
                          $w
                      ) per
                  ) chosen
                  JOIN quiz_attempts best ON best.id = chosen.attempt_id
                  JOIN users u ON u.id = best.user_id
                 ORDER BY best.score DESC, best.duration_used ASC, best.completed_at ASC, best.id ASC
                 LIMIT :l";
    }
    
    // Get user's phase-by-phase breakdown for a challenge
    public function getUserPhaseBreakdown(int $quizId, int $userId): array
    {
        $sql = "SELECT phase, score, total, duration_used, completed_at 
                FROM quiz_attempts 
                WHERE quiz_id = :q AND user_id = :u 
                ORDER BY phase ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':q' => $quizId, ':u' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
