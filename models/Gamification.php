<?php
/**
 * Gamification Model
 * Handles XP points, levels, quiz streaks, and badge awarding
 */
class Gamification {
    private $pdo;

    // Level thresholds: level => min points required
    const LEVELS = [
        1 => 0, 2 => 100, 3 => 300, 4 => 600, 5 => 1000,
        6 => 1500, 7 => 2100, 8 => 2800, 9 => 3600, 10 => 4500,
        11 => 5500, 12 => 6600, 13 => 7800, 14 => 9100, 15 => 10500,
        16 => 12000, 17 => 13600, 18 => 15300, 19 => 17100, 20 => 19000,
        21 => 21000, 22 => 23100, 23 => 25300, 24 => 27600, 25 => 30000,
        26 => 33000, 27 => 36500, 28 => 40500, 29 => 45000, 30 => 50000,
    ];

    // University Level Names (1-30)
    const UNI_LEVEL_NAMES = [
        1 => "Matriculant", 2 => "Freshman Initiate", 3 => "Freshman Learner", 4 => "Freshman Scholar", 5 => "Freshman Achiever",
        6 => "Sophomore Novice", 7 => "Sophomore Explorer", 8 => "Sophomore Analyst", 9 => "Sophomore Scholar", 10 => "Sophomore Specialist",
        11 => "Junior Aspirant", 12 => "Junior Researcher", 13 => "Junior Analyst", 14 => "Junior Scholar", 15 => "Junior Master",
        16 => "Senior Candidate", 17 => "Senior Researcher", 18 => "Senior Scholar", 19 => "Senior Elite", 20 => "Senior Vanguard",
        21 => "Graduate Prospect", 22 => "Graduate Scholar", 23 => "Masters Candidate", 24 => "Masters Scholar", 25 => "Fellow",
        26 => "Doctoral Candidate", 27 => "Postdoc Researcher", 28 => "Assistant Professor", 29 => "Distinguished Professor", 30 => "Professor Emeritus"
    ];

    // Secondary Level Names (1-30)
    const SEC_LEVEL_NAMES = [
        1 => "JSS1 Novice", 2 => "JSS1 Initiate", 3 => "JSS1 Learner", 4 => "JSS1 Scholar", 5 => "JSS1 Prodigy",
        6 => "JSS2 Explorer", 7 => "JSS2 Analyst", 8 => "JSS2 Achiever", 9 => "JSS2 Scholar", 10 => "JSS2 Elite",
        11 => "JSS3 Candidate", 12 => "JSS3 Prefect", 13 => "JSS3 Scholar", 14 => "JSS3 Vanguard", 15 => "JSS3 Valedictorian",
        16 => "SS1 Pioneer", 17 => "SS1 Innovator", 18 => "SS1 Specialist", 19 => "SS1 Scholar", 20 => "SS1 Commander",
        21 => "SS2 Master", 22 => "SS2 Analyst", 23 => "SS2 Scholar", 24 => "SS2 Elite", 25 => "SS2 Vanguard",
        26 => "SS3 Aspirant", 27 => "SS3 Head Boy/Girl", 28 => "SS3 Scholar", 29 => "SS3 Grandmaster", 30 => "National Scholar"
    ];

    // Badge definitions: name => [icon, description, color]
    /**
     * The badge catalogue.
     *
     * The key is what is stored in the database - it is what an earned badge
     * is written under - so it never changes. The label is only what the
     * student reads, so it can be made plainer without touching a single
     * earned badge: the rows in user_badges keep their names, and a student
     * who earned "Initiate Scholar" yesterday still has that badge today,
     * now with a name that says what it is.
     */
    const BADGES = [
        'first_quiz'       => ['🎯', 'First Quiz', '#10b981'],
        'quiz_streak_3'    => ['🔥', '3 in a Row', '#f59e0b'],
        'quiz_streak_7'    => ['⚡', 'Week Warrior', '#ef4444'],
        'quiz_streak_14'   => ['💎', 'Two Week Streak', '#8b5cf6'],
        'quiz_streak_30'   => ['👑', '30 Day Streak', '#fbbf24'],
        'perfect_score'    => ['💯', 'Perfect Score', '#3b82f6'],
        'ten_quizzes'      => ['📚', '10 Quizzes Done', '#6366f1'],
        'fifty_quizzes'    => ['🏆', '50 Quizzes Done', '#ec4899'],
        'hundred_quizzes'  => ['🌟', '100 Quizzes Done', '#14b8a6'],
        'challenge_top3'   => ['🥇', 'Challenge Top 3', '#fbbf24'],
        'challenge_winner' => ['🏅', 'Challenge Winner', '#f59e0b'],
        'level_5'          => ['⭐', 'Level 5 Scholar', '#6366f1'],
        'level_10'         => ['🔱', 'Level 10 Scholar', '#8b5cf6'],
        'early_bird'       => ['🐣', 'Early Bird', '#10b981'],
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;

        // The badge table is created here rather than assumed. A badge that
        // cannot be written is a badge that never appears, and nobody would
        // ever find out why - so the table is made if it is not there.
        require_once __DIR__ . '/../includes/schema-ensure.php';
        msb_ensure_badge_tables($this->pdo);
    }

