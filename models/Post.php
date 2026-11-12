<?php
class Post
{
    protected PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
        // NOTE: seed() removed — it auto-created dummy posts on every request
        // whenever count was zero, causing deleted posts to reappear.
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            excerpt TEXT NULL,
            content MEDIUMTEXT NULL,
            author_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Images attached to posts
        $this->conn->exec("CREATE TABLE IF NOT EXISTS post_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            caption VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Universities catalog
        $this->conn->exec("CREATE TABLE IF NOT EXISTS universities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            acronym VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add tagging columns to posts if missing
        $cols = $this->conn->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!in_array('level', $cols, true)) {
            $this->conn->exec("ALTER TABLE posts ADD COLUMN level VARCHAR(20) NOT NULL DEFAULT 'Secondary'");
        }
        if (!in_array('university_id', $cols, true)) {
            $this->conn->exec("ALTER TABLE posts ADD COLUMN university_id INT NULL");
        }
        if (!in_array('is_university_general', $cols, true)) {
            $this->conn->exec("ALTER TABLE posts ADD COLUMN is_university_general TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Ensure universities catalog is populated (reference data, not demo data)
        $ucount = (int)$this->conn->query('SELECT COUNT(*) FROM universities')->fetchColumn();
        if ($ucount === 0) {
            $pairs = [
                ['Abubakar Tafawa Balewa University', 'ATBU'],
                ['Ahmadu Bello University', 'ABU'],
                ['Alex Ekwueme Federal University Ndufu-Alike Ikwo', 'AE-FUNAI'],
                ['Bayero University Kano', 'BUK'],
                ['Federal University of Agriculture, Abeokuta', 'FUNAAB'],
                ['Federal University of Technology, Akure', 'FUTA'],
                ['Federal University of Technology Minna', 'FUTMINN'],
                ['Federal University of Technology, Owerri', 'FUTO'],
                ['Nnamdi Azikiwe University, Awka', 'UNIZIK'],
                ['Obafemi Awolowo University', 'OAU'],
                ['University of Benin', 'UNIBEN'],
                ['University of Ibadan', 'UI'],
                ['University of Ilorin', 'UNILORIN'],
                ['University of Lagos', 'UNILAG'],
                ['University of Nigeria, Nsukka', 'UNN'],
                ['University of Port Harcourt', 'UNIPORT'],
                ['Covenant University', 'CU'],
                ['Babcock University', 'BU'],
                ['Lagos State University', 'LASU'],
                ['Ladoke Akintola University of Technology', 'LAUTECH'],
            ];
            $ins = $this->conn->prepare('INSERT INTO universities (name, acronym) VALUES (:n,:a)');
            foreach ($pairs as [$n,$a]) {
                try { $ins->execute([':n'=>$n, ':a'=>$a]); } catch (Throwable $e) { /* ignore duplicates */ }
            }
        }
    }

    private function seed(): void
    {
        // Seed universities if empty (always check independently of posts)
        $ucount = (int)$this->conn->query('SELECT COUNT(*) FROM universities')->fetchColumn();
        if ($ucount === 0) {
            $pairs = [
                ['Abubakar Tafawa Balewa University', 'ATBU'],
                ['Ahmadu Bello University', 'ABU'],
                ['Alex Ekwueme Federal University Ndufu-Alike Ikwo', 'AE-FUNAI'],
                ['Bayero University Kano', 'BUK'],
                ['Federal University of Agriculture, Abeokuta', 'FUNAAB'],
                ['Federal University of Applied Sciences Kachia', 'FUASK'],
                ['Federal University Birnin Kebbi', 'FUBK'],
                ['Federal University Dutse', 'FUD'],
                ['Federal University Dutsin-Ma', 'FUDM'],
                ['Federal University Gashua', 'FUGASHUA'],
                ['Federal University Gusau', 'FUGUS'],
                ['Federal University Kashere', 'FUK'],
                ['Federal University Lokoja', 'FUL'],
                ['Federal University of Lafia', 'FULAFIA'],
                ['Federal University of Petroleum Resources Effurun', 'FUPRE'],
                ['Federal University of Technology, Akure', 'FUTA'],
                ['Federal University of Technology Minna', 'FUTMINN'],
                ['Federal University of Technology, Owerri', 'FUTO'],
                ['Federal University Otuoke', 'FUO'],
                ['Federal University Oye-Ekiti', 'FUOYE'],
                ['Federal University Wukari', 'FUW'],
                ['Michael Okpara University of Agriculture, Umudike', 'MOUAU'],
                ['Modibbo Adama University, Yola', 'MAU'],
                ['National Open University of Nigeria', 'NOUN'],
                ['Nnamdi Azikiwe University, Awka', 'UNIZIK'],
                ['Obafemi Awolowo University', 'OAU'],
                ['University of Abuja', 'UNIABUJA'],
                ['University of Agriculture, Makurdi', 'UAM'],
                ['University of Benin', 'UNIBEN'],
                ['University of Calabar', 'UNICAL'],
                ['University of Ibadan', 'UI'],
                ['University of Ilorin', 'UNILORIN'],
                ['University of Jos', 'UNIJOS'],
                ['University of Lagos', 'UNILAG'],
                ['University of Maiduguri', 'UNIMAID'],
                ['University of Nigeria, Nsukka', 'UNN'],
                ['University of Port Harcourt', 'UNIPORT'],
                ['University of Uyo', 'UNIUYO'],
                ['Usmanu Danfodiyo University', 'UDUS'],
                ['Abdulkadir Kure University', 'AKUM'],
                ['Abia State University', 'ABSU'],
                ['Adamawa State University', 'ADSU'],
                ['Adekunle Ajasin University', 'AAUA'],
                ['Akwa Ibom State University', 'AKSU'],
                ['Aliko Dangote University of Science and Technology (Wudil)', 'ADUSTECH'],
                ['Ambrose Alli University', 'AAU'],
                ['Bauchi State University', 'BASUG'],
                ['Bayelsa Medical University', 'BMU'],
                ['Benue State University', 'BSU'],
                ['Borno State University', 'BOSU'],
                ['Bukar Abba Ibrahim University', 'YSU'],
                ['Chukwuemeka Odumegwu Ojukwu University', 'ANSU'],
                ['Delta State University, Abraka', 'DELSU'],
                ['Delta State University of Science & Technology (Ozoro)', 'DSPZ'],
                ['Dennis Osadebay University', 'DOU'],
                ['Ebonyi State University', 'EBSU'],
                ['Edo State University, Uzairue', 'EDSU'],
                ['Ekiti State University', 'EKSU'],
                ['Enugu State University of Science and Technology', 'ESUT'],
                ['Gombe State University', 'GSU'],
                ['Gombe State University of Science & Technology', 'GSUST'],
                ['Ibrahim Badamasi Babangida University', 'IBBUL'],
                ['Ignatius Ajuru University of Education', 'IAUE'],
                ['Imo State University', 'IMSU'],
                ['Kaduna State University', 'KASU'],
                ['Kebbi State University of Science & Technology', 'KSUSTA'],
                ['Prince Abubakar Audu University', 'PAAU'],
                ['Kwara State University', 'KWASU'],
                ['Ladoke Akintola University of Technology', 'LAUTECH'],
                ['Lagos State University', 'LASU'],
                ['Lagos State University of Education', 'LASUED'],
                ['Lagos State University of Science & Technology', 'LASUST'],
                ['Nasarawa State University', 'NSUK'],
                ['Niger Delta University', 'NDU'],
                ['Osun State University', 'UNIOSUN'],
                ['Rivers State University', 'RIVSU'],
                ['Sule Lamido University', 'SLU'],
                ['Tai Solarin University of Education', 'TASUED'],
                ['Taraba State University', 'TSU'],
                ['Umaru Musa Yar\'adua University', 'UMYU'],
                ['Sokoto State University', 'SSU'],
                ['University of Delta (Agbor)', 'UNIDEL'],
                ['Yusuf Maitama Sule University Kano', 'YUMSUK'],
                ['Zamfara State University', 'ZSU'],
                ['Nigeria Airforce University (Air Force Institute of Technology)', 'AFIT'],
                ['Nigeria Maritime University', 'NMU'],
                ['Nigeria Police Academy, Wudil', 'POLAC'],
                ['Nigerian Army University, Biu', 'NUAB'],
                ['Nigerian Defence Academy', 'NDA'],
                ['Achievers University', 'AC'],
                ['Adeleke University', 'AUE'],
                ['Afe Babalola University', 'ABUAD'],
                ['African University of Science and Technology', 'AUST'],
                ['Ahman Pategi University', 'APU'],
                ['Ajayi Crowther University', 'ACU'],
                ['Al-Ansar University Maiduguri', 'AUM'],
                ['Al-Hikmah University', 'AHU'],
                ['Al-Qalam University', 'AUK'],
                ['American University of Nigeria', 'AUN'],
                ['Anchor University', 'ANCHOR'],
                ['Arthur Jarvis University', 'AJU'],
                ['Ave Maria University', 'AMU'],
                ['Babcock University', 'BU'],
                ['Baze University', 'BAZE'],
                ['Bells University of Technology', 'BUT'],
                ['Benson Idahosa University', 'BIU'],
                ['Bowen University', 'BOWEN'],
                ['Bingham University', 'BHU'],
                ['Caleb University', 'CUL'],
                ['Caritas University', 'CARITAS'],
                ['CETEP City University', 'CETEP'],
                ['Chrisland University', 'CHRISLAND'],
                ['Christopher University', 'CHRISTOPHER'],
                ['Clifford University', 'CLU'],
                ['Coal City University', 'CCU'],
                ['Covenant University', 'CU'],
                ['Crawford University', 'CRAWFORD'],
                ['Crescent University', 'CRESCENT'],
                ['Dominican University Ibadan', 'DUI'],
                ['Edwin Clark University', 'ECU'],
                ['Elizade University', 'EU'],
                ['Evangel University, Akaeze', 'EUA'],
                ['Fountain University', 'FUO'],
                ['Godfrey Okoye University', 'GOU'],
                ['Greenfield University', 'GFU'],
                ['Gregory University', 'GUU'],
                ['Hallmark University', 'HALLMARK'],
                ['Hezekiah University', 'HEZEKIAH'],
                ['Igbinedion University, Okada', 'IUO'],
                ['Joseph Ayo Babalola University', 'JABU'],
                ['Khadija University', 'KHAD'],
                ['Kings University', 'KU'],
                ['Koladaisi University', 'KOLA'],
                ['Kwararafa University', 'KWARARAFA'],
                ['Landmark University', 'LU'],
                ['Lead City University', 'LCU'],
                ['Madonna University', 'MADONNA'],
                ['McPherson University', 'MCU'],
                ['Mewar University', 'MEWAR'],
                ['Michael & Cecilia Ibru University', 'MCIU'],
                ['Mountain Top University', 'MTU'],
                ['Mudiame University', 'MUDIAME'],
                ['Nile University of Nigeria', 'NUN'],
                ['Novena University', 'NOVENA'],
                ['Obong University', 'OBONG'],
                ['Oduduwa University', 'OUI'],
                ['PAMO University of Medical Sciences', 'PAMO'],
                ['Pan-Atlantic University', 'PAU'],
                ['Paul University', 'PAUL'],
                ['Peaceland University', 'PUE'],
                ['Precious Cornerstone University', 'PCU'],
                ['Redeemer\'s University', 'RUN'],
                ['Renaissance University', 'RENAISSANCE'],
                ['Rhema University', 'RHEMA'],
                ['Ritman University', 'RITMAN'],
                ['Salem University', 'SALEM'],
                ['Sam Maris University', 'SMU'],
                ['Samuel Adegboyega University', 'SAU'],
                ['Skyline University', 'SUN'],
                ['Summit University', 'SUMMIT'],
                ['Veritas University (Catholic University of Nigeria)', 'VUNA'],
                ['Wesley University', 'WUO'],
                ['Western Delta University', 'WDU'],
                ['Westland University', 'WUI'],
                ['University of Mkar', 'UMKAR'],
                ['James Hope University, Lagos', 'JHU']
            ];
            $ins = $this->conn->prepare('INSERT INTO universities (name, acronym) VALUES (:n,:a)');
            foreach ($pairs as [$n,$a]) {
                try { $ins->execute([':n'=>$n, ':a'=>$a]); } catch (Throwable $e) { /* ignore duplicates */ }
            }
        }
        // Seed demo posts only if none exist
        $count = (int)$this->conn->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        if ($count === 0) {
            $stmt = $this->conn->prepare('INSERT INTO posts (title, slug, excerpt, content, author_id) VALUES (:t,:s,:e,:c,:a)');
            for ($i=1; $i<=10; $i++) {
                $title = "Study Tips #$i";
                $slug = 'study-tips-' . $i;
                $excerpt = 'Quick tips to improve your study habits and exam performance.';
                $content = "# $title\n\nHere are useful tips for studying effectively...";
                $stmt->execute([':t'=>$title, ':s'=>$slug, ':e'=>$excerpt, ':c'=>$content, ':a'=>1]);
            }
        }
    }

    public function list(int $limit=10, int $offset=0): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM posts ORDER BY created_at DESC LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAllPosts(): int
    {
        $stmt = $this->conn->query('SELECT COUNT(*) FROM posts');
        return (int)$stmt->fetchColumn();
    }

    public function listFiltered(?string $level, ?int $universityId, int $limit=10, int $offset=0): array
    {
        $where = [];
        $params = [];
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'level = :level'; $params[':level'] = $level; }
        if (!empty($universityId)) { $where[] = 'university_id = :uid'; $params[':uid'] = (int)$universityId; }
        $sql = 'SELECT * FROM posts';
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY created_at DESC LIMIT :l OFFSET :o';
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countFilteredPosts(?string $level, ?int $universityId): int
    {
        $where = [];
        $params = [];
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'level = :level'; $params[':level'] = $level; }
        if (!empty($universityId)) { $where[] = 'university_id = :uid'; $params[':uid'] = (int)$universityId; }
        $sql = 'SELECT COUNT(*) FROM posts';
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function searchPosts(string $query, ?string $level, ?int $universityId, int $limit=10, int $offset=0): array
    {
        $query = trim($query);
        $where = [];
        $params = [];
        if ($query !== '') {
            // Use unique named placeholders for each occurrence to avoid HY093
            $where[] = '(title LIKE :q1 OR excerpt LIKE :q2 OR content LIKE :q3)';
            $params[':q1'] = '%' . $query . '%';
            $params[':q2'] = '%' . $query . '%';
            $params[':q3'] = '%' . $query . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'level = :level'; $params[':level'] = $level; }
        if (!empty($universityId)) { $where[] = 'university_id = :uid'; $params[':uid'] = (int)$universityId; }
        $sql = 'SELECT * FROM posts';
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY created_at DESC LIMIT :l OFFSET :o';
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSearchPosts(string $query, ?string $level, ?int $universityId): int
    {
        $query = trim($query);
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(title LIKE :q1 OR excerpt LIKE :q2 OR content LIKE :q3)';
            $params[':q1'] = '%' . $query . '%';
            $params[':q2'] = '%' . $query . '%';
            $params[':q3'] = '%' . $query . '%';
        }
        if ($level === 'Secondary' || $level === 'University') { $where[] = 'level = :level'; $params[':level'] = $level; }
        if (!empty($universityId)) { $where[] = 'university_id = :uid'; $params[':uid'] = (int)$universityId; }
        $sql = 'SELECT COUNT(*) FROM posts';
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM posts WHERE slug = :s LIMIT 1');
        $stmt->execute([':s'=>$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get related posts for a given post (same level/university where applicable).
     */
    public function getRelatedPosts(array $post, int $limit = 4): array
    {
        $sql = 'SELECT * FROM posts WHERE id <> :id';
        $params = [':id' => (int)$post['id']];

        if (!empty($post['level'])) {
            $sql .= ' AND level = :level';
            $params[':level'] = $post['level'];
        }
        if ($post['level'] === 'University' && !empty($post['university_id'])) {
            $sql .= ' AND university_id = :uid';
            $params[':uid'] = (int)$post['university_id'];
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :l';
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listUniversities(): array
    {
        $stmt = $this->conn->prepare('SELECT id, name, acronym FROM universities ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

class PostAdmin extends Post
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO posts (title, slug, excerpt, content, author_id, level, university_id, is_university_general) VALUES (:title,:slug,:excerpt,:content,:author_id,:level,:university_id,:is_university_general)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':title' => trim($data['title'] ?? ''),
            ':slug' => trim($data['slug'] ?? ''),
            ':excerpt' => $data['excerpt'] ?? null,
            ':content' => $data['content'] ?? null,
            ':author_id' => (int)($data['author_id'] ?? 0) ?: null,
            ':level' => ($data['level'] ?? 'Secondary') === 'University' ? 'University' : 'Secondary',
            ':university_id' => !empty($data['university_id']) ? (int)$data['university_id'] : null,
            ':is_university_general' => !empty($data['is_university_general']) ? 1 : 0,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE posts SET title=:title, slug=:slug, excerpt=:excerpt, content=:content, author_id=:author_id, level=:level, university_id=:university_id, is_university_general=:is_university_general WHERE id=:id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => trim($data['title'] ?? ''),
            ':slug' => trim($data['slug'] ?? ''),
            ':excerpt' => $data['excerpt'] ?? null,
            ':content' => $data['content'] ?? null,
            ':author_id' => (int)($data['author_id'] ?? 0) ?: null,
            ':level' => ($data['level'] ?? 'Secondary') === 'University' ? 'University' : 'Secondary',
            ':university_id' => !empty($data['university_id']) ? (int)$data['university_id'] : null,
            ':is_university_general' => !empty($data['is_university_general']) ? 1 : 0,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM posts WHERE id = :id');
        return $stmt->execute([':id'=>$id]);
    }

    public function getImages(int $postId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM post_images WHERE post_id = :pid ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':pid' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteImagesByIds(array $ids, int $postId): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        array_unshift($params, (int)$postId);

        $stmt = $this->conn->prepare("SELECT id, file_path FROM post_images WHERE post_id = ? AND id IN ($placeholders)");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $del = $this->conn->prepare("DELETE FROM post_images WHERE post_id = ? AND id IN ($placeholders)");
            $del->execute($params);

            foreach ($rows as $row) {
                $path = $row['file_path'] ?? '';
                if ($path) {
                    $full = ROOT_PATH . '/' . ltrim($path, '/');
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
            }
        }
    }

    public function addImage(int $postId, string $relativePath, ?string $caption = null, int $sortOrder = 0): void
    {
        $stmt = $this->conn->prepare('INSERT INTO post_images (post_id, file_path, caption, sort_order) VALUES (:pid,:path,:caption,:sort)');
        $stmt->execute([
            ':pid' => $postId,
            ':path' => $relativePath,
            ':caption' => $caption,
            ':sort' => $sortOrder,
        ]);
    }
}
