<?php
/**
 * Paid materials: one row per checkout attempt.
 *
 * The row is opened the moment a student starts checkout, which is what lets the
 * money be chased down later without their browser: the Paystack webhook, the
 * return redirect and a revisit to the material page all look the same row up by
 * reference and try to be the one that closes it. markSuccessful() is a single
 * conditional UPDATE, so exactly one of them wins and only that one sends the
 * email.
 */
class MaterialPayment
{
    private PDO $conn;

    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS material_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(190) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_ref VARCHAR(100) NOT NULL UNIQUE,
            status ENUM('open','successful','failed') NOT NULL DEFAULT 'open',
            gateway_status VARCHAR(40) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_material (user_id, material_id, status),
            INDEX idx_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Open a checkout. Returns the reference that was written. */
    public function open(int $userId, int $materialId, string $email, float $amount, string $ref): bool
    {
        $stmt = $this->conn->prepare('INSERT INTO material_payments (material_id, user_id, email, amount, payment_ref)
                                     VALUES (:m, :u, :e, :a, :r)
                                     ON DUPLICATE KEY UPDATE amount = VALUES(amount)');
        return $stmt->execute([
            ':m' => $materialId,
            ':u' => $userId,
            ':e' => $email,
            ':a' => $amount,
            ':r' => $ref,
        ]);
    }

    public function getByRef(string $ref): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM material_payments WHERE payment_ref = :r LIMIT 1');
        $stmt->execute([':r' => $ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Unpaid checkouts this student opened for this material in the last two days.
     * Paystack sessions die long before that, so older rows are dead attempts.
     */
    public function openFor(int $userId, int $materialId, int $hours = 48, int $limit = 5): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM material_payments
            WHERE user_id = :u AND material_id = :m AND status = 'open'
              AND created_at > (NOW() - INTERVAL :h HOUR)
            ORDER BY id DESC LIMIT :l");
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':m', $materialId, PDO::PARAM_INT);
        $stmt->bindValue(':h', $hours, PDO::PARAM_INT);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Close a checkout as paid. True only for the caller that actually flipped it,
     * which is the one allowed to notify the student. A 'failed' row is still
     * creditable because an abandoned Paystack session can still be completed.
     */
    public function markSuccessful(string $ref, float $paidAmount, ?string $gatewayStatus = null): bool
    {
        $stmt = $this->conn->prepare("UPDATE material_payments
            SET status = 'successful', amount = :a, gateway_status = :g, paid_at = CURRENT_TIMESTAMP
            WHERE payment_ref = :r AND status <> 'successful'");
        $stmt->execute([':a' => $paidAmount, ':g' => $gatewayStatus, ':r' => $ref]);
        return $stmt->rowCount() > 0;
    }

    public function owns(int $userId, int $materialId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM material_payments
            WHERE user_id = :u AND material_id = :m AND status = 'successful'");
        $stmt->execute([':u' => $userId, ':m' => $materialId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Which of these materials does the student already own? Used by the listings. */
    public function ownedIds(int $userId, array $materialIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $materialIds))));
        if ($userId <= 0 || $ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("SELECT DISTINCT material_id FROM material_payments
            WHERE user_id = ? AND status = 'successful' AND material_id IN ($in)");
        $stmt->execute(array_merge([$userId], $ids));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    }

    /** Every material this student has paid for, newest first. */
    public function allOwnedIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare("SELECT material_id FROM material_payments
            WHERE user_id = :u AND status = 'successful' GROUP BY material_id ORDER BY MAX(id) DESC");
        $stmt->execute([':u' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    }

    /** Admin ledger: revenue and buyer list per paid material. */
    public function salesSummary(int $limit = 100): array
    {
        $stmt = $this->conn->prepare("SELECT p.*, m.title AS material_title, u.name AS buyer_name
            FROM material_payments p
            JOIN materials m ON m.id = p.material_id
            LEFT JOIN users u ON u.id = p.user_id
            ORDER BY p.id DESC LIMIT :l");
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function totals(): array
    {
        $row = $this->conn->query("SELECT
                COALESCE(SUM(CASE WHEN status='successful' THEN amount ELSE 0 END),0) AS revenue,
                COUNT(CASE WHEN status='successful' THEN 1 END) AS sales,
                COUNT(CASE WHEN status='open' THEN 1 END) AS open_attempts
            FROM material_payments")->fetch(PDO::FETCH_ASSOC);
        return [
            'revenue' => (float)($row['revenue'] ?? 0),
            'sales' => (int)($row['sales'] ?? 0),
            'open' => (int)($row['open_attempts'] ?? 0),
        ];
    }
}
