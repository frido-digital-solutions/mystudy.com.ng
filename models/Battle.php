<?php
// models/Battle.php
class Battle
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTables();
        $this->sweepStaleBattles();
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS battles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                challenger_id INT NOT NULL,
                opponent_id INT NOT NULL,
                quiz_id INT NOT NULL,
                status ENUM('pending', 'negotiating', 'declined', 'p1_turn', 'p2_turn', 'completed') DEFAULT 'pending',
                match_type ENUM('friendly', 'ranked') DEFAULT 'friendly',
                xp_wager INT DEFAULT 0,
                custom_timer_seconds INT DEFAULT NULL,
                start_time DATETIME DEFAULT NULL,
                decline_reason VARCHAR(255) DEFAULT NULL,
                proposed_changes TEXT DEFAULT NULL,
                challenger_score INT DEFAULT NULL,
                challenger_time_seconds INT DEFAULT NULL,
                opponent_score INT DEFAULT NULL,
                opponent_time_seconds INT DEFAULT NULL,
                winner_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                FOREIGN KEY (challenger_id) REFERENCES users(id),
                FOREIGN KEY (opponent_id) REFERENCES users(id),
                FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_battle_stats (
                user_id INT PRIMARY KEY,
                rating INT DEFAULT 1000,
                wins INT DEFAULT 0,
                losses INT DEFAULT 0,
                draws INT DEFAULT 0,
                current_streak INT DEFAULT 0,
                highest_streak INT DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Backward compatibility alterations for Phase 3
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN custom_timer_seconds INT DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN start_time DATETIME DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN decline_reason VARCHAR(255) DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN proposed_changes TEXT DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles MODIFY COLUMN status ENUM('pending', 'negotiating', 'declined', 'p1_turn', 'p2_turn', 'completed', 'open') DEFAULT 'pending'");
        } catch (Throwable $e) {
        }
        // Phase 4: Open invite link support
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN invite_code VARCHAR(20) DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles MODIFY COLUMN opponent_id INT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN is_public TINYINT(1) DEFAULT 0");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN expires_at DATETIME DEFAULT NULL");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN max_participants INT DEFAULT 1");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN accepted_count INT DEFAULT 0");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN parent_battle_id INT DEFAULT NULL");
        } catch (Throwable $e) {
        }
        // 1 = this row escrows its own pot; 0 = legacy row funded from the parent's reservation.
        try {
            $this->pdo->exec("ALTER TABLE battles ADD COLUMN escrow_mode TINYINT NOT NULL DEFAULT 0");
        } catch (Throwable $e) {
        }
    }

    /**
     * Initializes battle stats for a user if they don't exist yet
     */
    public function ensureUserStatsExist($userId)
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_battle_stats (user_id) VALUES (?)");
        $stmt->execute([$userId]);
    }

    /**
     * Get a user's battle stats
     */
    public function getUserStats($userId)
    {
        $this->ensureUserStatsExist($userId);
        $stmt = $this->pdo->prepare("SELECT * FROM user_battle_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the highest ELO players
     */
    public function getLeaderboard($limit = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.name, u.profile_picture 
            FROM user_battle_stats s 
            JOIN users u ON s.user_id = u.id 
            ORDER BY s.rating DESC 
            LIMIT ?
        ");
        // PDO needs limit as INT
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Creates a new challenge
     */
    public function createChallenge($challengerId, $opponentId, $quizId, $matchType = 'friendly', $xpWager = 0, $customTimer = null, $startTime = null)
    {
        // Validate user has enough XP if wager is set
        if ($xpWager > 0) {
            // Conditional deduction: concurrent creates cannot spend the same XP twice.
            $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
            $deduct->execute([$xpWager, $challengerId, $xpWager]);
            if ($deduct->rowCount() === 0) {
                return false; // Not enough XP
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO battles (challenger_id, opponent_id, quiz_id, status, match_type, xp_wager, custom_timer_seconds, start_time)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)
        ");
        $stmt->execute([$challengerId, $opponentId, $quizId, $matchType, $xpWager, $customTimer, $startTime]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Accept a challenge
     */
    public function acceptChallenge($battleId, $opponentId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'pending'");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle || (int) $battle['opponent_id'] !== (int) $opponentId) {
            return false;
        }

        // Opponent must also pay the escrow if ranked
        $wager = (int) $battle['xp_wager'];

        try {
            $this->pdo->beginTransaction();

            // Claim the row first so two simultaneous accepts cannot both take escrow.
            $claim = $this->pdo->prepare("UPDATE battles SET status = 'p1_turn' WHERE id = ? AND status = 'pending'");
            $claim->execute([$battleId]);
            if ($claim->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            if ($wager > 0) {
                $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
                $deduct->execute([$wager, $opponentId, $wager]);
                if ($deduct->rowCount() === 0) {
                    $this->pdo->rollBack();
                    throw new Exception("insufficient_xp"); // Not enough XP to match the challenger's wager
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getMessage() === 'insufficient_xp') {
                throw $e;
            }
            log_error('acceptChallenge failed', ['battle_id' => $battleId, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Decline a challenge
     */
    public function declineChallenge($battleId, $opponentId, $reason = null)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'pending'");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle || (int) $battle['opponent_id'] !== (int) $opponentId) {
            return false;
        }

        // Refund the challenger's escrow
        $wager = (int) $battle['xp_wager'];
        if ($wager > 0) {
            $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $battle['challenger_id']]);
        }

        $stmt = $this->pdo->prepare("UPDATE battles SET status = 'declined', decline_reason = ? WHERE id = ? AND opponent_id = ? AND status = 'pending'");
        return $stmt->execute([$reason, $battleId, $opponentId]);
    }

    /**
     * Propose a Change (Negotiation phase)
     */
    public function proposeChange($battleId, $opponentId, $proposedJson)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'pending'");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle || (int) $battle['opponent_id'] !== (int) $opponentId) {
            return false;
        }

        // Set status to negotiating, send back to challenger
        $stmt = $this->pdo->prepare("UPDATE battles SET status = 'negotiating', proposed_changes = ? WHERE id = ?");
        return $stmt->execute([$proposedJson, $battleId]);
    }

    /**
     * Challenger Accepts proposed changes
     */
    public function acceptNegotiation($battleId, $challengerId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'negotiating'");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle || (int) $battle['challenger_id'] !== (int) $challengerId) {
            return false;
        }

        $proposed = json_decode($battle['proposed_changes'], true) ?: [];
        $newWager = isset($proposed['xp_wager']) ? (int) $proposed['xp_wager'] : (int) $battle['xp_wager'];
        $oldWager = (int) $battle['xp_wager'];
        $newTimer = isset($proposed['custom_timer_seconds']) ? (int) $proposed['custom_timer_seconds'] : $battle['custom_timer_seconds'];

        $wagerDiff = $newWager - $oldWager;

        try {
            $this->pdo->beginTransaction();

            // Challenger logic (Escrow diff)
            if ($wagerDiff > 0) {
                // Challenger needs to pay MORE into escrow
                $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
                $deduct->execute([$wagerDiff, $challengerId, $wagerDiff]);
                if ($deduct->rowCount() === 0) {
                    throw new Exception("insufficient_xp"); // Challenger can't afford the increase
                }
            } else if ($wagerDiff < 0) {
                // Return the difference to challenger
                $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([abs($wagerDiff), $challengerId]);
            }

            // Opponent logic (Needs to pay full new amount into Escrow)
            if ($newWager > 0) {
                $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
                $deduct->execute([$newWager, $battle['opponent_id'], $newWager]);
                if ($deduct->rowCount() === 0) {
                    throw new Exception("opponent_insufficient_xp"); // Opponent proposed something they can't afford (weird but possible if they lost XP since proposing)
                }
            }

            // Apply updates
            $stmt = $this->pdo->prepare("UPDATE battles SET status = 'p1_turn', xp_wager = ?, custom_timer_seconds = ?, proposed_changes = NULL WHERE id = ? AND status = 'negotiating'");
            $stmt->execute([$newWager, $newTimer, $battleId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("battle_not_negotiating");
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            // Roll back once here only; the caller must still see why the accept failed.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (in_array($e->getMessage(), ['insufficient_xp', 'opponent_insufficient_xp'], true)) {
                throw $e;
            }
            log_error('acceptNegotiation failed', ['battle_id' => $battleId, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Challenger Declines (Cancels) proposed changes
     */
    public function declineNegotiation($battleId, $challengerId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'negotiating'");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle || (int) $battle['challenger_id'] !== (int) $challengerId) {
            return false; // Error or invalid access
        }

        // Refund the challenger's ORIGINAL escrow wager
        $wager = (int) $battle['xp_wager'];
        if ($wager > 0) {
            $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $challengerId]);
        }

        $stmt = $this->pdo->prepare("UPDATE battles SET status = 'declined', decline_reason = 'Challenger declined the counter-offer.' WHERE id = ?");
        return $stmt->execute([$battleId]);
    }

    /**
     * Get a specific battle by ID
     */
    public function getBattle($battleId)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   c.name as challenger_name, c.profile_picture as challenger_avatar,
                   c.points as challenger_points, c.user_type as challenger_user_type,
                   o.name as opponent_name, o.profile_picture as opponent_avatar,
                   o.points as opponent_points, o.user_type as opponent_user_type,
                   q.title as quiz_title, q.subject as quiz_subject,
                   cs.rating as challenger_rating,
                   os.rating as opponent_rating
            FROM battles b
            JOIN users c ON b.challenger_id = c.id
            LEFT JOIN users o ON b.opponent_id = o.id
            JOIN quizzes q ON b.quiz_id = q.id
            LEFT JOIN user_battle_stats cs ON b.challenger_id = cs.user_id
            LEFT JOIN user_battle_stats os ON b.opponent_id = os.user_id
            WHERE b.id = ?
        ");
        $stmt->execute([$battleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent batles involving user
     */
    public function getUserBattles($userId, $limit = 20)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   c.name as challenger_name, c.profile_picture as challenger_avatar,
                   o.name as opponent_name, o.profile_picture as opponent_avatar,
                   q.title as quiz_title
            FROM battles b
            JOIN users c ON b.challenger_id = c.id
            LEFT JOIN users o ON b.opponent_id = o.id
            JOIN quizzes q ON b.quiz_id = q.id
            WHERE (b.challenger_id = ? OR b.opponent_id = ?)
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending challenges SENT TO user
     */
    public function getPendingChallenges($opponentId)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, c.name as challenger_name, c.profile_picture as challenger_avatar, q.title as quiz_title
            FROM battles b
            JOIN users c ON b.challenger_id = c.id
            JOIN quizzes q ON b.quiz_id = q.id
            WHERE b.opponent_id = ? AND b.status = 'pending'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$opponentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== OPEN INVITE LINK METHODS ==========

    /**
     * Create an open challenge (no specific opponent)
     */
    public function createOpenChallenge($challengerId, $quizId, $matchType = 'friendly', $xpWager = 0, $customTimer = null, $startTime = null, $isPublic = false, $expiresAt = null, $maxParticipants = 1)
    {
        if ($xpWager > 0) {
            // One reservation for the whole invite; refunded when the invite closes.
            $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
            $deduct->execute([$xpWager, $challengerId, $xpWager]);
            if ($deduct->rowCount() === 0) {
                return false;
            }
        }

        $inviteCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        $stmt = $this->pdo->prepare("
            INSERT INTO battles (challenger_id, opponent_id, quiz_id, status, match_type, xp_wager, custom_timer_seconds, start_time, invite_code, is_public, expires_at, max_participants, accepted_count, escrow_mode)
            VALUES (?, NULL, ?, 'open', ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ");
        $stmt->execute([$challengerId, $quizId, $matchType, $xpWager, $customTimer, $startTime, $inviteCode, $isPublic ? 1 : 0, $expiresAt, $maxParticipants]);
        return $inviteCode;
    }

    /**
     * Get an open challenge by its invite code
     */
    public function getOpenChallenge($inviteCode)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   c.name as challenger_name, c.profile_picture as challenger_avatar, c.points as challenger_points,
                   q.title as quiz_title
            FROM battles b
            JOIN users c ON b.challenger_id = c.id
            JOIN quizzes q ON b.quiz_id = q.id
            WHERE b.invite_code = ?
        ");
        $stmt->execute([$inviteCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Accept an open challenge
     */
    public function acceptOpenChallenge($battleId, $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'open' AND opponent_id IS NULL");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle)
            return false;
        if ((int) $battle['challenger_id'] === (int) $userId)
            return false;

        // Prevent users from accepting the same open battle twice
        $chk = $this->pdo->prepare("SELECT id FROM battles WHERE parent_battle_id = ? AND opponent_id = ?");
        $chk->execute([$battleId, $userId]);
        if ($chk->fetchColumn())
            return false;

        // Check if max participants already reached
        $maxP = (int) ($battle['max_participants'] ?? 1);
        $accepted = (int) ($battle['accepted_count'] ?? 0);
        if ($accepted >= $maxP)
            return false;

        // Check start_time
        if (!empty($battle['start_time']) && strtotime($battle['start_time']) > time())
            return false;

        // An expired invite can no longer be joined; the sweep returns the creator's reservation.
        if (!empty($battle['expires_at']) && strtotime($battle['expires_at']) <= time())
            return false;

        $wager = (int) $battle['xp_wager'];
        $challengerId = (int) $battle['challenger_id'];

        try {
            $this->pdo->beginTransaction();

            // Take the seat atomically so concurrent accepts cannot overshoot max_participants.
            $seat = $this->pdo->prepare("UPDATE battles SET accepted_count = accepted_count + 1 WHERE id = ? AND status = 'open' AND accepted_count < max_participants");
            $seat->execute([$battleId]);
            if ($seat->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            if ($wager > 0) {
                // Every child escrows its own full pot (wager from each side). The creator's
                // single reservation on the parent is refunded when the parent closes, so the
                // books balance: -W (create) -2NW (accepts) +W (close) +2NW (payouts) = 0.
                $deduct = $this->pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
                $deduct->execute([$wager, $userId, $wager]);
                if ($deduct->rowCount() === 0) {
                    $this->pdo->rollBack();
                    throw new Exception("insufficient_xp");
                }
                $deduct->execute([$wager, $challengerId, $wager]);
                if ($deduct->rowCount() === 0) {
                    $this->pdo->rollBack();
                    throw new Exception("challenger_insufficient_xp");
                }
            }

            // Create a new battle row for this specific challenger vs acceptor
            $stmt = $this->pdo->prepare("
                INSERT INTO battles (challenger_id, opponent_id, quiz_id, status, match_type, xp_wager, custom_timer_seconds, start_time, invite_code, parent_battle_id, escrow_mode)
                VALUES (?, ?, ?, 'p1_turn', ?, ?, ?, ?, NULL, ?, 1)
            ");
            $stmt->execute([
                $challengerId,
                $userId,
                (int) $battle['quiz_id'],
                $battle['match_type'],
                $wager,
                $battle['custom_timer_seconds'],
                $battle['start_time'],
                $battleId
            ]);
            $newBattleId = (int) $this->pdo->lastInsertId();

            // Last seat taken: close the parent and return the reservation exactly once.
            $close = $this->pdo->prepare("UPDATE battles SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'open' AND accepted_count >= max_participants");
            $close->execute([$battleId]);
            if ($close->rowCount() > 0 && $wager > 0 && $this->reservationIsRefundable($battleId)) {
                $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $challengerId]);
            }

            $this->pdo->commit();
            return $newBattleId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (in_array($e->getMessage(), ['insufficient_xp', 'challenger_insufficient_xp'], true)) {
                throw $e;
            }
            log_error('acceptOpenChallenge failed', ['battle_id' => $battleId, 'user_id' => $userId, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Propose changes to an open challenge
     */
    public function proposeOpenChange($battleId, $userId, $proposedJson)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'open' AND opponent_id IS NULL");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$battle)
            return false;
        if ((int) $battle['challenger_id'] === (int) $userId)
            return false;

        $stmt = $this->pdo->prepare("UPDATE battles SET opponent_id = ?, status = 'negotiating', proposed_changes = ? WHERE id = ?");
        return $stmt->execute([$userId, $proposedJson, $battleId]);
    }

    /**
     * Get all open challenges created by user
     */
    public function getUserOpenChallenges($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, q.title as quiz_title
            FROM battles b
            JOIN quizzes q ON b.quiz_id = q.id
            WHERE b.challenger_id = ? AND b.status = 'open' AND b.opponent_id IS NULL
              AND (b.expires_at IS NULL OR b.expires_at > NOW())
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all public open challenges for the Battle Hub
     */
    public function getPublicOpenChallenges($limit = 30)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   c.name as challenger_name, c.profile_picture as challenger_avatar,
                   q.title as quiz_title,
                   bs.rating as challenger_rating, bs.wins as challenger_wins, bs.losses as challenger_losses
            FROM battles b
            JOIN users c ON b.challenger_id = c.id
            JOIN quizzes q ON b.quiz_id = q.id
            LEFT JOIN user_battle_stats bs ON b.challenger_id = bs.user_id
            WHERE b.status = 'open' AND b.opponent_id IS NULL AND b.is_public = 1
                  AND (b.expires_at IS NULL OR b.expires_at > NOW())
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record an attempt score & time. 
     * If this finishes the battle, resolve it.
     */
    public function recordAttempt($battleId, $userId, $score, $timeSeconds)
    {
        $battle = $this->getBattle($battleId);
        if (!$battle)
            return false;

        // A turn may only be recorded while the arena is open, and only once per player.
        if (!in_array($battle['status'], ['p1_turn', 'p2_turn'], true)) {
            return false;
        }

        $isChallenger = ((int) $battle['challenger_id'] === (int) $userId);
        $isOpponent = ((int) $battle['opponent_id'] === (int) $userId);

        if (!$isChallenger && !$isOpponent)
            return false;

        if ($isChallenger) {
            if ($battle['challenger_score'] !== null)
                return false;
            $stmt = $this->pdo->prepare("UPDATE battles SET challenger_score = ?, challenger_time_seconds = ? WHERE id = ? AND challenger_score IS NULL AND status IN ('p1_turn','p2_turn')");
            $stmt->execute([(int) $score, (int) $timeSeconds, $battleId]);
            if ($stmt->rowCount() === 0)
                return false;
        } else {
            if ($battle['opponent_score'] !== null)
                return false;
            $stmt = $this->pdo->prepare("UPDATE battles SET opponent_score = ?, opponent_time_seconds = ? WHERE id = ? AND opponent_score IS NULL AND status IN ('p1_turn','p2_turn')");
            $stmt->execute([(int) $score, (int) $timeSeconds, $battleId]);
            if ($stmt->rowCount() === 0)
                return false;
        }

        // Advance state machine
        if ($battle['status'] === 'p1_turn') {
            $stmt = $this->pdo->prepare("UPDATE battles SET status = 'p2_turn' WHERE id = ? AND status = 'p1_turn'");
            $stmt->execute([$battleId]);
        }

        // Fetch freshly updated battle to see if it's done
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ?");
        $stmt->execute([$battleId]);
        $updatedBattle = $stmt->fetch(PDO::FETCH_ASSOC);

        // If both have scores, resolve the battle
        if ($updatedBattle['challenger_score'] !== null && $updatedBattle['opponent_score'] !== null) {
            $this->resolveBattle($battleId);
        } else {
            // One player finished their turn, other hasn't
            try {
                require_once __DIR__ . '/Notification.php';
                $notif = new Notification($this->pdo);

                // Re-fetch full battle to get quiz title and names
                $fullBattle = $this->getBattle($battleId);
                if ($fullBattle) {
                    $quizTitle = $fullBattle['quiz_title'] ?? 'a quiz';
                    $isC = ((int) $fullBattle['challenger_id'] === (int) $userId);
                    $playerName = $isC ? $fullBattle['challenger_name'] : $fullBattle['opponent_name'];
                    $otherId = $isC ? $fullBattle['opponent_id'] : $fullBattle['challenger_id'];

                    if (!empty($otherId)) {
                        $notif->create(
                            (int) $otherId,
                            'battle_turn',
                            "⚔️ Your Turn!",
                            "{$playerName} has completed their turn in the battle on \"{$quizTitle}\". Now it's your turn to strike!",
                            SITE_URL . "/battle_arena.php#battle-{$battleId}"
                        );
                        // Send turn email
                        try {
                            require_once ROOT_PATH . '/includes/email-config.php';
                            sendBattleEmail($this->pdo, (int) $otherId, "⚔️ Your Turn!", "{$playerName} has completed their turn in the battle on \"{$quizTitle}\". Now it's your turn to strike!", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'turn');
                        } catch (Throwable $emailErr) {
                            error_log('Battle turn email error: ' . $emailErr->getMessage());
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log("Turn notification error: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Resolves the battle, calculates winner, updates ELO & XP wagers
     */
    private function resolveBattle($battleId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ?");
        $stmt->execute([$battleId]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$b || $b['status'] === 'completed' || $b['opponent_id'] === null)
            return;

        $cScore = (int) $b['challenger_score'];
        $cTime = (int) $b['challenger_time_seconds'];
        $oScore = (int) $b['opponent_score'];
        $oTime = (int) $b['opponent_time_seconds'];

        $winnerId = null;
        $loserId = null;
        $isDraw = false;

        if ($cScore > $oScore) {
            $winnerId = $b['challenger_id'];
            $loserId = $b['opponent_id'];
        } else if ($oScore > $cScore) {
            $winnerId = $b['opponent_id'];
            $loserId = $b['challenger_id'];
        } else {
            // Same score = Draw (no time tiebreaker for battle quizzes)
            $isDraw = true;
        }

        try {
            $this->pdo->beginTransaction();

            // Claim the resolution first so a concurrent call cannot pay the pot twice.
            $stmt = $this->pdo->prepare("UPDATE battles SET status = 'completed', completed_at = NOW(), winner_id = ? WHERE id = ? AND status <> 'completed'");
            $stmt->execute([$winnerId, $battleId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return;
            }

            $this->ensureUserStatsExist($b['challenger_id']);
            $this->ensureUserStatsExist($b['opponent_id']);

            if ($isDraw) {
                // Just log draw
                $this->pdo->prepare("UPDATE user_battle_stats SET draws = draws + 1 WHERE user_id IN (?, ?)")->execute([$b['challenger_id'], $b['opponent_id']]);

                // Refund Escrow to both players
                $wager = (int) $b['xp_wager'];
                if ($wager > 0) {
                    $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $b['challenger_id']]);
                    $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $b['opponent_id']]);
                }
            } else {
                // Update Winner Stats
                $this->pdo->prepare("
                    UPDATE user_battle_stats 
                    SET wins = wins + 1, 
                        current_streak = current_streak + 1,
                        highest_streak = GREATEST(highest_streak, current_streak + 1)
                    WHERE user_id = ?
                ")->execute([$winnerId]);

                // Update Loser Stats
                $this->pdo->prepare("
                    UPDATE user_battle_stats 
                    SET losses = losses + 1, 
                        current_streak = 0
                    WHERE user_id = ?
                ")->execute([$loserId]);

                // Handle ELO (Ranked Only)
                if ($b['match_type'] === 'ranked') {
                    $cStats = $this->getUserStats($b['challenger_id']);
                    $oStats = $this->getUserStats($b['opponent_id']);

                    $cRating = (int) $cStats['rating'];
                    $oRating = (int) $oStats['rating'];

                    $kFactor = 32;
                    $expectedC = 1 / (1 + pow(10, ($oRating - $cRating) / 400));
                    $expectedO = 1 / (1 + pow(10, ($cRating - $oRating) / 400));

                    $actualC = ($winnerId == $b['challenger_id']) ? 1 : 0;
                    $actualO = ($winnerId == $b['opponent_id']) ? 1 : 0;

                    $newC = $cRating + round($kFactor * ($actualC - $expectedC));
                    $newO = $oRating + round($kFactor * ($actualO - $expectedO));

                    $this->pdo->prepare("UPDATE user_battle_stats SET rating = ? WHERE user_id = ?")->execute([$newC, $b['challenger_id']]);
                    $this->pdo->prepare("UPDATE user_battle_stats SET rating = ? WHERE user_id = ?")->execute([$newO, $b['opponent_id']]);
                }

                // Handle XP Wager Transfer (Escrow Resolution)
                $wager = (int) $b['xp_wager'];
                if ($wager > 0) {
                    // Both players already paid their wager into escrow. 
                    // Winner gets double the wager (their money strictly back + the opponent's wager).
                    // Loser gets nothing (they already paid).
                    $pot = $wager * 2;
                    $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$pot, $winnerId]);
                }
            }

            $this->pdo->commit();

            // --- Send Battle Result Notifications ---
            try {
                require_once __DIR__ . '/Notification.php';
                $notif = new Notification($this->pdo);

                // Fetch full battle with names for notification
                $fullBattle = $this->getBattle($battleId);
                $quizTitle = $fullBattle ? $fullBattle['quiz_title'] : 'a quiz';
                $challengerName = $fullBattle ? $fullBattle['challenger_name'] : 'Challenger';
                $opponentName = $fullBattle ? $fullBattle['opponent_name'] : 'Opponent';

                $wager = (int) $b['xp_wager'];

                if ($isDraw) {
                    $refundMsg = $wager > 0 ? " Your {$wager} XP wager has been refunded." : '';
                    // Notify both players of draw
                    $notif->create(
                        (int) $b['challenger_id'],
                        'battle_result',
                        "🤝 Battle Draw!",
                        "Your battle vs {$opponentName} on \"{$quizTitle}\" ended in a draw! Score: {$cScore} - {$oScore}.{$refundMsg}",
                        SITE_URL . "/quiz_battle_result.php?id={$battleId}"
                    );
                    $notif->create(
                        (int) $b['opponent_id'],
                        'battle_result',
                        "🤝 Battle Draw!",
                        "Your battle vs {$challengerName} on \"{$quizTitle}\" ended in a draw! Score: {$oScore} - {$cScore}.{$refundMsg}",
                        SITE_URL . "/quiz_battle_result.php?id={$battleId}"
                    );
                } else {
                    $pot = $wager * 2;
                    $winnerName = ($winnerId == $b['challenger_id']) ? $challengerName : $opponentName;
                    $loserName = ($loserId == $b['challenger_id']) ? $challengerName : $opponentName;
                    $winnerScore = ($winnerId == $b['challenger_id']) ? $cScore : $oScore;
                    $loserScore = ($loserId == $b['challenger_id']) ? $cScore : $oScore;

                    $winXpMsg = $wager > 0 ? " You won the {$pot} XP pot!" : '';
                    $loseXpMsg = $wager > 0 ? " You lost your {$wager} XP wager." : '';

                    // ELO info for ranked
                    $eloWinMsg = '';
                    $eloLoseMsg = '';
                    if ($b['match_type'] === 'ranked') {
                        $eloWinMsg = ' Your ELO rating has increased!';
                        $eloLoseMsg = ' Your ELO rating has decreased.';
                    }

                    // Notify winner
                    $notif->create(
                        (int) $winnerId,
                        'battle_result',
                        "🏆 Victory!",
                        "You defeated {$loserName} in the battle on \"{$quizTitle}\"! Score: {$winnerScore} - {$loserScore}.{$winXpMsg}{$eloWinMsg}",
                        SITE_URL . "/quiz_battle_result.php?id={$battleId}"
                    );

                    // Notify loser
                    $notif->create(
                        (int) $loserId,
                        'battle_result',
                        "😞 Defeat",
                        "You were defeated by {$winnerName} in the battle on \"{$quizTitle}\". Score: {$loserScore} - {$winnerScore}.{$loseXpMsg}{$eloLoseMsg}",
                        SITE_URL . "/quiz_battle_result.php?id={$battleId}"
                    );
                }
            } catch (Throwable $notifErr) {
                // Non-critical: don't break resolution if notification fails
                error_log("Battle notification error: " . $notifErr->getMessage());
            }

            // --- Send Battle Result Emails ---
            try {
                require_once ROOT_PATH . '/includes/email-config.php';
                $fb = $this->getBattle($battleId);
                $qt = $fb ? $fb['quiz_title'] : 'a quiz';
                $cn = $fb ? $fb['challenger_name'] : 'Challenger';
                $on = $fb ? $fb['opponent_name'] : 'Opponent';
                $w = (int) $b['xp_wager'];

                if ($isDraw) {
                    $refund = $w > 0 ? " Your {$w} XP wager has been refunded." : '';
                    sendBattleEmail($this->pdo, (int) $b['challenger_id'], "🤝 Battle Draw!", "Your battle vs {$on} on \"{$qt}\" ended in a draw! Score: {$cScore} - {$oScore}.{$refund}", SITE_URL . "/quiz_battle_result.php?id={$battleId}", 'result');
                    sendBattleEmail($this->pdo, (int) $b['opponent_id'], "🤝 Battle Draw!", "Your battle vs {$cn} on \"{$qt}\" ended in a draw! Score: {$oScore} - {$cScore}.{$refund}", SITE_URL . "/quiz_battle_result.php?id={$battleId}", 'result');
                } else {
                    $pot = $w * 2;
                    $wn = ($winnerId == $b['challenger_id']) ? $cn : $on;
                    $ln = ($loserId == $b['challenger_id']) ? $cn : $on;
                    $ws = ($winnerId == $b['challenger_id']) ? $cScore : $oScore;
                    $ls = ($loserId == $b['challenger_id']) ? $cScore : $oScore;
                    $winXp = $w > 0 ? " You won the {$pot} XP pot!" : '';
                    $loseXp = $w > 0 ? " You lost your {$w} XP wager." : '';
                    $eloW = ($b['match_type'] === 'ranked') ? ' Your ELO rating has increased!' : '';
                    $eloL = ($b['match_type'] === 'ranked') ? ' Your ELO rating has decreased.' : '';

                    sendBattleEmail($this->pdo, (int) $winnerId, "🏆 Victory!", "You defeated {$ln} in the battle on \"{$qt}\"! Score: {$ws} - {$ls}.{$winXp}{$eloW}", SITE_URL . "/quiz_battle_result.php?id={$battleId}", 'result');
                    sendBattleEmail($this->pdo, (int) $loserId, "😞 Defeat", "You were defeated by {$wn} in the battle on \"{$qt}\". Score: {$ls} - {$ws}.{$loseXp}{$eloL}", SITE_URL . "/quiz_battle_result.php?id={$battleId}", 'result');
                }
            } catch (Throwable $emailErr) {
                error_log('Battle result email error: ' . $emailErr->getMessage());
            }

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            log_error("Failed to resolve battle", ['battle_id' => $battleId, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Code-only stand-in for a cron job: closes battles that can never finish on their own.
     * Throttled to one pass per session per 5 minutes, a handful of rows per pass.
     */
    private function sweepStaleBattles(): void
    {
        if ($this->pdo->inTransaction()) {
            return;
        }

        $now = time();
        if (isset($_SESSION)) {
            if (!empty($_SESSION['_last_battle_sweep']) && ($now - (int) $_SESSION['_last_battle_sweep']) < 300) {
                return;
            }
            $_SESSION['_last_battle_sweep'] = $now;
        }

        try {
            $rows = $this->pdo->query("
                SELECT b.*, q.duration_seconds AS quiz_duration, q.title AS quiz_title
                FROM battles b
                LEFT JOIN quizzes q ON q.id = b.quiz_id
                WHERE (b.status IN ('p1_turn','p2_turn') AND b.created_at < (NOW() - INTERVAL 1 DAY))
                   OR (b.status = 'open' AND b.opponent_id IS NULL
                        AND ((b.expires_at IS NOT NULL AND b.expires_at < NOW())
                             OR (b.expires_at IS NULL AND b.created_at < (NOW() - INTERVAL 7 DAY))))
                   OR (b.status IN ('pending','negotiating') AND b.created_at < (NOW() - INTERVAL 7 DAY))
                ORDER BY b.created_at ASC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log_error('Battle sweep query failed', ['err' => $e->getMessage()]);
            return;
        }

        foreach ($rows as $row) {
            try {
                if ($row['status'] === 'open') {
                    $this->expireOpenBattle($row);
                } elseif ($row['status'] === 'pending' || $row['status'] === 'negotiating') {
                    $this->expireUnacceptedBattle($row);
                } else {
                    $this->forfeitStaleBattle($row);
                }
            } catch (Throwable $e) {
                log_error('Battle sweep failed', ['battle_id' => $row['id'], 'err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Expired (or never-dated, 7 day old) open invite: close it and return the creator's
     * reservation exactly once. Child battles keep their own escrow and resolve separately.
     */
    private function expireOpenBattle(array $b): void
    {
        $battleId = (int) $b['id'];
        $deadline = !empty($b['expires_at'])
            ? strtotime($b['expires_at'])
            : strtotime($b['created_at']) + (7 * 86400);
        if (time() < $deadline) {
            return;
        }

        $wager = (int) $b['xp_wager'];
        $challengerId = (int) $b['challenger_id'];
        $refunded = false;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE battles SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'open'");
            $stmt->execute([$battleId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return;
            }
            if ($wager > 0 && $this->reservationIsRefundable($battleId)) {
                $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $challengerId]);
                $refunded = true;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $title = $b['quiz_title'] ?? 'a quiz';
        $refund = $refunded ? " Your {$wager} XP reservation has been refunded." : '';
        $this->notifyBattleUser($challengerId, 'battle_declined', '⌛ Open Challenge Expired', "Your open challenge on \"{$title}\" closed before every seat was filled.{$refund}", SITE_URL . "/battle_arena.php#battle-{$battleId}");
    }

    /**
     * Invites created before per-child escrow paid for their first child out of the creator's
     * reservation, so that reservation must not be handed back a second time.
     */
    private function reservationIsRefundable(int $battleId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM battles WHERE parent_battle_id = ? AND escrow_mode = 0");
        $stmt->execute([$battleId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * A direct challenge nobody answered: cancel it and return the challenger's escrow.
     */
    private function expireUnacceptedBattle(array $b): void
    {
        $battleId = (int) $b['id'];
        if (time() < strtotime($b['created_at']) + (7 * 86400)) {
            return;
        }

        $wager = (int) $b['xp_wager'];
        $challengerId = (int) $b['challenger_id'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE battles SET status = 'declined', decline_reason = 'Challenge expired without a response.', completed_at = NOW() WHERE id = ? AND status IN ('pending','negotiating')");
            $stmt->execute([$battleId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return;
            }
            if ($wager > 0) {
                $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$wager, $challengerId]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $title = $b['quiz_title'] ?? 'a quiz';
        $refund = $wager > 0 ? " Your {$wager} XP escrow has been refunded." : '';
        $this->notifyBattleUser($challengerId, 'battle_declined', '⌛ Challenge Expired', "Your challenge on \"{$title}\" expired without a response.{$refund}", SITE_URL . "/battle_arena.php#battle-{$battleId}");
        if (!empty($b['opponent_id'])) {
            $this->notifyBattleUser((int) $b['opponent_id'], 'battle_declined', '⌛ Challenge Expired', "The challenge on \"{$title}\" expired because it was not answered in time.", SITE_URL . "/battle_arena.php#battle-{$battleId}");
        }
    }

    /**
     * An arena that stayed open long past both turns: forfeit whoever never submitted.
     */
    private function forfeitStaleBattle(array $b): void
    {
        $battleId = (int) $b['id'];
        if ($b['opponent_id'] === null) {
            return;
        }

        $window = (int) ($b['custom_timer_seconds'] ?: ($b['quiz_duration'] ?: 900));
        if ($window <= 0) {
            $window = 900;
        }
        $base = max(strtotime($b['created_at']), !empty($b['start_time']) ? strtotime($b['start_time']) : 0);
        // Each player gets the full window, plus a day of grace before the arena is closed.
        if (time() < $base + (2 * $window) + 86400) {
            return;
        }

        $cNull = ($b['challenger_score'] === null);
        $oNull = ($b['opponent_score'] === null);
        $wager = (int) $b['xp_wager'];

        if (!$cNull && !$oNull) {
            // Both turns exist but resolution never ran; finish it normally.
            $this->resolveBattle($battleId);
            return;
        }

        if ($cNull && $oNull) {
            // Neither player showed up: return both escrows, no winner and no stats.
            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("UPDATE battles SET status = 'completed', completed_at = NOW(), winner_id = NULL, challenger_score = 0, challenger_time_seconds = ?, opponent_score = 0, opponent_time_seconds = ? WHERE id = ? AND status IN ('p1_turn','p2_turn') AND challenger_score IS NULL AND opponent_score IS NULL");
                $stmt->execute([$window, $window, $battleId]);
                if ($stmt->rowCount() === 0) {
                    $this->pdo->rollBack();
                    return;
                }
                if ($wager > 0) {
                    $refund = $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                    $refund->execute([$wager, $b['challenger_id']]);
                    $refund->execute([$wager, $b['opponent_id']]);
                }
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            $title = $b['quiz_title'] ?? 'a quiz';
            $refund = $wager > 0 ? " Your {$wager} XP wager has been refunded." : '';
            $msg = "Your battle on \"{$title}\" expired because neither player took their turn.{$refund}";
            $link = SITE_URL . "/quiz_battle_result.php?id={$battleId}";
            $this->notifyBattleUser((int) $b['challenger_id'], 'battle_result', '⌛ Battle Expired', $msg, $link);
            $this->notifyBattleUser((int) $b['opponent_id'], 'battle_result', '⌛ Battle Expired', $msg, $link);
            return;
        }

        // Exactly one player never submitted: they forfeit, the other takes the pot.
        $side = $cNull ? 'challenger' : 'opponent';
        $stmt = $this->pdo->prepare("UPDATE battles SET {$side}_score = 0, {$side}_time_seconds = ? WHERE id = ? AND {$side}_score IS NULL AND status IN ('p1_turn','p2_turn')");
        $stmt->execute([$window, $battleId]);
        if ($stmt->rowCount() > 0) {
            $this->resolveBattle($battleId);
        }
    }

    private function notifyBattleUser(int $userId, string $type, string $title, string $msg, string $link): void
    {
        try {
            require_once __DIR__ . '/Notification.php';
            $notif = new Notification($this->pdo);
            $notif->create($userId, $type, $title, $msg, $link);
        } catch (Throwable $e) {
            log_error('Battle sweep notification failed', ['user_id' => $userId, 'err' => $e->getMessage()]);
        }
    }
}
