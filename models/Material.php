<?php
class Material
{
    private PDO $conn;

    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
        // NOTE: seed() removed — it auto-created dummy materials on every request
        // whenever count was zero, causing deleted materials to reappear.
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS material_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category_id INT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INT NULL,
            mime_type VARCHAR(120) NULL,
            uploader_id INT NOT NULL,
            visibility ENUM('public','private') NOT NULL DEFAULT 'public',
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            level VARCHAR(20) NOT NULL DEFAULT 'Secondary',
            secondary_board ENUM('General','WAEC','NECO','JAMB','GCE') NOT NULL DEFAULT 'General',
            university_id INT NULL,
            is_university_general TINYINT(1) NOT NULL DEFAULT 0,
            original_name VARCHAR(255) NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES material_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Backfill columns for existing installations
        try {
            $cols = $this->conn->query('SHOW COLUMNS FROM materials')->fetchAll(PDO::FETCH_COLUMN, 0);
            $add = function(string $name, string $sql) use ($cols) { if (!in_array($name, $cols, true)) { $this->conn->exec("ALTER TABLE materials ADD COLUMN " . $sql); } };
            $add('status', "status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
            $add('level', "level VARCHAR(20) NOT NULL DEFAULT 'Secondary'");
            $add('secondary_board', "secondary_board ENUM('General','WAEC','NECO','JAMB','GCE') NOT NULL DEFAULT 'General'");
            $add('university_id', "university_id INT NULL");
            $add('is_university_general', "is_university_general TINYINT(1) NOT NULL DEFAULT 0");
            $add('original_name', "original_name VARCHAR(255) NULL");
            $add('reviewed_by', "reviewed_by INT NULL");
            $add('reviewed_at', "reviewed_at TIMESTAMP NULL DEFAULT NULL");
            $add('download_count', "download_count INT NOT NULL DEFAULT 0");
        } catch (Throwable $e) { /* ignore */ }

        // Ensure material categories exist (reference data, not demo data)
        $catCount = (int)$this->conn->query('SELECT COUNT(*) FROM material_categories')->fetchColumn();
        if ($catCount === 0) {
            $cats = [
                ['Science','science'],
                ['Engineering','engineering'],
                ['Arts','arts'],
                ['Business','business'],
            ];
            $stmt = $this->conn->prepare('INSERT INTO material_categories (name, slug) VALUES (:n,:s)');
            foreach ($cats as [$n,$s]) { $stmt->execute([':n'=>$n, ':s'=>$s]); }
        }
    }

