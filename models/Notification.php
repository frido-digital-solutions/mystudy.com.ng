<?php
class Notification
{
    private PDO $conn;
    
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }
    
    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    public function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $sql = "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $type, $title, $message, $link]);
        $id = (int)$this->conn->lastInsertId();

        // Dispatch Web Push notification (non-blocking — failures are silently logged)
        try {
            require_once __DIR__ . '/../includes/WebPushService.php';
            $push = new WebPushService($this->conn);
            $push->sendToUser($userId, $title, $message, $link);
        } catch (\Throwable $e) {
            error_log('WebPush dispatch failed: ' . $e->getMessage());
        }

        return $id;
    }
    
    public function getUserNotifications(int $userId, int $limit = 20): array
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Latest unread admin notice (broadcast/unicast) for modal.
     */
    public function getLatestUnreadAdminNotice(int $userId): ?array
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :uid 
                  AND is_read = 0 
                  AND type IN ('admin_broadcast','admin_unicast','admin_multicast')
                ORDER BY created_at DESC 
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function markAsRead(int $notificationId, ?int $userId = null): bool
    {
        // Fall back to the session owner so legacy callers can never touch another user's row.
        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    public function markAllAsRead(int $userId): bool
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    // Notify all participants when challenge ends
    public function notifyChallengeEnd(int $quizId): void
    {
        // Get quiz details
        $quizStmt = $this->conn->prepare("SELECT * FROM quizzes WHERE id = ?");
        $quizStmt->execute([$quizId]);
        $quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz) return;
        
        // Get all participants with scores
        $participantsStmt = $this->conn->prepare("
            SELECT 
                user_id,
                SUM(score) as total_score,
                SUM(total) as total_questions
            FROM quiz_attempts 
            WHERE quiz_id = ?
            GROUP BY user_id
        ");
        $participantsStmt->execute([$quizId]);
        $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($participants)) return;
        
        // Get rankings
        require_once __DIR__ . '/QuizAttempt.php';
        $attemptModel = new QuizAttempt($this->conn);
        $leaderboard = $attemptModel->topScores($quizId, null, 1000);
        
        // Create ranking map
        $rankMap = [];
        foreach ($leaderboard as $index => $entry) {
            $rankMap[(int)$entry['user_id']] = $index + 1;
        }
        
        $totalParticipants = count($participants);
        $hasPrize = !empty($quiz['prize_amount']) || !empty($quiz['prize_text']);
        $prizeDisplay = '';
        if (!empty($quiz['prize_amount'])) {
            if (function_exists('format_prize')) {
                $prizeDisplay = format_prize($quiz['prize_amount'], $quiz['prize_type'] ?? 'money');
            } else {
                $prizeDisplay = '₦' . number_format((float)$quiz['prize_amount'], 0);
            }
        } elseif (!empty($quiz['prize_text'])) {
            $prizeDisplay = $quiz['prize_text'];
        }

        // Prepare user model for emails
        require_once __DIR__ . '/User.php';
        $userModel = new User($this->conn);
        
        // Notify each participant
        foreach ($participants as $participant) {
            $userId = (int)$participant['user_id'];
            $score = (int)$participant['total_score'];
            $total = (int)$participant['total_questions'];
            $rank = $rankMap[$userId] ?? 0;
            $percentage = $total > 0 ? round(($score / $total) * 100, 1) : 0;
            
            $rankEmoji = '';
            if ($rank === 1) $rankEmoji = '🥇';
            elseif ($rank === 2) $rankEmoji = '🥈';
            elseif ($rank === 3) $rankEmoji = '🥉';

            if ($rank <= 3 && $hasPrize) {
                // Top 3 — Special winner notification
                $title = "🏆 {$rankEmoji} You finished #{$rank} in " . $quiz['title'] . "!";
                $message = "Congratulations! The challenge has ended and you're a winner!\n\n";
                $message .= "Your Rank: #{$rank} of {$totalParticipants} participants\n";
                $message .= "Your Score: {$score}/{$total} ({$percentage}%)\n";
                if ($prizeDisplay) {
                    $message .= "Prize Pool: {$prizeDisplay}\n";
                }
                $message .= "\n🎁 Click below to claim your reward. You'll need to provide your payment details so we can send your prize.";
                
                $link = SITE_URL . "/claim_reward.php?quiz_id={$quizId}";
                $this->create($userId, 'challenge_winner', $title, $message, $link);
            } else {
                // Regular participant
                $title = "🏆 Challenge Complete: " . $quiz['title'];
                $message = "The challenge has ended! Here are your final results:\n\n";
                $message .= "Your Rank: #{$rank} of {$totalParticipants} participants\n";
                $message .= "Your Score: {$score}/{$total} ({$percentage}%)\n";
                $message .= "\nAmazing effort! 🌟 Every challenge makes you sharper. Keep pushing your limits, study harder, and you'll be at the top of the leaderboard next time!";
                
                $link = SITE_URL . "/challenge_results.php?quiz_id={$quizId}";
                $this->create($userId, 'challenge_complete', $title, $message, $link);
            }

            // Dedicated corrections notification for ALL participants
            $corrTitle = "📖 Corrections Available: " . $quiz['title'];
            $corrMsg = "The challenge is over and full corrections are now unlocked! Review your detailed answers to see where you can improve for the next challenge.";
            
            // Get the attempt ID for this user to link directly to their result
            $attemptIdStmt = $this->conn->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
            $attemptIdStmt->execute([$quizId, $userId]);
            $attemptId = $attemptIdStmt->fetchColumn();
            
            if ($attemptId) {
                $corrLink = SITE_URL . "/quiz_result.php?attempt={$attemptId}";
                $this->create($userId, 'corrections_available', $corrTitle, $corrMsg, $corrLink);
            }

            // Send email too
            $userModel->getById($userId);
            if (!empty($userModel->email)) {
                require_once ROOT_PATH . '/includes/EmailService.php';
                require_once ROOT_PATH . '/includes/EmailTemplates.php';
                $svc = EmailService::fromConfig();
                if ($svc) {
                    $isWinner = ($rank <= 3 && $hasPrize);
                    $ctaUrl = $isWinner
                        ? (SITE_URL . "/claim_reward.php?quiz_id={$quizId}")
                        : (SITE_URL . "/challenge_results.php?quiz_id={$quizId}");
                    $scoreLine = "Score: {$score}/{$total} ({$percentage}%)";
                    $prizeLine = $isWinner && !empty($prizeDisplay) ? ("Prize: {$prizeDisplay}") : '';
                    $tpl = EmailTemplates::challengeResult($userModel->name ?? 'Student', $quiz['title'], (int)$rank, (int)$totalParticipants, $scoreLine, $ctaUrl, $isWinner, $prizeLine);
                    $svc->send($userModel->email, $userModel->name ?? 'Student', $tpl['subject'], $tpl['html'], $tpl['text']);
                }
            }
        }
    }
}
