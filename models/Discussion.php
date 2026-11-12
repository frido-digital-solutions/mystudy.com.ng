<?php
class Discussion
{
    protected PDO $conn;

    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS discussions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            image_path VARCHAR(255) NULL,
            level VARCHAR(50) NULL,
            subject VARCHAR(120) NULL,
            views INT NOT NULL DEFAULT 0,
            upvotes INT NOT NULL DEFAULT 0,
            downvotes INT NOT NULL DEFAULT 0,
            score INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS discussion_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discussion_id INT NOT NULL,
            user_id INT NOT NULL,
            parent_id INT NULL,
            reply_to_user_id INT NULL,
            content TEXT NOT NULL,
            image_path VARCHAR(255) NULL,
            upvotes INT NOT NULL DEFAULT 0,
            downvotes INT NOT NULL DEFAULT 0,
            score INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) DEFAULT 0,
            FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES discussion_comments(id) ON DELETE CASCADE,
            FOREIGN KEY (reply_to_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS discussion_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            target_type ENUM('discussion', 'comment') NOT NULL,
            target_id INT NOT NULL,
            vote TINYINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_target (user_id, target_type, target_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS discussion_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            target_type ENUM('discussion', 'comment') NOT NULL,
            target_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function createDiscussion(int $userId, string $title, string $content, ?string $imagePath = null, ?string $level = null, ?string $subject = null): int
    {
        $sql = 'INSERT INTO discussions (user_id, title, content, image_path, level, subject) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, trim($title), trim($content), $imagePath, $level, $subject]);
        return (int)$this->conn->lastInsertId();
    }

    public function getDiscussions(int $limit = 20, int $offset = 0, string $sortBy = 'score', ?string $level = null, ?string $subject = null, string $searchQuery = '', int $viewerId = 0): array
    {
        $where = [];
        $params = [];

        if ($level && $level !== 'All') {
            $where[] = 'd.level = ?';
            $params[] = $level;
        }

        if ($subject) {
            $where[] = 'd.subject = ?';
            $params[] = $subject;
        }

        if ($searchQuery) {
            $where[] = '(d.title LIKE ? OR d.content LIKE ?)';
            $search = '%' . $searchQuery . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Order mapping
        $orderClause = 'ORDER BY d.score DESC, d.created_at DESC';
        if ($sortBy === 'newest') {
            $orderClause = 'ORDER BY d.created_at DESC';
        } elseif ($sortBy === 'unanswered') {
            $whereClause .= ($whereClause ? ' AND ' : 'WHERE ') . '(SELECT COUNT(*) FROM discussion_comments WHERE discussion_id = d.id AND is_deleted = 0) = 0';
        }

        $sql = "
            SELECT 
                d.*,
                u.name as author_name,
                u.profile_picture as author_avatar,
                u.points as author_points,
                (SELECT COUNT(*) FROM discussion_comments WHERE discussion_id = d.id AND is_deleted = 0) as comment_count
            FROM discussions d
            JOIN users u ON d.user_id = u.id
            $whereClause
            $orderClause
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        if ($viewerId > 0 && !empty($discussions)) {
            $dIds = array_column($discussions, 'id');
            $placeholders = str_repeat('?,', count($dIds) - 1) . '?';
            $vStmt = $this->conn->prepare("SELECT target_id, vote FROM discussion_votes WHERE target_type = 'discussion' AND user_id = ? AND target_id IN ($placeholders)");
            $vParams = array_merge([$viewerId], $dIds);
            $vStmt->execute($vParams);
            
            $votes = [];
            while ($row = $vStmt->fetch(PDO::FETCH_ASSOC)) {
                $votes[$row['target_id']] = (int)$row['vote'];
            }
            
            foreach ($discussions as &$disc) {
                $disc['user_vote'] = $votes[$disc['id']] ?? 0;
            }
        } else {
            foreach ($discussions as &$disc) {
                $disc['user_vote'] = 0;
            }
        }
        
        return $discussions;
    }

    public function getDiscussionById(int $id, int $viewerId = 0): ?array
    {
        $sql = "
            SELECT 
                d.*,
                u.name as author_name,
                u.profile_picture as author_avatar,
                u.points as author_points
            FROM discussions d
            JOIN users u ON d.user_id = u.id
            WHERE d.id = ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $discussion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$discussion) return null;

        $discussion['user_vote'] = 0;
        if ($viewerId > 0) {
            $voteStmt = $this->conn->prepare("SELECT vote FROM discussion_votes WHERE target_type = 'discussion' AND target_id = ? AND user_id = ?");
            $voteStmt->execute([$id, $viewerId]);
            $discussion['user_vote'] = (int)$voteStmt->fetchColumn() ?: 0;
        }

        return $discussion;
    }

    public function incrementViews(int $id): void
    {
        $this->conn->prepare("UPDATE discussions SET views = views + 1 WHERE id = ?")->execute([$id]);
    }

    public function addComment(int $discussionId, int $userId, string $content, ?string $imagePath = null, ?int $parentId = null, ?int $replyToUserId = null): int
    {
        $sql = "INSERT INTO discussion_comments (discussion_id, user_id, parent_id, reply_to_user_id, content, image_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$discussionId, $userId, $parentId, $replyToUserId, trim($content), $imagePath]);
        return (int)$this->conn->lastInsertId();
    }

    public function getCommentsThreaded(int $discussionId, int $viewerId = 0): array
    {
        $sql = "
            SELECT 
                c.*,
                u.name as author_name,
                u.profile_picture as author_avatar,
                u.points as author_points,
                ru.name as reply_to_name
            FROM discussion_comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN users ru ON c.reply_to_user_id = ru.id
            WHERE c.discussion_id = ?
            ORDER BY c.score DESC, c.created_at ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$discussionId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $votes = [];
        if ($viewerId > 0 && !empty($comments)) {
            $cIds = array_column($comments, 'id');
            $placeholders = str_repeat('?,', count($cIds) - 1) . '?';
            $vStmt = $this->conn->prepare("SELECT target_id, vote FROM discussion_votes WHERE target_type = 'comment' AND user_id = ? AND target_id IN ($placeholders)");
            $params = array_merge([$viewerId], $cIds);
            $vStmt->execute($params);
            while ($row = $vStmt->fetch(PDO::FETCH_ASSOC)) {
                $votes[$row['target_id']] = (int)$row['vote'];
            }
        }

        // Facebook style: 2 levels max. Parent=NULL are top level. Parent!=NULL are children grouped by parent_id.
        $parents = [];
        $children = [];

        foreach ($comments as $row) {
            $row['user_vote'] = $votes[$row['id']] ?? 0;
            $row['replies'] = []; // placeholder for frontend nesting

            if (empty($row['parent_id'])) {
                $parents[$row['id']] = $row;
            } else {
                $children[$row['parent_id']][] = $row;
            }
        }

        foreach ($children as $parentId => $replies) {
            // Sort replies chronologically
            usort($replies, function($a, $b) {
                return strtotime($a['created_at']) <=> strtotime($b['created_at']);
            });
            if (isset($parents[$parentId])) {
                $parents[$parentId]['replies'] = $replies;
            }
        }

        // Return flat array of parents (which internally hold their replies array)
        return array_values($parents);
    }

    public function getCommentsThreadedSorted(int $discussionId, int $viewerId = 0, string $sortBy = 'score'): array
    {
        // Determine sort order
        $orderBy = match($sortBy) {
            'newest' => 'c.created_at DESC',
            'oldest' => 'c.created_at ASC',
            'score' => 'c.score DESC, c.created_at ASC',
            'replies' => 'reply_count DESC, c.score DESC, c.created_at ASC',
            default => 'c.score DESC, c.created_at ASC'
        };

        $sql = "
            SELECT 
                c.*,
                u.name as author_name,
                u.profile_picture as author_avatar,
                u.points as author_points,
                ru.name as reply_to_name,
                (SELECT COUNT(*) FROM discussion_comments WHERE parent_id = c.id AND is_deleted = 0) as reply_count
            FROM discussion_comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN users ru ON c.reply_to_user_id = ru.id
            WHERE c.discussion_id = ? AND c.is_deleted = 0
            ORDER BY {$orderBy}
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$discussionId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $votes = [];
        if ($viewerId > 0 && !empty($comments)) {
            $cIds = array_column($comments, 'id');
            $placeholders = str_repeat('?,', count($cIds) - 1) . '?';
            $vStmt = $this->conn->prepare("SELECT target_id, vote FROM discussion_votes WHERE target_type = 'comment' AND user_id = ? AND target_id IN ($placeholders)");
            $params = array_merge([$viewerId], $cIds);
            $vStmt->execute($params);
            while ($row = $vStmt->fetch(PDO::FETCH_ASSOC)) {
                $votes[$row['target_id']] = (int)$row['vote'];
            }
        }

        // Facebook style: 2 levels max. Parent=NULL are top level. Parent!=NULL are children grouped by parent_id.
        $parents = [];
        $children = [];

        foreach ($comments as $row) {
            $row['user_vote'] = $votes[$row['id']] ?? 0;
            $row['replies'] = []; // placeholder for frontend nesting

            if (empty($row['parent_id'])) {
                $parents[$row['id']] = $row;
            } else {
                $children[$row['parent_id']][] = $row;
            }
        }

        foreach ($children as $parentId => $replies) {
            // Sort replies chronologically
            usort($replies, function($a, $b) {
                return strtotime($a['created_at']) <=> strtotime($b['created_at']);
            });
            if (isset($parents[$parentId])) {
                $parents[$parentId]['replies'] = $replies;
            }
        }

        // Return flat array of parents (which internally hold their replies array)
        return array_values($parents);
    }

    public function getCommentById(int $commentId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM discussion_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function vote(int $userId, string $targetType, int $targetId, int $voteValue): array
    {
        if (!in_array($targetType, ['discussion', 'comment'])) {
            throw new InvalidArgumentException("Invalid target type");
        }
        
        $voteValue = max(-1, min(1, $voteValue)); // clamp to -1, 0, 1

        $checkStmt = $this->conn->prepare("SELECT vote FROM discussion_votes WHERE user_id = ? AND target_type = ? AND target_id = ?");
        $checkStmt->execute([$userId, $targetType, $targetId]);
        $existing = $checkStmt->fetchColumn();

        $table = $targetType === 'discussion' ? 'discussions' : 'discussion_comments';

        $this->conn->beginTransaction();

        try {
            if ($existing !== false) {
                $oldVote = (int)$existing;
                if ($voteValue === 0 || $voteValue === $oldVote) {
                    // Remove vote
                    $this->conn->prepare("DELETE FROM discussion_votes WHERE user_id = ? AND target_type = ? AND target_id = ?")->execute([$userId, $targetType, $targetId]);
                    
                    if ($oldVote === 1) {
                        $this->conn->prepare("UPDATE {$table} SET upvotes = upvotes - 1, score = score - 1 WHERE id = ?")->execute([$targetId]);
                    } elseif ($oldVote === -1) {
                        $this->conn->prepare("UPDATE {$table} SET downvotes = downvotes - 1, score = score + 1 WHERE id = ?")->execute([$targetId]);
                    }
                } else {
                    // Change vote
                    $this->conn->prepare("UPDATE discussion_votes SET vote = ? WHERE user_id = ? AND target_type = ? AND target_id = ?")->execute([$voteValue, $userId, $targetType, $targetId]);
                    
                    if ($voteValue === 1 && $oldVote === -1) {
                        $this->conn->prepare("UPDATE {$table} SET upvotes = upvotes + 1, downvotes = downvotes - 1, score = score + 2 WHERE id = ?")->execute([$targetId]);
                    } elseif ($voteValue === -1 && $oldVote === 1) {
                        $this->conn->prepare("UPDATE {$table} SET downvotes = downvotes + 1, upvotes = upvotes - 1, score = score - 2 WHERE id = ?")->execute([$targetId]);
                    }
                }
            } else {
                if ($voteValue !== 0) {
                    $this->conn->prepare("INSERT INTO discussion_votes (user_id, target_type, target_id, vote) VALUES (?, ?, ?, ?)")->execute([$userId, $targetType, $targetId, $voteValue]);
                    
                    if ($voteValue === 1) {
                        $this->conn->prepare("UPDATE {$table} SET upvotes = upvotes + 1, score = score + 1 WHERE id = ?")->execute([$targetId]);
                    } elseif ($voteValue === -1) {
                        $this->conn->prepare("UPDATE {$table} SET downvotes = downvotes + 1, score = score - 1 WHERE id = ?")->execute([$targetId]);
                    }
                }
            }

            // Return new counts
            $counts = $this->conn->prepare("SELECT upvotes, downvotes, score FROM {$table} WHERE id = ?");
            $counts->execute([$targetId]);
            $res = $counts->fetch(PDO::FETCH_ASSOC);

            $this->conn->commit();
            
            // Get post author to award XP conceptually (handled elsewhere or here if needed)
            // For now, we return the counts so UI updates smoothly.

            return $res;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getAuthorIdByTarget(string $targetType, int $targetId): ?int
    {
        $table = $targetType === 'discussion' ? 'discussions' : 'discussion_comments';
        $stmt = $this->conn->prepare("SELECT user_id FROM {$table} WHERE id = ?");
        $stmt->execute([$targetId]);
        $author = $stmt->fetchColumn();
        return $author ? (int)$author : null;
    }

    public function report(int $userId, string $targetType, int $targetId, string $reason): void
    {
        $stmt = $this->conn->prepare("INSERT INTO discussion_reports (user_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $targetType, $targetId, trim($reason)]);
        
        // Notify the content owner
        $authorId = $this->getAuthorIdByTarget($targetType, $targetId);
        if ($authorId && $authorId !== $userId) {
            $title = "⚠️ Content Reported";
            $message = "Your " . $targetType . " has been reported for: '" . trim($reason) . "'. An admin will review it shortly. Please review our community guidelines.";
            
            // Only require Notification if we need it here
            require_once __DIR__ . '/Notification.php';
            $notif = new Notification($this->conn);
            $notif->create($authorId, 'content_reported', $title, $message);
        }
    }

    public function softDeleteComment(int $commentId, int $userId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            $stmt = $this->conn->prepare("UPDATE discussion_comments SET is_deleted = 1, content = '[This comment has been removed by a moderator]', image_path = NULL WHERE id = ?");
            $stmt->execute([$commentId]);
            return $stmt->rowCount() > 0;
        }
        $stmt = $this->conn->prepare("UPDATE discussion_comments SET is_deleted = 1, content = '[Deleted by user]', image_path = NULL WHERE id = ? AND user_id = ?");
        $stmt->execute([$commentId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteDiscussion(int $discussionId, int $userId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            $stmt = $this->conn->prepare("DELETE FROM discussions WHERE id = ?");
            $stmt->execute([$discussionId]);
            return $stmt->rowCount() > 0;
        }
        $stmt = $this->conn->prepare("DELETE FROM discussions WHERE id = ? AND user_id = ?");
        $stmt->execute([$discussionId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function getTrendingTags(): array
    {
        $stmt = $this->conn->query("SELECT subject, COUNT(*) as count FROM discussions WHERE subject IS NOT NULL AND subject != '' GROUP BY subject ORDER BY count DESC LIMIT 8");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
