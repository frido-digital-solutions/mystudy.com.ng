<?php
class User
{
    private $conn;
    private $table_name = "users";

    // Object properties
    public $id;
    public $name;
    public $email;
    public $password_hash;
    public $user_type;
    public $profile_picture;
    public $university;
    public $department;
    public $level;
    public $privacy_show_school = 1;
    public $privacy_show_stats = 1;
    public $privacy_show_discussions = 1;
    public $privacy_show_badges = 1;
    public $privacy_show_battles = 1;
    public $privacy_allow_messages = 1;
    public $show_level_on_users_page = 1;

    public $email_verified;
    public $verification_token;
    public $reset_token;
    public $reset_token_expires;
    public $created_at;
    public $updated_at;
    public $last_login;
    public $last_active;
    public $status;
    public $points = 0;

    // Constructor with database connection
    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create a new user
    public function create()
    {
        // Hash the password before saving
        $this->password_hash = hash_password($this->password_hash);

        // Mark email as unverified by default to require verification
        $query = "INSERT INTO " . $this->table_name . " 
                (name, email, password_hash, user_type, profile_picture, email_verified, verification_token) 
                VALUES 
                (:name, :email, :password_hash, :user_type, :profile_picture, 0, NULL)";

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->user_type = htmlspecialchars(strip_tags($this->user_type));
        $this->profile_picture = htmlspecialchars(strip_tags($this->profile_picture));

        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);
        $stmt->bindParam(":user_type", $this->user_type);
        $stmt->bindParam(":profile_picture", $this->profile_picture);

        if ($stmt->execute()) {
            $this->id = (int)$this->conn->lastInsertId();

            // Insert optional meta for university students
            if ($this->user_type === 'university') {
                $meta = [
                    'university' => $this->university ?: null,
                    'department' => $this->department ?: null,
                    'level' => $this->level ?: null,
                ];
                $metaStmt = $this->conn->prepare("INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (:uid, :mk, :mv)");
                foreach ($meta as $mk => $mv) {
                    if ($mv !== null) {
                        $metaStmt->execute([':uid' => $this->id, ':mk' => $mk, ':mv' => $mv]);
                    }
                }
            }
            return true;
        }