    /**
     * Get the descriptive name for a level based on the quiz/user track
     */
    public static function getLevelName(int $level, string $track = 'both'): string {
        $lvl = max(1, min(30, $level));
        if ($track === 'university') {
            return self::UNI_LEVEL_NAMES[$lvl] ?? "Level $lvl";
        } elseif ($track === 'secondary') {
            return self::SEC_LEVEL_NAMES[$lvl] ?? "Level $lvl";
        }
        // Fallback for 'both' or generic
        return "Level $lvl: " . (self::UNI_LEVEL_NAMES[$lvl] ?? "Master");
    }

    /**
     * Award points to a user and check for new badges
     */
    public function awardQuizPoints(int $userId, int $score, int $total, int $quizId, bool $isChallenge = false): array {
        $results = ['points_earned' => 0, 'new_badges' => [], 'level_up' => false, 'new_level' => 0, 'streak' => 0];

        // Calculate points: base 10 + score-based bonus
        $percentage = $total > 0 ? ($score / $total) * 100 : 0;
        $basePoints = 10; // for completing a quiz
        $scoreBonus = round($percentage / 10); // 0-10 bonus based on %
        $challengeBonus = $isChallenge ? 15 : 0;
        $perfectBonus = ($score === $total && $total > 0) ? 20 : 0;

        $pointsEarned = $basePoints + $scoreBonus + $challengeBonus + $perfectBonus;
        $results['points_earned'] = $pointsEarned;

        // Get current user data
        $stmt = $this->pdo->prepare("SELECT points, current_streak, longest_streak, last_quiz_date FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return $results;

        $oldPoints = (int)($user['points'] ?? 0);
        $newPoints = $oldPoints + $pointsEarned;
        $oldLevel = self::getLevel($oldPoints);
        $newLevel = self::getLevel($newPoints);

        // Streak calculation
        $today = date('Y-m-d');
        $lastQuizDate = $user['last_quiz_date'];
        $currentStreak = (int)($user['current_streak'] ?? 0);
        $longestStreak = (int)($user['longest_streak'] ?? 0);

        if ($lastQuizDate === $today) {
            // Already took a quiz today, no streak change
        } elseif ($lastQuizDate === date('Y-m-d', strtotime('-1 day'))) {
            // Consecutive day
            $currentStreak++;
        } else {
            // Streak broken or first quiz
            $currentStreak = 1;
        }

        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
        }

        $results['streak'] = $currentStreak;

        // Update user
        // Increment rather than overwrite: a battle escrow deducted since the SELECT above must survive.
        $upd = $this->pdo->prepare("UPDATE users SET points = points + ?, current_streak = ?, longest_streak = ?, last_quiz_date = ? WHERE id = ?");
        $upd->execute([$pointsEarned, $currentStreak, $longestStreak, $today, $userId]);

        // Check level up
        if ($newLevel > $oldLevel) {
            $results['level_up'] = true;
            $results['new_level'] = $newLevel;
        }

        // Count total quizzes
        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?");
        $cntStmt->execute([$userId]);
        $totalQuizzes = (int)$cntStmt->fetchColumn();

        // Award badges
        $newBadges = [];

        // First quiz
        if ($totalQuizzes <= 1) {
            $newBadges[] = 'first_quiz';
        }

        // Quiz count badges
        if ($totalQuizzes >= 10) $newBadges[] = 'ten_quizzes';
        if ($totalQuizzes >= 50) $newBadges[] = 'fifty_quizzes';
        if ($totalQuizzes >= 100) $newBadges[] = 'hundred_quizzes';

        // Perfect score
        if ($score === $total && $total > 0) {
            $newBadges[] = 'perfect_score';
        }

        // Streak badges
        if ($currentStreak >= 3) $newBadges[] = 'quiz_streak_3';
        if ($currentStreak >= 7) $newBadges[] = 'quiz_streak_7';
        if ($currentStreak >= 14) $newBadges[] = 'quiz_streak_14';
        if ($currentStreak >= 30) $newBadges[] = 'quiz_streak_30';

        // Level badges
        if ($newLevel >= 5) $newBadges[] = 'level_5';
        if ($newLevel >= 10) $newBadges[] = 'level_10';

        // Actually insert badges (ignore duplicates)
        foreach ($newBadges as $badge) {
            if ($this->awardBadge($userId, $badge)) {
                $results['new_badges'][] = $badge;
            }
        }

        return $results;
    }

