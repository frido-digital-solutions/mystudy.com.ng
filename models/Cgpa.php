<?php
class Cgpa
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS cgpa_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_code VARCHAR(32) NOT NULL,
            course_unit INT NOT NULL,
            grade_letter VARCHAR(2) NOT NULL,
            semester VARCHAR(32) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM cgpa_entries WHERE user_id = :u ORDER BY id DESC');
        $stmt->execute([':u'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function add(int $userId, string $courseCode, int $unit, string $grade, ?string $semester = null): int
    {
        $stmt = $this->conn->prepare('INSERT INTO cgpa_entries (user_id, course_code, course_unit, grade_letter, semester) VALUES (:u,:c,:n,:g,:s)');
        $stmt->execute([
            ':u'=>$userId,
            ':c'=>trim($courseCode),
            ':n'=>$unit,
            ':g'=>strtoupper(trim($grade)),
            ':s'=>$semester
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM cgpa_entries WHERE id=:id AND user_id=:u');
        return $stmt->execute([':id'=>$id, ':u'=>$userId]);
    }

    public static function gradePoint(string $letter): float
    {
        $map = [
            'A'=>5.0, 'B'=>4.0, 'C'=>3.0, 'D'=>2.0, 'E'=>1.0, 'F'=>0.0
        ];
        $l = strtoupper(trim($letter));
        return $map[$l] ?? 0.0;
    }

    public function compute(array $items): array
    {
        $totalUnits = 0; $totalPoints = 0.0;
        foreach ($items as $row) {
            $u = (int)$row['course_unit'];
            $gp = self::gradePoint((string)$row['grade_letter']);
            $totalUnits += $u;
            $totalPoints += $gp * $u;
        }
        $gpa = $totalUnits > 0 ? round($totalPoints / $totalUnits, 2) : 0.00;
        return [
            'total_units'=>$totalUnits,
            'total_points'=>$totalPoints,
            'gpa'=>$gpa
        ];
    }
}