        return false;
    }

    // Get user by email
    public function getByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->user_type = $row['user_type'];
            $this->profile_picture = $row['profile_picture'];
            $this->points = (int)($row['points'] ?? 0);
            // Load meta from user_meta, since these are not columns on users
            $this->university = null;
            $this->department = null;
            $this->level = null;
            $metaKeys = "'university','department','level','privacy_show_school','privacy_show_stats','privacy_show_discussions','privacy_show_badges','privacy_show_battles'";
            $metaStmt = $this->conn->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = :uid AND meta_key IN ($metaKeys)");
            $metaStmt->execute([':uid' => $this->id]);
            while ($m = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($m['meta_key'] === 'university')
                    $this->university = $m['meta_value'];
                elseif ($m['meta_key'] === 'department')
                    $this->department = $m['meta_value'];
                elseif ($m['meta_key'] === 'level')
                    $this->level = $m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_badges')
                    $this->privacy_show_badges = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_battles')
                    $this->privacy_show_battles = (int)$m['meta_value'];
            }
            $this->email_verified = $row['email_verified'];
            $this->verification_token = $row['verification_token'];
            $this->reset_token = $row['reset_token'];
            $this->reset_token_expires = $row['reset_token_expires'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->status = $row['status'];

            return true;
        }

        return false;
    }

    // Get user by ID
    public function getById($id)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->user_type = $row['user_type'];
            $this->profile_picture = $row['profile_picture'];
            $this->points = (int)($row['points'] ?? 0);
            // Load meta from user_meta
            $this->university = null;
            $this->department = null;
            $this->level = null;
            $metaKeys = "'university','department','level','privacy_show_school','privacy_show_stats','privacy_show_discussions','privacy_show_badges','privacy_show_battles','privacy_allow_messages'";
            $metaStmt = $this->conn->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = :uid AND meta_key IN ($metaKeys)");
            $metaStmt->execute([':uid' => $this->id]);
            while ($m = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($m['meta_key'] === 'university')
                    $this->university = $m['meta_value'];
                if ($m['meta_key'] === 'department')
                    $this->department = $m['meta_value'];
                if ($m['meta_key'] === 'level')
                    $this->level = $m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_school')
                    $this->privacy_show_school = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_stats')
                    $this->privacy_show_stats = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_discussions')
                    $this->privacy_show_discussions = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_badges')
                    $this->privacy_show_badges = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_show_battles')
                    $this->privacy_show_battles = (int)$m['meta_value'];
                if ($m['meta_key'] === 'privacy_allow_messages')
                    $this->privacy_allow_messages = (int)$m['meta_value'];
            }
            $this->email_verified = $row['email_verified'];
            $this->verification_token = $row['verification_token'];
            $this->reset_token = $row['reset_token'];
            $this->reset_token_expires = $row['reset_token_expires'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->last_active = $row['last_active'] ?? $row['last_login'];
            $this->show_level_on_users_page = (int)($row['show_level_on_users_page'] ?? 1);
            $this->status = $row['status'];

            return true;
        }

        return false;
    }

    // Update user
    public function update()
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET name = :name, 
                     email = :email, 
                     profile_picture = :profile_picture,
                     status = :status,
                     show_level_on_users_page = :show_level_on_users_page
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize data (handle null values safely for PHP 8.1+)
        $this->name = htmlspecialchars(strip_tags($this->name ?? ''));
        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->profile_picture = !empty($this->profile_picture)
            ? htmlspecialchars(strip_tags($this->profile_picture))
            : ($this->profile_picture ?? '');
        $this->status = !empty($this->status)
            ? htmlspecialchars(strip_tags($this->status))
            : 'active';

        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":profile_picture", $this->profile_picture);
        $stmt->bindParam(":status", $this->status);
        
        $showLevel = isset($this->show_level_on_users_page) ? (int)$this->show_level_on_users_page : 1;
        $stmt->bindParam(":show_level_on_users_page", $showLevel, PDO::PARAM_INT);
        
        $stmt->bindParam(":id", $this->id);

        $success = $stmt->execute();

        if ($success) {
            // Update Privacy Meta
            $privacyMeta = [
                'privacy_show_school' => $this->privacy_show_school,
                'privacy_show_stats' => $this->privacy_show_stats,
                'privacy_show_discussions' => $this->privacy_show_discussions,
                'privacy_show_badges' => $this->privacy_show_badges,
                'privacy_show_battles' => $this->privacy_show_battles,
                'privacy_allow_messages' => $this->privacy_allow_messages
            ];

            foreach ($privacyMeta as $k => $v) {
                // Upsert logic for meta
                $check = $this->conn->prepare("SELECT id FROM user_meta WHERE user_id = ? AND meta_key = ?");
                $check->execute([$this->id, $k]);
                if ($check->rowCount() > 0) {
                    $this->conn->prepare("UPDATE user_meta SET meta_value = ? WHERE user_id = ? AND meta_key = ?")->execute([$v, $this->id, $k]);
                }
                else {
                    $this->conn->prepare("INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)")->execute([$this->id, $k, $v]);
                }
            }
        }

        return $success;
    }

    // Update last_active timestamp
    public function updateLastActive()
    {
        if (empty($this->id))
            return false;
        $query = "UPDATE " . $this->table_name . " SET last_active = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        return $stmt->execute();
    }

    // Update password
    public function updatePassword($new_password)
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET password_hash = :password_hash 
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Hash the new password
        $password_hash = hash_password($new_password);

        // Bind values
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Verify email
    public function verifyEmail($token)
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET email_verified = 1, 
                     verification_token = NULL 
                 WHERE verification_token = :token";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    // Generate email verification token
    public function generateVerificationToken($email)
    {
        if ($this->getByEmail($email)) {
            $this->verification_token = generate_token();
            
            $query = "UPDATE " . $this->table_name . " 
                     SET verification_token = :token
                     WHERE id = :id";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":token", $this->verification_token);
            $stmt->bindParam(":id", $this->id);
            
            if ($stmt->execute()) {
                return $this->verification_token;
            }
        }
        return false;
    }

    // Generate password reset token
    public function generatePasswordResetToken($email)
    {
        if ($this->getByEmail($email)) {
            $this->reset_token = generate_token();
            $this->reset_token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $query = "UPDATE " . $this->table_name . " 
                     SET reset_token = :reset_token,
                         reset_token_expires = :reset_token_expires
                     WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":reset_token", $this->reset_token);
            $stmt->bindParam(":reset_token_expires", $this->reset_token_expires);
            $stmt->bindParam(":id", $this->id);

            if ($stmt->execute()) {
                return $this->reset_token;
            }
        }

        return false;
    }

    // Reset password with token
    public function resetPassword($token, $new_password)
    {
        // Find user by reset token
        $query = "SELECT id FROM " . $this->table_name . " 
                 WHERE reset_token = :token 
                 AND reset_token_expires > NOW()
                 LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];

            // Update password and clear reset token
            $query = "UPDATE " . $this->table_name . " 
                     SET password_hash = :password_hash,
                         reset_token = NULL,
                         reset_token_expires = NULL
                     WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Hash the new password
            $password_hash = hash_password($new_password);

            // Bind values
            $stmt->bindParam(":password_hash", $password_hash);
            $stmt->bindParam(":id", $this->id);

            return $stmt->execute();
        }

        return false;
    }

    // Update last login time
    public function updateLastLogin()
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET last_login = NOW() 
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Check if email exists
    public function emailExists($email)
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Get all users (for admin)
    public function getAll($page = 1, $records_per_page = 10)
    {
        $offset = ($page - 1) * $records_per_page;

        $query = "SELECT * FROM " . $this->table_name . " 
                 ORDER BY created_at DESC 
                 LIMIT :offset, :records_per_page";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->bindParam(":records_per_page", $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    // Count total users (for pagination)
    public function countAll()
    {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
}
?>
