<?php
class Payment
{
    private PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            quiz_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_ref VARCHAR(100) UNIQUE NULL,
            receipt_path VARCHAR(255) NULL,
            status ENUM('pending', 'successful', 'failed') NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function recordPayment(int $userId, int $quizId, float $amount, string $ref, string $status = 'pending', ?string $receiptPath = null): int
    {
        $stmt = $this->conn->prepare('INSERT INTO quiz_payments (user_id, quiz_id, amount, payment_ref, receipt_path, status) VALUES (:u, :q, :a, :r, :rp, :s)');
        $stmt->execute([
            ':u' => $userId,
            ':q' => $quizId,
            ':a' => $amount,
            ':r' => $ref,
            ':rp' => $receiptPath,
            ':s' => $status
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function updateStatusByRef(string $ref, string $status): bool
    {
        $stmt = $this->conn->prepare('UPDATE quiz_payments SET status = :s WHERE payment_ref = :r');
        return $stmt->execute([':s' => $status, ':r' => $ref]);
    }

    public function getPaymentByRef(string $ref): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quiz_payments WHERE payment_ref = :r LIMIT 1');
        $stmt->execute([':r' => $ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function hasPaid(int $userId, int $quizId): bool
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM quiz_payments WHERE user_id = :u AND quiz_id = :q AND status = "successful"');
        $stmt->execute([':u' => $userId, ':q' => $quizId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getPendingPayment(int $userId, int $quizId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quiz_payments WHERE user_id = :u AND quiz_id = :q AND status = "pending" ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':u' => $userId, ':q' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