    /**
     * Award a badge to user. Returns true if newly earned, false if already had it.
     */
    public function awardBadge(int $userId, string $badgeName): bool {
        try {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_name) VALUES (?, ?)");
            $stmt->execute([$userId, $badgeName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user's level from points
     */
    public static function getLevel(int $points): int {
        $level = 1;
        foreach (self::LEVELS as $lvl => $minPts) {
            if ($points >= $minPts) {
                $level = $lvl;
            }
        }
        return $level;
    }

    /**
     * Get progress to next level (0-100%)
     */
    public function getLevelProgress(int $points): array {
        $currentLevel = $this->getLevel($points);
        $nextLevel = $currentLevel + 1;
        
        if (!isset(self::LEVELS[$nextLevel])) {
            return ['current' => $currentLevel, 'progress' => 100, 'points_needed' => 0, 'current_points' => $points];
        }

        $currentMin = self::LEVELS[$currentLevel];
        $nextMin = self::LEVELS[$nextLevel];
        $range = $nextMin - $currentMin;
        $inRange = $points - $currentMin;
        $progress = $range > 0 ? round(($inRange / $range) * 100) : 0;

        return [
            'current' => $currentLevel,
            'progress' => min(100, $progress),
            'points_needed' => $nextMin - $points,
            'current_points' => $points,
        ];
    }

    /**
     * Get all badges for a user
     */
    public function getUserBadges(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT badge_name, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC");
        $stmt->execute([$userId]);
        $earned = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($earned as $b) {
            $name = $b['badge_name'];
            $meta = self::BADGES[$name] ?? ['❓', $name, '#6b7280'];
            $result[] = [
                'name' => $name,
                'icon' => $meta[0],
                'label' => $meta[1],
                'color' => $meta[2],
                'earned_at' => $b['earned_at'],
            ];
        }
        return $result;
    }

    /**
     * Get gamification summary for a user
     */
    public function getUserStats(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT points, current_streak, longest_streak, last_quiz_date FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['points' => 0, 'level' => 1, 'streak' => 0, 'longest_streak' => 0, 'badges' => [], 'level_progress' => []];
        }

        $points = (int)($user['points'] ?? 0);
        
        // Check if streak is still active
        $today = date('Y-m-d');
        $lastDate = $user['last_quiz_date'];
        $streakActive = ($lastDate === $today || $lastDate === date('Y-m-d', strtotime('-1 day')));
        $currentStreak = $streakActive ? (int)$user['current_streak'] : 0;

        return [
            'points' => $points,
            'level' => $this->getLevel($points),
            'level_progress' => $this->getLevelProgress($points),
            'streak' => $currentStreak,
            'longest_streak' => (int)$user['longest_streak'],
            'badges' => $this->getUserBadges($userId),
            'last_quiz_date' => $lastDate,
        ];
    }

    /**
     * Generate a motivational notification based on user stats
     */
    public function getMotivationalMessage(int $userId): ?string {
        $stats = $this->getUserStats($userId);
        $messages = [];

        if ($stats['streak'] === 0) {
            $messages[] = "🔥 Start a quiz today to begin your streak!";
            $messages[] = "📚 Take a quiz and earn points to level up!";
        } elseif ($stats['streak'] >= 1 && $stats['streak'] < 3) {
            $messages[] = "🔥 " . $stats['streak'] . "-day streak! Keep it going for a badge!";
        } elseif ($stats['streak'] >= 3 && $stats['streak'] < 7) {
            $messages[] = "⚡ " . $stats['streak'] . "-day streak! " . (7 - $stats['streak']) . " more days for the 7-day badge!";
        } elseif ($stats['streak'] >= 7) {
            $messages[] = "💎 Amazing " . $stats['streak'] . "-day streak! You're on fire!";
        }

        $progress = $stats['level_progress'];
        if ($progress['points_needed'] > 0 && $progress['points_needed'] <= 50) {
            $messages[] = "⭐ Only " . $progress['points_needed'] . " points to reach Level " . ($progress['current'] + 1) . "!";
        }

        return !empty($messages) ? $messages[array_rand($messages)] : null;
    }

    /**
     * Add generic XP to a user (e.g., from discussions, upvotes)
     * Returns true if leveled up
     */
    public function addXP(int $userId, int $amount): bool {
        if ($amount <= 0) return false;

        $stmt = $this->pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $oldPoints = (int)($user['points'] ?? 0);
        $newPoints = $oldPoints + $amount;
        $oldLevel = self::getLevel($oldPoints);
        $newLevel = self::getLevel($newPoints);

        $upd = $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $upd->execute([$amount, $userId]);

        // Level badges check
        if ($newLevel > $oldLevel) {
            if ($newLevel >= 5) $this->awardBadge($userId, 'level_5');
            if ($newLevel >= 10) $this->awardBadge($userId, 'level_10');
            return true;
        }

        return false;
    }
}