    private function seed(): void
    {
        // Seed categories
        $catCount = (int)$this->conn->query('SELECT COUNT(*) FROM material_categories')->fetchColumn();
        if ($catCount === 0) {
            $cats = [
                ['Science','science'],
                ['Engineering','engineering'],
                ['Arts','arts'],
                ['Business','business'],
            ];
            $stmt = $this->conn->prepare('INSERT INTO material_categories (name, slug) VALUES (:n,:s)');
            foreach ($cats as [$n,$s]) { $stmt->execute([':n'=>$n, ':s'=>$s]); }
        }

        // Seed 10 demo materials if empty
        $matCount = (int)$this->conn->query('SELECT COUNT(*) FROM materials')->fetchColumn();
        if ($matCount === 0) {
            $stmt = $this->conn->prepare("INSERT INTO materials (title, description, category_id, file_path, file_size, mime_type, uploader_id, visibility)
                VALUES (:t,:d,:cid,:fp,:fs,:mt,:uid,'public')");
            // Use existing SVG as safe downloadable asset
            $filePath = 'assets/img/hero-illustration.svg';
            $full = defined('ROOT_PATH') ? (ROOT_PATH . '/' . $filePath) : $filePath;
            $size = @filesize($full) ?: 1024;
            $mime = 'image/svg+xml';
            for ($i=1; $i<=10; $i++) {
                $title = "Sample Material #$i";
                $desc = 'Demo study material for preview and testing.';
                $cid = ($i % 4) + 1;
                $stmt->execute([
                    ':t'=>$title,
                    ':d'=>$desc,
                    ':cid'=>$cid,
                    ':fp'=>$filePath,
                    ':fs'=>$size,
                    ':mt'=>$mime,
                    ':uid'=>1,
                ]);
            }
        }
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO materials (title, description, category_id, file_path, file_size, mime_type, uploader_id, visibility, status, level, secondary_board, university_id, is_university_general, original_name)
                VALUES (:title, :description, :category_id, :file_path, :file_size, :mime_type, :uploader_id, :visibility, :status, :level, :secondary_board, :university_id, :is_university_general, :original_name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':category_id' => $data['category_id'] ?? null,
            ':file_path' => $data['file_path'],
            ':file_size' => $data['file_size'] ?? null,
            ':mime_type' => $data['mime_type'] ?? null,
            ':uploader_id' => $data['uploader_id'],
            ':visibility' => $data['visibility'] ?? 'public',
            ':status' => $data['status'] ?? 'pending',
            ':level' => $data['level'] ?? 'Secondary',
            ':secondary_board' => $data['secondary_board'] ?? 'General',
            ':university_id' => $data['university_id'] ?? null,
            ':is_university_general' => !empty($data['is_university_general']) ? 1 : 0,
            ':original_name' => $data['original_name'] ?? null,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function list(int $limit = 20, int $offset = 0, ?int $categoryId = null, ?string $q = null, string $status = 'approved'): array
    {
        $where = [];
        $params = [];
        if ($status) { $where[] = 'm.status = :status'; $params[':status'] = $status; }
        if ($categoryId) { $where[] = 'm.category_id = :cid'; $params[':cid'] = $categoryId; }
        if ($q) { 
            // Use distinct placeholders to avoid HY093
            $where[] = '(m.title LIKE :q1 OR m.description LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT m.*, c.name AS category_name
                FROM materials m
                LEFT JOIN material_categories c ON c.id = m.category_id
                $whereSql
                ORDER BY m.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchMaterials(
        ?string $q = null,
        ?string $level = null,
        ?string $secondaryBoard = null,
        ?int $isUniversityGeneral = null,
        ?int $universityId = null,
        int $limit = 20,
        int $offset = 0,
        string $status = 'approved'
    ): array {
        $where = [];
        $params = [];
        if ($status) { $where[] = 'm.status = :status'; $params[':status'] = $status; }
        if ($q && trim($q) !== '') {
            $where[] = '(m.title LIKE :q1 OR m.description LIKE :q2)';
            $params[':q1'] = '%' . trim($q) . '%';
            $params[':q2'] = '%' . trim($q) . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'm.level = :level'; $params[':level'] = $level; }
        if ($level === 'Secondary' && $secondaryBoard && in_array($secondaryBoard, ['General','WAEC','NECO','JAMB','GCE'], true)) {
            $where[] = 'm.secondary_board = :sboard';
            $params[':sboard'] = $secondaryBoard;
        }
        if ($level === 'University') {
            if ($isUniversityGeneral !== null) {
                $where[] = 'm.is_university_general = :uigen';
                $params[':uigen'] = (int)$isUniversityGeneral;
            }
            if (!empty($universityId)) {
                $where[] = 'm.university_id = :uid';
                $params[':uid'] = (int)$universityId;
            }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT m.*, c.name AS category_name
                FROM materials m
                LEFT JOIN material_categories c ON c.id = m.category_id
                $whereSql
                ORDER BY m.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Count search results for pagination.
     */
    public function countSearchMaterials(
        ?string $q = null,
        ?string $level = null,
        ?string $secondaryBoard = null,
        ?int $isUniversityGeneral = null,
        ?int $universityId = null,
        string $status = 'approved'
    ): int {
        $where = [];
        $params = [];
        if ($status) { $where[] = 'm.status = :status'; $params[':status'] = $status; }
        if ($q && trim($q) !== '') {
            $where[] = '(m.title LIKE :q1 OR m.description LIKE :q2)';
            $params[':q1'] = '%' . trim($q) . '%';
            $params[':q2'] = '%' . trim($q) . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'm.level = :level'; $params[':level'] = $level; }
        if ($level === 'Secondary' && $secondaryBoard && in_array($secondaryBoard, ['General','WAEC','NECO','JAMB','GCE'], true)) {
            $where[] = 'm.secondary_board = :sboard';
            $params[':sboard'] = $secondaryBoard;
        }
        if ($level === 'University') {
            if ($isUniversityGeneral !== null) {
                $where[] = 'm.is_university_general = :uigen';
                $params[':uigen'] = (int)$isUniversityGeneral;
            }
            if (!empty($universityId)) {
                $where[] = 'm.university_id = :uid';
                $params[':uid'] = (int)$universityId;
            }
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT COUNT(*) FROM materials m $whereSql";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(int $id): void {
        $stmt = $this->conn->prepare('UPDATE materials SET download_count = download_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT m.*, c.name AS category_name FROM materials m LEFT JOIN material_categories c ON c.id = m.category_id WHERE m.id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listUniversities(): array
    {
        try {
            $stmt = $this->conn->prepare('SELECT id, name, acronym FROM universities ORDER BY name ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function updateStatus(int $id, string $status, ?int $reviewerId = null): bool
    {
        if (!in_array($status, ['pending','approved','rejected'], true)) return false;
        $sql = 'UPDATE materials SET status = :s, reviewed_by = :rb, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':s'=>$status, ':rb'=>$reviewerId, ':id'=>$id]);
    }

    public function listByStatus(string $status, int $limit=50, int $offset=0): array
    {
        if (!in_array($status, ['pending','approved','rejected'], true)) { $status = 'pending'; }
        return $this->list($limit, $offset, null, null, $status);
    }

    public function moderationCounts(): array
    {
        $res = ['pending'=>0,'approved'=>0,'rejected'=>0];
        try {
            $stmt = $this->conn->query("SELECT status, COUNT(*) as c FROM materials GROUP BY status");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $k = $row['status'];
                if (isset($res[$k])) { $res[$k] = (int)$row['c']; }
            }
        } catch (Throwable $e) {}
        return $res;
    }
}
