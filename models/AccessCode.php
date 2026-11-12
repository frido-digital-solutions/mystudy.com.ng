<?php
class AccessCode
{
    private PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_access_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            is_used TINYINT(1) DEFAULT 0,
            used_by_user_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
            FOREIGN KEY (used_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function createCode(int $quizId, string $code): int
    {
        $stmt = $this->conn->prepare('INSERT INTO quiz_access_codes (quiz_id, code) VALUES (:q, :c)');
        $stmt->execute([':q' => $quizId, ':c' => strtoupper(trim($code))]);
        return (int)$this->conn->lastInsertId();
    }

    public function generateBulk(int $quizId, int $quantity): array
    {
        $created = [];
        for ($i = 0; $i < $quantity; $i++) {
            $code = substr(str_shuffle("0123456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 12);
            // Try to insert, ignore if duplicate
            try {
                if ($this->createCode($quizId, $code)) {
                    $created[] = $code;
                }
            } catch (PDOException $e) {
                // If unique constraint fails, just try one more time
                $code = substr(str_shuffle("0123456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 12);
                try {
                    if ($this->createCode($quizId, $code)) {
                         $created[] = $code;
                    }
                } catch(PDOException $e2) { }
            }
        }
        return $created;
    }

    public function generateForUser(int $quizId, int $userId): string
    {
        // Check if user already has one to avoid duplicates
        $stmt = $this->conn->prepare('SELECT code FROM quiz_access_codes WHERE used_by_user_id = :u AND quiz_id = :q LIMIT 1');
        $stmt->execute([':u' => $userId, ':q' => $quizId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return $existing;

        $code = substr(str_shuffle("0123456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 12);
        try {
            $stmt = $this->conn->prepare('INSERT INTO quiz_access_codes (quiz_id, code, is_used, used_by_user_id) VALUES (:q, :c, 1, :u)');
            $stmt->execute([':q' => $quizId, ':c' => $code, ':u' => $userId]);
        } catch (PDOException $e) {
            // retry once on collision
            $code = substr(str_shuffle("0123456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 12);
            $stmt = $this->conn->prepare('INSERT INTO quiz_access_codes (quiz_id, code, is_used, used_by_user_id) VALUES (:q, :c, 1, :u)');
            $stmt->execute([':q' => $quizId, ':c' => $code, ':u' => $userId]);
        }
        return $code;
    }

    public function verifyCode(string $code, int $quizId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quiz_access_codes WHERE code = :c AND quiz_id = :q LIMIT 1');
        $stmt->execute([':c' => strtoupper(trim($code)), ':q' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function useCode(int $codeId, int $userId): bool
    {
        $stmt = $this->conn->prepare('UPDATE quiz_access_codes SET is_used = 1, used_by_user_id = :u WHERE id = :i AND is_used = 0');
        return $stmt->execute([':u' => $userId, ':i' => $codeId]) && $stmt->rowCount() > 0;
    }

    public function hasUserUsedACodeForQuiz(int $userId, int $quizId): bool
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM quiz_access_codes WHERE used_by_user_id = :u AND quiz_id = :q');
        $stmt->execute([':u' => $userId, ':q' => $quizId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getCodeForUser(int $userId, int $quizId): ?string
    {
        $stmt = $this->conn->prepare('SELECT code FROM quiz_access_codes WHERE used_by_user_id = :u AND quiz_id = :q LIMIT 1');
        $stmt->execute([':u' => $userId, ':q' => $quizId]);
        $code = $stmt->fetchColumn();
        return $code ?: null;
    }
}
