<?php
class Timetable
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS timetable_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            day_of_week TINYINT NOT NULL, -- 1=Mon .. 7=Sun
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            location VARCHAR(150) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM timetable_entries WHERE user_id = :u ORDER BY day_of_week ASC, start_time ASC');
        $stmt->execute([':u'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function add(int $userId, string $title, int $dayOfWeek, string $startTime, string $endTime, ?string $location=null, ?string $notes=null): int
    {
        $stmt = $this->conn->prepare('INSERT INTO timetable_entries (user_id,title,day_of_week,start_time,end_time,location,notes) VALUES (:u,:t,:d,:s,:e,:l,:n)');
        $stmt->execute([
            ':u'=>$userId,
            ':t'=>trim($title),
            ':d'=>$dayOfWeek,
            ':s'=>$startTime,
            ':e'=>$endTime,
            ':l'=>$location,
            ':n'=>$notes,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM timetable_entries WHERE id=:id AND user_id=:u');
        return $stmt->execute([':id'=>$id, ':u'=>$userId]);
    }
}
