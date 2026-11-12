<?php
class Tutorial
{
    protected PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        // Tutorials
        $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT NULL,
            youtube_id VARCHAR(32) NOT NULL,
            youtube_url VARCHAR(255) NOT NULL,
            thumbnail_url VARCHAR(255) NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'University', -- 'Secondary' or 'University'
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Courses catalog for tutorials (general, not per-university)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorial_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Mapping table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorial_course_map (
            tutorial_id INT NOT NULL,
            course_id INT NOT NULL,
            PRIMARY KEY (tutorial_id, course_id),
            FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES tutorial_courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function slugify(string $text): string
    {
        $text = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        return $text ?: bin2hex(random_bytes(4));
    }

    public static function extractYoutubeId(string $url): ?string
    {
        $url = trim($url);
        // Handle youtu.be/<id> and youtube.com/watch?v=<id> and embed
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        if (preg_match('~v=([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        if (preg_match('~/embed/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        return null;
    }

    public function listCourses(): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM tutorial_courses ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function ensureCourse(string $name): int
    {
        $name = trim($name);
        if ($name === '') return 0;
        $slug = self::slugify($name);
        $stmt = $this->conn->prepare('INSERT INTO tutorial_courses (name, slug) VALUES (:n,:s) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        $stmt->execute([':n'=>$name, ':s'=>$slug]);
        if ($this->conn->lastInsertId()) return (int)$this->conn->lastInsertId();
        // fetch id if existed
        $g = $this->conn->prepare('SELECT id FROM tutorial_courses WHERE slug=:s LIMIT 1');
        $g->execute([':s'=>$slug]);
        return (int)($g->fetchColumn() ?: 0);
    }

    public function create(array $data, array $courseNamesOrIds = []): int
    {
        $title = trim($data['title'] ?? '');
        $slug  = self::slugify($data['slug'] ?? $title);
        $desc  = $data['description'] ?? null;
        $url   = trim($data['youtube_url'] ?? '');
        $ytid  = self::extractYoutubeId($url) ?? ($data['youtube_id'] ?? null);
        if (!$ytid) throw new InvalidArgumentException('Invalid YouTube URL/ID');
        $thumb = $data['thumbnail_url'] ?? ('https://img.youtube.com/vi/' . $ytid . '/hqdefault.jpg');
        $level = ($data['level'] ?? 'University') === 'Secondary' ? 'Secondary' : 'University';

        $sql = 'INSERT INTO tutorials (title, slug, description, youtube_id, youtube_url, thumbnail_url, level) VALUES (:t,:s,:d,:yid,:yurl,:thumb,:lvl)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':t'=>$title, ':s'=>$slug, ':d'=>$desc, ':yid'=>$ytid, ':yurl'=>$url, ':thumb'=>$thumb, ':lvl'=>$level
        ]);
        $tid = (int)$this->conn->lastInsertId();
        $this->setCoursesByNamesOrIds($tid, $courseNamesOrIds);
        return $tid;
    }

    public function update(int $id, array $data, array $courseNamesOrIds = []): bool
    {
        $one = $this->getById($id);
        if (!$one) return false;
        $title = trim($data['title'] ?? $one['title']);
        $slug  = self::slugify($data['slug'] ?? $one['slug']);
        $desc  = $data['description'] ?? $one['description'];
        $url   = trim($data['youtube_url'] ?? $one['youtube_url']);
        $ytid  = self::extractYoutubeId($url) ?? ($data['youtube_id'] ?? $one['youtube_id']);
        $thumb = $data['thumbnail_url'] ?? ('https://img.youtube.com/vi/' . $ytid . '/hqdefault.jpg');
        $level = ($data['level'] ?? $one['level']) === 'Secondary' ? 'Secondary' : 'University';
        $sql = 'UPDATE tutorials SET title=:t, slug=:s, description=:d, youtube_id=:yid, youtube_url=:yurl, thumbnail_url=:thumb, level=:lvl WHERE id=:id';
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([':id'=>$id, ':t'=>$title, ':s'=>$slug, ':d'=>$desc, ':yid'=>$ytid, ':yurl'=>$url, ':thumb'=>$thumb, ':lvl'=>$level]);
        if ($ok && !empty($courseNamesOrIds)) {
            $this->setCoursesByNamesOrIds($id, $courseNamesOrIds);
        }
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM tutorials WHERE id = :id');
        return $stmt->execute([':id'=>$id]);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM tutorials WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { $row['courses'] = $this->getCoursesForTutorial($id); }
        return $row ?: null;
    }

    public function getCoursesForTutorial(int $tutorialId): array
    {
        $stmt = $this->conn->prepare('SELECT c.* FROM tutorial_course_map m JOIN tutorial_courses c ON c.id = m.course_id WHERE m.tutorial_id = :id ORDER BY c.name ASC');
        $stmt->execute([':id'=>$tutorialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setCoursesByNamesOrIds(int $tutorialId, array $courseNamesOrIds): void
    {
        // Clear existing
        $this->conn->prepare('DELETE FROM tutorial_course_map WHERE tutorial_id = :id')->execute([':id'=>$tutorialId]);
        foreach ($courseNamesOrIds as $raw) {
            $cid = 0;
            if (is_numeric($raw)) {
                $cid = (int)$raw;
            } else {
                $cid = $this->ensureCourse((string)$raw);
            }
            if ($cid > 0) {
                $this->conn->prepare('INSERT IGNORE INTO tutorial_course_map (tutorial_id, course_id) VALUES (:t,:c)')->execute([':t'=>$tutorialId, ':c'=>$cid]);
            }
        }
    }

    public function list(int $limit=12, int $offset=0): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM tutorials ORDER BY created_at DESC LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows;
    }

    public function countAllTutorials(): int
    {
        $stmt = $this->conn->query('SELECT COUNT(*) FROM tutorials');
        return (int)$stmt->fetchColumn();
    }

    public function searchTutorials(?string $q, ?string $level, ?string $courseSlug, int $limit=12, int $offset=0): array
    {
        $where = [];
        $params = [];
        if ($q && trim($q) !== '') {
            $where[] = '(t.title LIKE :q1 OR t.description LIKE :q2)';
            $params[':q1'] = '%' . trim($q) . '%';
            $params[':q2'] = '%' . trim($q) . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 't.level = :lvl'; $params[':lvl'] = $level; }
        $join = '';
        if ($courseSlug) {
            $join = ' JOIN tutorial_course_map m ON m.tutorial_id = t.id JOIN tutorial_courses c ON c.id = m.course_id ';
            $where[] = 'c.slug = :cslug';
            $params[':cslug'] = $courseSlug;
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $sql = 'SELECT t.* FROM tutorials t' . $join . $whereSql . ' ORDER BY t.created_at DESC LIMIT :l OFFSET :o';
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSearchTutorials(?string $q, ?string $level, ?string $courseSlug): int
    {
        $where = [];
        $params = [];
        if ($q && trim($q) !== '') {
            $where[] = '(t.title LIKE :q1 OR t.description LIKE :q2)';
            $params[':q1'] = '%' . trim($q) . '%';
            $params[':q2'] = '%' . trim($q) . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 't.level = :lvl'; $params[':lvl'] = $level; }
        $join = '';
        if ($courseSlug) {
            $join = ' JOIN tutorial_course_map m ON m.tutorial_id = t.id JOIN tutorial_courses c ON c.id = m.course_id ';
            $where[] = 'c.slug = :cslug';
            $params[':cslug'] = $courseSlug;
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $sql = 'SELECT COUNT(*) FROM tutorials t' . $join . $whereSql;
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}

class TutorialAdmin extends Tutorial
{
    // Admin-only helpers can be extended here if needed
}
