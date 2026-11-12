<?php

class Message {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    /**
     * Auto-create messaging tables if they don't exist
     */
    public function ensureTables() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `conversations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_one_id` INT NOT NULL,
                `user_two_id` INT NOT NULL,
                `last_message_id` INT DEFAULT NULL,
                `last_message_at` DATETIME DEFAULT NULL,
                `deleted_by_user_one` TINYINT(1) DEFAULT 0,
                `deleted_by_user_two` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_pair` (`user_one_id`, `user_two_id`),
                INDEX `idx_user_one` (`user_one_id`),
                INDEX `idx_user_two` (`user_two_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `sender_id` INT NOT NULL,
                `body` TEXT NOT NULL,
                `is_edited` TINYINT(1) DEFAULT 0,
                `deleted_for_sender` TINYINT(1) DEFAULT 0,
                `deleted_for_everyone` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_conv_created` (`conversation_id`, `created_at`),
                INDEX `idx_sender` (`sender_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Add reply_to_id column if it doesn't exist (safe migration)
        try {
            $this->pdo->exec("ALTER TABLE `messages` ADD COLUMN `reply_to_id` INT DEFAULT NULL AFTER `body`");
        } catch (PDOException $e) {
            // Column already exists — ignore
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_blocks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `blocker_id` INT NOT NULL,
                `blocked_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_block` (`blocker_id`, `blocked_id`),
                INDEX `idx_blocker` (`blocker_id`),
                INDEX `idx_blocked` (`blocked_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `message_reads` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `last_read_message_id` INT DEFAULT NULL,
                `read_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_read` (`conversation_id`, `user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `typing_status` (
                `user_id` INT NOT NULL,
                `partner_id` INT NOT NULL,
                `last_typing_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `partner_id`),
                INDEX `idx_partner_typing` (`partner_id`, `last_typing_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `message_hidden_for_user` (
                `message_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`message_id`, `user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // ─── Typing Indicator ────────────────────────────────────────

    public function setTyping(int $userId, int $partnerId): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO typing_status (user_id, partner_id, last_typing_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_typing_at = NOW()
        ");
        $stmt->execute([$userId, $partnerId]);
    }

    public function stopTyping(int $userId, int $partnerId): void {
        $stmt = $this->pdo->prepare("DELETE FROM typing_status WHERE user_id = ? AND partner_id = ?");
        $stmt->execute([$userId, $partnerId]);
    }

    /** Returns true if partner has typed to this user within last 5 seconds */
    public function isPartnerTyping(int $userId, int $partnerId): bool {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM typing_status 
            WHERE user_id = ? AND partner_id = ? AND last_typing_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ");
        $stmt->execute([$partnerId, $userId]);
        return $stmt->rowCount() > 0;
    }

    // ─── Conversation Management ──────────────────────────────────

    /**
     * Get or create a 1-on-1 conversation between two users.
     * Always stores the lower ID as user_one_id for consistency.
     */
    public function getOrCreateConversation(int $userId1, int $userId2): array {
        $low = min($userId1, $userId2);
        $high = max($userId1, $userId2);

        $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE user_one_id = ? AND user_two_id = ?");
        $stmt->execute([$low, $high]);
        $conv = $stmt->fetch();

        if ($conv) {
            // Un-delete for whichever user is re-opening it
            if ($userId1 == $low || $userId2 == $low) {
                $this->pdo->prepare("UPDATE conversations SET deleted_by_user_one = 0 WHERE id = ? AND deleted_by_user_one = 1")
                    ->execute([$conv['id']]);
            }
            if ($userId1 == $high || $userId2 == $high) {
                $this->pdo->prepare("UPDATE conversations SET deleted_by_user_two = 0 WHERE id = ? AND deleted_by_user_two = 1")
                    ->execute([$conv['id']]);
            }
            // Re-fetch
            $stmt->execute([$low, $high]);
            return $stmt->fetch();
        }

        $ins = $this->pdo->prepare("INSERT INTO conversations (user_one_id, user_two_id) VALUES (?, ?)");
        $ins->execute([$low, $high]);
        $newId = (int)$this->pdo->lastInsertId();

        $stmt->execute([$low, $high]);
        return $stmt->fetch();
    }

    /**
     * Get a conversation by ID only if the user is a participant
     */
    public function getConversationById(int $convId, int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)");
        $stmt->execute([$convId, $userId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all conversations for a user, with partner info and last message preview
     */
    public function getConversations(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END AS partner_id,
                   u.name AS partner_name,
                   u.profile_picture AS partner_avatar,
                   u.last_active AS partner_last_active,
                   m.body AS last_message_body,
                   m.sender_id AS last_message_sender_id,
                   m.created_at AS last_message_time,
                   (
                       SELECT COUNT(*) FROM messages msg
                       WHERE msg.conversation_id = c.id
                         AND msg.sender_id != ?
                         AND msg.deleted_for_everyone = 0
                         AND msg.id > COALESCE(
                             (SELECT mr.last_read_message_id FROM message_reads mr WHERE mr.conversation_id = c.id AND mr.user_id = ?), 0
                         )
                   ) AS unread_count
            FROM conversations c
            LEFT JOIN messages m ON m.id = c.last_message_id
            JOIN users u ON u.id = CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END
            WHERE (c.user_one_id = ? OR c.user_two_id = ?)
              AND (
                  (c.user_one_id = ? AND c.deleted_by_user_one = 0)
                  OR (c.user_two_id = ? AND c.deleted_by_user_two = 0)
              )
              AND c.last_message_id IS NOT NULL
            ORDER BY c.last_message_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get total unread message count across all conversations
     */
    public function getUnreadTotal(int $userId): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(sub.cnt), 0) AS total FROM (
                SELECT COUNT(*) AS cnt
                FROM messages msg
                JOIN conversations c ON c.id = msg.conversation_id
                WHERE (c.user_one_id = ? OR c.user_two_id = ?)
                  AND msg.sender_id != ?
                  AND msg.deleted_for_everyone = 0
                  AND msg.id > COALESCE(
                      (SELECT mr.last_read_message_id FROM message_reads mr WHERE mr.conversation_id = c.id AND mr.user_id = ?), 0
                  )
                  AND (
                      (c.user_one_id = ? AND c.deleted_by_user_one = 0)
                      OR (c.user_two_id = ? AND c.deleted_by_user_two = 0)
                  )
            ) sub
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Soft-delete a conversation for the current user only
     */
    public function deleteConversation(int $convId, int $userId): bool {
        $conv = $this->getConversationById($convId, $userId);
        if (!$conv) return false;

        if ((int)$conv['user_one_id'] === $userId) {
            $this->pdo->prepare("UPDATE conversations SET deleted_by_user_one = 1 WHERE id = ?")->execute([$convId]);
        } else {
            $this->pdo->prepare("UPDATE conversations SET deleted_by_user_two = 1 WHERE id = ?")->execute([$convId]);
        }
        return true;
    }

    // ─── Message Operations ───────────────────────────────────────

    /**
     * Send a message in a conversation
     */
    public function sendMessage(int $conversationId, int $senderId, string $body, ?int $replyToId = null): ?array {
        $body = trim($body);
        if ($body === '') return null;

        $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, reply_to_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$conversationId, $senderId, $body, $replyToId]);
        $msgId = (int)$this->pdo->lastInsertId();

        // Update conversation's last message pointer
        $this->pdo->prepare("UPDATE conversations SET last_message_id = ?, last_message_at = NOW(), deleted_by_user_one = 0, deleted_by_user_two = 0 WHERE id = ?")
            ->execute([$msgId, $conversationId]);

        // Return the newly created message with reply info
        $fetch = $this->pdo->prepare("
            SELECT m.*, rm.body AS reply_to_body, ru.name AS reply_to_sender_name
            FROM messages m
            LEFT JOIN messages rm ON rm.id = m.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE m.id = ?
        ");
        $fetch->execute([$msgId]);
        return $fetch->fetch();
    }

    /**
     * Get messages for a conversation (paginated, respects deletions)
     * Includes "deleted for everyone" placeholders (deleted_by_user_id set) shown as "Message deleted by [name]"
     */
    public function getMessages(int $conversationId, int $userId, int $limit = 50, ?int $beforeId = null): array {
        $sql = "
            SELECT m.*, u.name AS sender_name, u.profile_picture AS sender_avatar,
                   d.name AS deleted_by_name,
                   rm.body AS reply_to_body, ru.name AS reply_to_sender_name
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN users d ON d.id = m.deleted_by_user_id
            LEFT JOIN messages rm ON rm.id = m.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE m.conversation_id = ?
              AND (
                (m.deleted_for_everyone = 0 AND NOT (m.deleted_for_sender = 1 AND m.sender_id = ?)
                 AND NOT EXISTS (SELECT 1 FROM message_hidden_for_user mh WHERE mh.message_id = m.id AND mh.user_id = ?))
                OR (m.deleted_for_everyone = 1 AND m.deleted_by_user_id IS NOT NULL)
              )
        ";
        $params = [$conversationId, $userId, $userId];

        if ($beforeId !== null) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        return array_reverse($messages); // Return in chronological order
    }

    /**
     * Get messages AFTER a given ID (for polling new messages)
     * Includes "deleted for everyone" placeholders so the other user sees the update
     */
    public function getNewMessages(int $conversationId, int $userId, int $afterId): array {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.name AS sender_name, u.profile_picture AS sender_avatar,
                   d.name AS deleted_by_name,
                   rm.body AS reply_to_body, ru.name AS reply_to_sender_name
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN users d ON d.id = m.deleted_by_user_id
            LEFT JOIN messages rm ON rm.id = m.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE m.conversation_id = ?
              AND m.id > ?
              AND (
                (m.deleted_for_everyone = 0 AND NOT (m.deleted_for_sender = 1 AND m.sender_id = ?)
                 AND NOT EXISTS (SELECT 1 FROM message_hidden_for_user mh WHERE mh.message_id = m.id AND mh.user_id = ?))
                OR (m.deleted_for_everyone = 1 AND m.deleted_by_user_id IS NOT NULL)
              )
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversationId, $afterId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get messages that were deleted for everyone (id <= afterId) so the client can update existing bubbles without refresh
     */
    public function getDeletedForEveryoneUpdates(int $conversationId, int $afterId): array {
        if ($afterId <= 0) return [];
        $stmt = $this->pdo->prepare("
            SELECT m.id, d.name AS deleted_by_name
            FROM messages m
            LEFT JOIN users d ON d.id = m.deleted_by_user_id
            WHERE m.conversation_id = ? AND m.id <= ? AND m.deleted_for_everyone = 1 AND m.deleted_by_user_id IS NOT NULL
            ORDER BY m.id DESC
            LIMIT 100
        ");
        $stmt->execute([$conversationId, $afterId]);
        return $stmt->fetchAll();
    }

    /**
     * Get recently edited messages (id <= afterId) so the other user sees edits without refresh
     */
    public function getEditedMessageUpdates(int $conversationId, int $afterId): array {
        if ($afterId <= 0) return [];
        $stmt = $this->pdo->prepare("
            SELECT id, body, is_edited
            FROM messages
            WHERE conversation_id = ? AND id <= ? AND is_edited = 1
              AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
              AND deleted_for_everyone = 0
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt->execute([$conversationId, $afterId]);
        return $stmt->fetchAll();
    }

    /**
     * Edit a message (only by the sender, within 1 hour)
     */
    public function editMessage(int $messageId, int $senderId, string $newBody): bool {
        $newBody = trim($newBody);
        if ($newBody === '') return false;

        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$messageId, $senderId]);
        $msg = $stmt->fetch();

        if (!$msg) return false;

        // Allow editing within 1 hour of sending
        $sentTime = strtotime($msg['created_at']);
        if (time() - $sentTime > 3600) return false;

        $update = $this->pdo->prepare("UPDATE messages SET body = ?, is_edited = 1 WHERE id = ?");
        return $update->execute([$newBody, $messageId]);
    }

    /**
     * Delete a message for the current user only
     */
    public function deleteForMe(int $messageId, int $userId): bool {
        // We only support "delete for sender" since we don't track per-recipient deletions individually
        // For the receiver, we use a separate tracking approach
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg) return false;

        if ((int)$msg['sender_id'] === $userId) {
            $this->pdo->prepare("UPDATE messages SET deleted_for_sender = 1 WHERE id = ?")->execute([$messageId]);
        } else {
            // Receiver: hide only for this user (don't touch deleted_for_everyone)
            $this->pdo->prepare("INSERT IGNORE INTO message_hidden_for_user (message_id, user_id) VALUES (?, ?)")
                ->execute([$messageId, $userId]);
        }
        return true;
    }

    /**
     * Delete a message for everyone (only by sender, within 1 hour).
     * Shows "Message deleted by [username]" to both participants.
     */
    public function deleteForEveryone(int $messageId, int $senderId): bool {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$messageId, $senderId]);
        $msg = $stmt->fetch();
        if (!$msg) return false;

        $sentTime = strtotime($msg['created_at']);
        if (time() - $sentTime > 3600) return false;

        $this->pdo->prepare("UPDATE messages SET deleted_for_everyone = 1, deleted_by_user_id = ?, body = '' WHERE id = ?")
            ->execute([$senderId, $messageId]);

        // If this was the last message, update the conversation's last_message_id
        $conv = $this->pdo->prepare("SELECT * FROM conversations WHERE last_message_id = ?");
        $conv->execute([$messageId]);
        $convRow = $conv->fetch();
        if ($convRow) {
            // Find the previous non-deleted message
            $prev = $this->pdo->prepare("
                SELECT id, created_at FROM messages 
                WHERE conversation_id = ? AND deleted_for_everyone = 0 
                ORDER BY created_at DESC LIMIT 1
            ");
            $prev->execute([$convRow['id']]);
            $prevMsg = $prev->fetch();
            if ($prevMsg) {
                $this->pdo->prepare("UPDATE conversations SET last_message_id = ?, last_message_at = ? WHERE id = ?")
                    ->execute([$prevMsg['id'], $prevMsg['created_at'], $convRow['id']]);
            } else {
                $this->pdo->prepare("UPDATE conversations SET last_message_id = NULL, last_message_at = NULL WHERE id = ?")
                    ->execute([$convRow['id']]);
            }
        }

        return true;
    }

    // ─── Read Receipts ────────────────────────────────────────────

    /**
     * Mark messages as read up to a specific message ID
     */
    public function markAsRead(int $conversationId, int $userId, int $lastMsgId): void {
        $this->pdo->prepare("
            INSERT INTO message_reads (conversation_id, user_id, last_read_message_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)), read_at = NOW()
        ")->execute([$conversationId, $userId, $lastMsgId]);
    }

    /**
     * Get the last read message ID for a generic user in a conversation
     */
    public function getPartnerReadId(int $conversationId, int $partnerId): int {
        $stmt = $this->pdo->prepare("SELECT last_read_message_id FROM message_reads WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$conversationId, $partnerId]);
        return (int)$stmt->fetchColumn();
    }

    // ─── Blocking ─────────────────────────────────────────────────

    /**
     * Block a user
     */
    public function blockUser(int $blockerId, int $blockedId): bool {
        try {
            $this->pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)")
                ->execute([$blockerId, $blockedId]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Unblock a user
     */
    public function unblockUser(int $blockerId, int $blockedId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        return $stmt->execute([$blockerId, $blockedId]);
    }

    /**
     * Check if either user has blocked the other
     */
    public function isBlocked(int $userId1, int $userId2): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_blocks 
            WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)
        ");
        $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if a specific user has blocked another (directional)
     */
    public function hasBlocked(int $blockerId, int $blockedId): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$blockerId, $blockedId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get all users blocked by a specific user
     */
    public function getBlockedUsers(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT ub.*, u.name, u.profile_picture
            FROM user_blocks ub
            JOIN users u ON u.id = ub.blocked_id
            WHERE ub.blocker_id = ?
            ORDER BY ub.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // ─── Permission Check ─────────────────────────────────────────

    /**
     * Check if a user can message another user.
     * Returns: ['allowed' => bool, 'reason' => string]
     */
    public function canMessage(int $senderId, int $recipientId): array {
        // Can't message yourself
        if ($senderId === $recipientId) {
            return ['allowed' => false, 'reason' => 'You cannot message yourself.'];
        }

        // Check blocks
        if ($this->hasBlocked($recipientId, $senderId)) {
            return ['allowed' => false, 'reason' => 'You cannot message this user.'];
        }
        if ($this->hasBlocked($senderId, $recipientId)) {
            return ['allowed' => false, 'reason' => 'You have blocked this user. Unblock to send messages.'];
        }

        // Check recipient's privacy setting
        require_once __DIR__ . '/User.php';
        $userModel = new User($this->pdo);
        $recipient = $userModel->getById($recipientId);
        if (!$recipient) {
            return ['allowed' => false, 'reason' => 'User not found.'];
        }

        if (isset($userModel->privacy_allow_messages) && !$userModel->privacy_allow_messages) {
            return ['allowed' => false, 'reason' => 'This user has disabled direct messages.'];
        }

        return ['allowed' => true, 'reason' => ''];
    }
}
?>
