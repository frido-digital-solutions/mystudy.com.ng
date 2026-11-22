<?php
class Quiz
{
    protected PDO $conn;
    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
        $this->ensureTables();
        // NOTE: seed() removed — it auto-created dummy quizzes on every request
        // whenever count was zero, causing deleted quizzes to reappear.
    }

    private function ensureTables(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS quizzes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            subject VARCHAR(120) NULL,
            level VARCHAR(50) NULL,
            description TEXT NULL,
            type ENUM('secondary','university','both') NULL,
            is_prize TINYINT(1) NOT NULL DEFAULT 0,
            duration_seconds INT NOT NULL DEFAULT 900,
            slug VARCHAR(160) UNIQUE NULL,
            mode ENUM('normal','challenge') NOT NULL DEFAULT 'normal',
            event_start DATETIME NULL,
            event_end DATETIME NULL,
            prize_type ENUM('money','text') NULL,
            prize_amount DECIMAL(12,2) NULL,
            prize_text VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Alter table for backward compatibility
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS type ENUM('secondary','university','both') NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes MODIFY COLUMN type ENUM('secondary','university','both') NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS is_prize TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS duration_seconds INT NOT NULL DEFAULT 900"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS slug VARCHAR(160) UNIQUE NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS mode ENUM('normal','challenge') NOT NULL DEFAULT 'normal'"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS event_start DATETIME NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS event_end DATETIME NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS prize_type ENUM('money','text') NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS prize_amount DECIMAL(12,2) NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS prize_text VARCHAR(255) NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS total_phases INT NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS current_phase INT NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS is_paid TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS entry_fee DECIMAL(12,2) NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS access_code VARCHAR(50) NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS max_participants INT NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS required_level INT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS exam_types VARCHAR(100) NULL"); } catch (Throwable $e) {}

        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            question TEXT NOT NULL,
            explanation TEXT NULL,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $this->conn->exec("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS explanation TEXT NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS question_type VARCHAR(20) NOT NULL DEFAULT 'objective'"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS correct_answer VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
        try { $this->conn->exec("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}

        $this->conn->exec("CREATE TABLE IF NOT EXISTS quiz_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            option_text VARCHAR(255) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $this->conn->exec("ALTER TABLE quiz_options ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
    }

    private function seed(): void
    {
        $count = (int)$this->conn->query('SELECT COUNT(*) FROM quizzes')->fetchColumn();
        $stmt = $this->conn->prepare('INSERT INTO quizzes (title, subject, level, description, type, duration_seconds, slug, mode) VALUES (:t,:s,:l,:d,:type,:dur,:slug, :mode)');
        $subjects = ['Mathematics','Physics','Chemistry','Biology','English','Economics','Government','History','Geography','Computer Science'];
        if ($count === 0) {
            for ($i=1; $i<=10; $i++) {
                $lvl = ($i <= 5 ? 'Secondary' : 'University');
                $title = $subjects[$i-1] . ' Quiz';
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $title . '-' . $i));
                $stmt->execute([
                    ':t' => $title,
                    ':s' => $subjects[$i-1],
                    ':l' => $lvl,
                    ':d' => 'Standard quiz for ' . $subjects[$i-1] . ' with sample questions and explanations.',
                    ':type' => ($i <= 5 ? 'secondary' : 'university'),
                    ':dur' => 900,
                    ':slug' => $slug,
                    ':mode' => 'normal'
                ]);
            }
        }

        // Ensure Multi-Subject Sandbox exists
        $multiCount = (int)$this->conn->query('SELECT COUNT(*) FROM quizzes WHERE slug = "multi-subject-sandbox"')->fetchColumn();
        if ($multiCount === 0) {
            $stmt->execute([
                ':t' => 'Multi-Subject Sandbox',
                ':s' => 'Mixed',
                ':l' => 'All Levels',
                ':d' => 'A dynamic test sandbox spanning multiple subjects.',
                ':type' => null,
                ':dur' => 3600,
                ':slug' => 'multi-subject-sandbox',
                ':mode' => 'normal'
            ]);
        }

        // Seed 10 dummy challenge quizzes if none exist
        $challengeCount = (int)$this->conn->query('SELECT COUNT(*) FROM quizzes WHERE mode = "challenge"')->fetchColumn();
        if ($challengeCount === 0) {
            $challengeStmt = $this->conn->prepare('INSERT INTO quizzes (title, subject, level, description, type, duration_seconds, slug, mode, event_start, event_end, prize_type, prize_amount, prize_text) VALUES (:t,:s,:l,:d,:type,:dur,:slug,:mode,:es,:ee,:pt,:pa,:ptxt)');
            $now = time();
            
            // Challenge 1: Ended challenge (Secondary, money prize)
            $challengeStmt->execute([
                ':t' => 'Mathematics Speed Challenge',
                ':s' => 'Mathematics',
                ':l' => 'Secondary',
                ':d' => 'Test your speed and accuracy in mathematics. Top scorers win cash prizes!',
                ':type' => 'secondary',
                ':dur' => 600,
                ':slug' => 'math-speed-challenge-ended',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 86400*2), // 2 days ago
                ':ee' => date('Y-m-d H:i:s', $now - 86400*1), // 1 day ago
                ':pt' => 'money',
                ':pa' => 50000.00,
                ':ptxt' => null
            ]);

            // Challenge 2: Ongoing challenge (University, money prize)
            $challengeStmt->execute([
                ':t' => 'Physics Master Challenge',
                ':s' => 'Physics',
                ':l' => 'University',
                ':d' => 'Advanced physics challenge for university students. Prize pool of 100k!',
                ':type' => 'university',
                ':dur' => 900,
                ':slug' => 'physics-master-ongoing',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 3600*2), // 2 hours ago
                ':ee' => date('Y-m-d H:i:s', $now + 3600*10), // 10 hours from now
                ':pt' => 'money',
                ':pa' => 100000.00,
                ':ptxt' => null
            ]);

            // Challenge 3: Upcoming challenge (Secondary, text prize)
            $challengeStmt->execute([
                ':t' => 'Chemistry Quiz Challenge',
                ':s' => 'Chemistry',
                ':l' => 'Secondary',
                ':d' => 'Win free data bundles and study materials in this chemistry challenge!',
                ':type' => 'secondary',
                ':dur' => 750,
                ':slug' => 'chemistry-upcoming',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now + 3600*3), // 3 hours from now
                ':ee' => date('Y-m-d H:i:s', $now + 86400*1), // 1 day from now
                ':pt' => 'text',
                ':pa' => null,
                ':ptxt' => 'Top 3 get 5GB Data + Free Study Materials'
            ]);

            // Challenge 4: Ongoing challenge (Secondary, money prize)
            $challengeStmt->execute([
                ':t' => 'Biology WAEC Prep Challenge',
                ':s' => 'Biology',
                ':l' => 'Secondary',
                ':d' => 'WAEC-style biology questions. Cash prizes for top performers!',
                ':type' => 'secondary',
                ':dur' => 900,
                ':slug' => 'biology-waec-ongoing',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 3600*5), // 5 hours ago
                ':ee' => date('Y-m-d H:i:s', $now + 3600*19), // 19 hours from now
                ':pt' => 'money',
                ':pa' => 75000.00,
                ':ptxt' => null
            ]);

            // Challenge 5: Upcoming challenge (University, money prize)
            $challengeStmt->execute([
                ':t' => 'Computer Science Mega Challenge',
                ':s' => 'Computer Science',
                ':l' => 'University',
                ':d' => 'Programming, algorithms, and data structures. Win big prizes!',
                ':type' => 'university',
                ':dur' => 1200,
                ':slug' => 'cs-mega-upcoming',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now + 86400*2), // 2 days from now
                ':ee' => date('Y-m-d H:i:s', $now + 86400*3), // 3 days from now
                ':pt' => 'money',
                ':pa' => 200000.00,
                ':ptxt' => null
            ]);

            // Challenge 6: Ongoing challenge (Secondary, text prize)
            $challengeStmt->execute([
                ':t' => 'English Grammar Sprint',
                ':s' => 'English',
                ':l' => 'Secondary',
                ':d' => 'Test your English grammar skills. Winners get free tutoring sessions!',
                ':type' => 'secondary',
                ':dur' => 600,
                ':slug' => 'english-sprint-ongoing',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 3600*1), // 1 hour ago
                ':ee' => date('Y-m-d H:i:s', $now + 3600*5), // 5 hours from now
                ':pt' => 'text',
                ':pa' => null,
                ':ptxt' => 'Top 5 get Free 1-Month Premium Tutoring'
            ]);

            // Challenge 7: Upcoming challenge (University, text prize)
            $challengeStmt->execute([
                ':t' => 'Economics Expert Challenge',
                ':s' => 'Economics',
                ':l' => 'University',
                ':d' => 'Economics challenge covering micro and macro topics. Exclusive prizes!',
                ':type' => 'university',
                ':dur' => 900,
                ':slug' => 'economics-expert-upcoming',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now + 3600*12), // 12 hours from now
                ':ee' => date('Y-m-d H:i:s', $now + 86400*2), // 2 days from now
                ':pt' => 'text',
                ':pa' => null,
                ':ptxt' => 'Winners get Study Tablet + Scholarship Info'
            ]);

            // Challenge 8: Ongoing challenge (Secondary, money prize)
            $challengeStmt->execute([
                ':t' => 'Government & Civics Challenge',
                ':s' => 'Government',
                ':l' => 'Secondary',
                ':d' => 'Nigerian government and civics quiz. Cash prizes await!',
                ':type' => 'secondary',
                ':dur' => 750,
                ':slug' => 'govt-civics-ongoing',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 3600*3), // 3 hours ago
                ':ee' => date('Y-m-d H:i:s', $now + 3600*9), // 9 hours from now
                ':pt' => 'money',
                ':pa' => 30000.00,
                ':ptxt' => null
            ]);

            // Challenge 9: Upcoming challenge (Secondary, money prize)
            $challengeStmt->execute([
                ':t' => 'Geography JAMB Prep Challenge',
                ':s' => 'Geography',
                ':l' => 'Secondary',
                ':d' => 'JAMB-focused geography challenge. Prepare and win!',
                ':type' => 'secondary',
                ':dur' => 900,
                ':slug' => 'geo-jamb-upcoming',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now + 3600*6), // 6 hours from now
                ':ee' => date('Y-m-d H:i:s', $now + 86400*1 + 3600*6), // 1 day + 6 hours from now
                ':pt' => 'money',
                ':pa' => 60000.00,
                ':ptxt' => null
            ]);

            // Challenge 10: Ongoing challenge (University, text prize)
            $challengeStmt->execute([
                ':t' => 'History Knowledge Challenge',
                ':s' => 'History',
                ':l' => 'University',
                ':d' => 'Test your knowledge of world and Nigerian history. Special rewards!',
                ':type' => 'university',
                ':dur' => 900,
                ':slug' => 'history-knowledge-ongoing',
                ':mode' => 'challenge',
                ':es' => date('Y-m-d H:i:s', $now - 3600*4), // 4 hours ago
                ':ee' => date('Y-m-d H:i:s', $now + 3600*20), // 20 hours from now
                ':pt' => 'text',
                ':pa' => null,
                ':ptxt' => 'Top performers get Book Vouchers & Certificate'
            ]);

            // Seed specific locked quizzes for User Testing
            $lockedStmt = $this->conn->prepare('INSERT INTO quizzes (title, subject, level, description, type, duration_seconds, slug, mode, required_level) VALUES (:t,:s,:l,:d,:type,:dur,:slug,:mode,:req)');
            
            $lockedStmt->execute([
                ':t' => 'Uni Senior Challenge (Level 7+)',
                ':s' => 'Economics',
                ':l' => 'University',
                ':d' => 'This quiz is explicitly locked for university students below level 7.',
                ':type' => 'university',
                ':dur' => 900,
                ':slug' => 'uni-lock-level-7',
                ':mode' => 'challenge',
                ':req' => 7
            ]);

            $lockedStmt->execute([
                ':t' => 'Sec Pioneer Challenge (Level 9+)',
                ':s' => 'Mathematics',
                ':l' => 'Secondary',
                ':d' => 'This physics quiz explicitly requires level 9 or higher for secondary students.',
                ':type' => 'secondary',
                ':dur' => 900,
                ':slug' => 'sec-lock-level-9',
                ':mode' => 'challenge',
                ':req' => 9
            ]);
        }

        // Seed Mixed-Type Quiz (objective + subjective)
        $mixedCount = (int)$this->conn->query('SELECT COUNT(*) FROM quizzes WHERE slug = "general-knowledge-mixed"')->fetchColumn();
        if ($mixedCount === 0) {
            $mixedStmt = $this->conn->prepare('INSERT INTO quizzes (title, subject, level, description, type, duration_seconds, slug, mode) VALUES (:t,:s,:l,:d,:type,:dur,:slug,:mode)');
            $mixedStmt->execute([
                ':t' => 'General Knowledge — Mixed Types',
                ':s' => 'General Knowledge',
                ':l' => 'General',
                ':d' => 'A quiz with both multiple-choice and subjective (type-your-answer) questions.',
                ':type' => 'both',
                ':dur' => 600,
                ':slug' => 'general-knowledge-mixed',
                ':mode' => 'normal'
            ]);
            $mixedQuizId = (int)$this->conn->lastInsertId();

            $mqStmt = $this->conn->prepare('INSERT INTO quiz_questions (quiz_id, question, explanation, question_type, correct_answer) VALUES (:qid,:q,:e,:qt,:ca)');
            $moStmt = $this->conn->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (:qid,:txt,:ok)');

            // Q1: Objective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'What is the capital of Nigeria?', ':e'=>'Abuja has been the capital since 1991.', ':qt'=>'objective', ':ca'=>null]);
            $qid = (int)$this->conn->lastInsertId();
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Abuja', ':ok'=>1]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Lagos', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Kano', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Port Harcourt', ':ok'=>0]);

            // Q2: Subjective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'What planet is known as the Red Planet?', ':e'=>'Mars appears red due to iron oxide on its surface.', ':qt'=>'subjective', ':ca'=>'Mars']);

            // Q3: Objective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'Which organ pumps blood in the human body?', ':e'=>'The heart is a muscular organ that circulates blood.', ':qt'=>'objective', ':ca'=>null]);
            $qid = (int)$this->conn->lastInsertId();
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Heart', ':ok'=>1]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Lungs', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Brain', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Liver', ':ok'=>0]);

            // Q4: Subjective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'What is the chemical symbol for water?', ':e'=>'Water is composed of two hydrogen atoms and one oxygen atom.', ':qt'=>'subjective', ':ca'=>'H2O']);

            // Q5: Objective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'How many continents are there in the world?', ':e'=>'The 7 continents are Africa, Asia, Europe, North America, South America, Antarctica, Australia.', ':qt'=>'objective', ':ca'=>null]);
            $qid = (int)$this->conn->lastInsertId();
            $moStmt->execute([':qid'=>$qid, ':txt'=>'7', ':ok'=>1]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'5', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'6', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'8', ':ok'=>0]);

            // Q6: Subjective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'What gas do plants absorb from the atmosphere for photosynthesis?', ':e'=>'Plants use carbon dioxide along with water and sunlight to make food.', ':qt'=>'subjective', ':ca'=>'Carbon dioxide']);

            // Q7: Objective
            $mqStmt->execute([':qid'=>$mixedQuizId, ':q'=>'Which is the largest ocean on Earth?', ':e'=>'The Pacific Ocean covers more area than all the land area combined.', ':qt'=>'objective', ':ca'=>null]);
            $qid = (int)$this->conn->lastInsertId();
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Pacific Ocean', ':ok'=>1]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Atlantic Ocean', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Indian Ocean', ':ok'=>0]);
            $moStmt->execute([':qid'=>$qid, ':txt'=>'Arctic Ocean', ':ok'=>0]);
        }

        // Backfill: Ensure each quiz has at least 10 questions populated for testing
        $quizIds = $this->conn->query('SELECT id, subject, mode FROM quizzes ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $qStmt = $this->conn->prepare('INSERT INTO quiz_questions (quiz_id, question, explanation) VALUES (:qid,:q,:e)');
        $oStmt = $this->conn->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (:qid,:txt,:ok)');
        foreach ($quizIds as $index => $qi) {
            $existing = $this->conn->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :id');
            $existing->execute([':id'=>$qi['id']]);
            $countQ = (int)$existing->fetchColumn();
            $isChallenge = ($qi['mode'] ?? 'normal') === 'challenge';

            // For the first three subjects, insert more realistic sample questions
            if ($countQ === 0 && $index < 3) {
                $samples = [];
                if (strcasecmp($qi['subject'], 'Mathematics') === 0) {
                    $samples = [
                        ['What is the value of 2x + 5 = 13?', ['x = 4', 1], ['x = 3', 0], ['x = 5', 0], ['x = 6', 0], 'Subtract 5 and divide both sides by 2.'],
                        ['The area of a rectangle is 48 cm² and the length is 12 cm. What is the width?', ['4 cm', 1], ['12 cm', 0], ['6 cm', 0], ['8 cm', 0], 'Area = length × width.'],
                        ['Solve: 3(2x − 1) = 15', ['x = 3', 1], ['x = 2', 0], ['x = 1', 0], ['x = 5', 0], 'Expand and isolate x.'],
                        ['What is the mean of 2, 4, 6, 8?', ['5', 1], ['6', 0], ['4', 0], ['7', 0], 'Mean is sum divided by count.'],
                        ['If y varies directly as x and y = 10 when x = 2, find y when x = 5.', ['25', 1], ['20', 0], ['15', 0], ['12', 0], 'Direct variation: y = kx.'],
                    ];
                } elseif (strcasecmp($qi['subject'], 'Physics') === 0) {
                    $samples = [
                        ['Which quantity is a vector?', ['Velocity', 1], ['Speed', 0], ['Distance', 0], ['Time', 0], 'Vectors have magnitude and direction.'],
                        ['The SI unit of force is:', ['Newton', 1], ['Joule', 0], ['Watt', 0], ['Pascal', 0], 'Definition of force unit.'],
                        ['What device is used to measure electric current?', ['Ammeter', 1], ['Voltmeter', 0], ['Galvanometer', 0], ['Ohmmeter', 0], 'Ammeter measures current.'],
                        ['If a body is in uniform motion, its acceleration is:', ['Zero', 1], ['Constant', 0], ['Increasing', 0], ['Decreasing', 0], 'Uniform motion implies constant velocity.'],
                        ['Energy possessed by a body due to its position is called:', ['Potential energy', 1], ['Kinetic energy', 0], ['Heat energy', 0], ['Sound energy', 0], 'By definition.'],
                    ];
                } elseif (strcasecmp($qi['subject'], 'Chemistry') === 0) {
                    $samples = [
                        ['The atomic number of an element is equal to the number of:', ['Protons', 1], ['Neutrons', 0], ['Electrons + Neutrons', 0], ['Nucleons', 0], 'Atomic number = protons.'],
                        ['Which gas turns limewater milky?', ['Carbon dioxide', 1], ['Oxygen', 0], ['Hydrogen', 0], ['Nitrogen', 0], 'CO2 forms calcium carbonate.'],
                        ['The pH of a neutral solution at 25°C is:', ['7', 1], ['1', 0], ['14', 0], ['9', 0], 'Neutral pH is 7.'],
                        ['What is the chemical formula of common salt?', ['NaCl', 1], ['KCl', 0], ['Na2CO3', 0], ['CaCl2', 0], 'Sodium chloride.'],
                        ['Which bond involves sharing of electron pairs?', ['Covalent bond', 1], ['Ionic bond', 0], ['Metallic bond', 0], ['Hydrogen bond', 0], 'Definition of covalent bond.'],
                    ];
                }

                foreach ($samples as $s) {
                    [$qText, $a1, $a2, $a3, $a4, $exp] = $s;
                    $qStmt->execute([':qid'=>$qi['id'], ':q'=>$qText, ':e'=>$exp]);
                    $questionId = (int)$this->conn->lastInsertId();
                    foreach ([$a1, $a2, $a3, $a4] as $opt) {
                        [$text, $ok] = $opt;
                        $oStmt->execute([':qid'=>$questionId, ':txt'=>$text, ':ok'=>$ok]);
                    }
                }

                // Fill up to 10 if fewer
                for ($n=count($samples)+1; $n<=10; $n++) {
                    $question = $qi['subject'] . " practice question $n?\n(Select the best answer)";
                    $explain = 'Explanation: Review core concept in ' . $qi['subject'] . ' for question ' . $n . '.';
                    $qStmt->execute([':qid'=>$qi['id'], ':q'=>$question, ':e'=>$explain]);
                    $questionId = (int)$this->conn->lastInsertId();
                    $options = [["Option A", 1],["Option B", 0],["Option C", 0],["Option D", 0]];
                    foreach ($options as [$text, $ok]) { $oStmt->execute([':qid'=>$questionId, ':txt'=>$text, ':ok'=>$ok]); }
                }
                continue; // next quiz
            }

            // Fill challenge quizzes with generic questions for testing
            if ($countQ === 0 && $isChallenge) {
                for ($n=1; $n<=10; $n++) {
                    $question = "Challenge Question $n: " . $qi['subject'] . " knowledge test.\n(Select the best answer)";
                    $explain = 'This is a challenge question testing your understanding of ' . $qi['subject'] . '. Review the concept carefully.';
                    $qStmt->execute([':qid'=>$qi['id'], ':q'=>$question, ':e'=>$explain]);
                    $questionId = (int)$this->conn->lastInsertId();
                    $options = [["Option A", 1],["Option B", 0],["Option C", 0],["Option D", 0]];
                    foreach ($options as [$text, $ok]) { $oStmt->execute([':qid'=>$questionId, ':txt'=>$text, ':ok'=>$ok]); }
                }
            }

            // Do not auto-fill other normal quizzes. Leave them empty for now.
        }
    }

    public function list(int $limit=12, int $offset=0, ?string $level=null): array
    {
        $where = 'WHERE (mode IS NULL OR mode != "challenge")';
        $params = [];
        if ($level) { 
            $where .= ' AND level = :lvl'; 
            $params[':lvl'] = $level; 
        }
        $sql = "SELECT * FROM quizzes $where ORDER BY created_at DESC LIMIT :l OFFSET :o";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAll(?string $level=null): int
    {
        $where = 'WHERE (mode IS NULL OR mode != "challenge")';
        $params = [];
        if ($level) { 
            $where .= ' AND level = :lvl'; 
            $params[':lvl'] = $level; 
        }
        $sql = "SELECT COUNT(*) FROM quizzes $where";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // New Custom Exam Builder requirement: Fetch distinct free practice quizzes
    public function getValidFreeQuizzes(?string $level = null): array
    {
        $where = 'WHERE is_paid = 0 AND (mode IS NULL OR mode = "normal")';
        $params = [];
        if ($level) {
            $where .= ' AND (level = :lvl OR level = "General" OR level = "All Levels" OR type = "both")';
            $params[':lvl'] = $level;
        }
        $sql = "SELECT id, title, subject, level, exam_types FROM quizzes $where ORDER BY created_at DESC LIMIT 50";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quizzes WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quizzes WHERE slug = :slug');
        $stmt->execute([':slug'=>$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getQuestions(int $quizId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM quiz_questions WHERE quiz_id = :id');
        $stmt->execute([':id'=>$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($questions) {
            $qIds = array_column($questions, 'id');
            $in = implode(',', array_fill(0, count($qIds), '?'));
            $oStmt = $this->conn->prepare("SELECT * FROM quiz_options WHERE question_id IN ($in) ORDER BY id ASC");
            $oStmt->execute($qIds);
            $allOptions = $oStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byQ = [];
            foreach ($allOptions as $opt) { $byQ[$opt['question_id']][] = $opt; }
            foreach ($questions as &$q) { $q['options'] = $byQ[$q['id']] ?? []; }
        }
        return $questions;
    }

    public function getQuestionCount(int $quizId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :id');
        $stmt->execute([':id' => $quizId]);
        return (int)$stmt->fetchColumn();
    }

    public function getRandomQuestionsByQuizIds(array $quizIds, int $limitPerQuiz = 10): array
    {
        $allQuestions = [];
        // Flatten the valid integer IDs
        $validIds = [];
        foreach ($quizIds as $id) {
            if ((int)$id > 0) $validIds[] = (int)$id;
        }
        
        if (empty($validIds)) {
            return [];
        }

        $stmtQ = $this->conn->prepare('
            SELECT q.*, z.title as origin_quiz_title, z.subject as origin_subject 
            FROM quiz_questions q 
            JOIN quizzes z ON q.quiz_id = z.id 
            WHERE q.quiz_id = :qid 
            ORDER BY RAND() LIMIT :l
        ');
        $stmtO = $this->conn->prepare('SELECT * FROM quiz_options WHERE question_id = :qid ORDER BY id ASC');

        foreach ($validIds as $idVal) {
            $stmtQ->bindValue(':qid', $idVal, PDO::PARAM_INT);
            $stmtQ->bindValue(':l', $limitPerQuiz, PDO::PARAM_INT);
            $stmtQ->execute();
            $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($questions as &$q) {
                // Keep track of internal ID for answer validation later if needed
                $stmtO->execute([':qid' => $q['id']]);
                $q['options'] = $stmtO->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $allQuestions[] = $q;
            }
        }
        
        // Shuffle the final mega-array of questions so they aren't strictly ordered
        shuffle($allQuestions);
        return $allQuestions;
    }

    public function listChallenges(?string $level = null, int $limit = 12): array
    {
        // Fetch challenge-mode quizzes that are upcoming or ongoing (event_end in future)
        $where = 'WHERE mode = "challenge" AND event_end IS NOT NULL AND event_end >= NOW()';
        $params = [];
        if ($level) { $where .= ' AND level = :lvl'; $params[':lvl'] = $level; }
        $sql = "SELECT * FROM quizzes $where ORDER BY (event_start IS NULL), event_start ASC LIMIT :l";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Exact 50/30/20 prize split. Computed in whole 0.01 units so the three shares always
     * add back up to the pool: 1st and 2nd are floored, 3rd takes the remainder.
     */
    public static function prizeShare(float $pool, int $rank): float
    {
        if ($pool <= 0 || $rank < 1 || $rank > 3) {
            return 0.0;
        }
        $units = (int) round($pool * 100);
        $first = intdiv($units * 50, 100);
        $second = intdiv($units * 30, 100);
        $shares = [$first, $second, $units - $first - $second];
        return $shares[$rank - 1] / 100;
    }

    public function processEndedChallenges(PDO $pdo): int
    {
        $stmt = $this->conn->prepare("
            SELECT id, title, prize_type, prize_amount, prize_text 
            FROM quizzes 
            WHERE mode = 'challenge' 
              AND event_end <= NOW() 
              AND rewards_notified = 0 
              AND (prize_amount IS NOT NULL OR prize_text IS NOT NULL OR is_prize = 1)
        ");
        $stmt->execute();
        $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($challenges)) {
            return 0;
        }

        require_once __DIR__ . '/QuizAttempt.php';
        require_once __DIR__ . '/Notification.php';
        
        $attemptModel = new QuizAttempt($pdo);
        $notifModel = new Notification($pdo);
        $processed = 0;

        foreach ($challenges as $c) {
            $quizId = (int)$c['id'];
            $title = $c['title'];
            $basePrize = (float)($c['prize_amount'] ?? 0);
            $prizeType = $c['prize_type'];

            $leaderboard = $attemptModel->topScores($quizId, null, 3);
            if (!empty($leaderboard)) {
                foreach ($leaderboard as $idx => $entry) {
                    $userId = (int)$entry['user_id'];
                    $rank = $idx + 1;
                    $rankLabel = ['1st', '2nd', '3rd'][$idx] ?? $rank . 'th';
                    
                    // Calculate 50/30/20 split if there is a monetary or numeric prize
                    $amount = self::prizeShare($basePrize, $rank);
                    
                    $score = (int)($entry['score'] ?? 0);
                    $total = (int)($entry['total'] ?? 0);
                    
                    // Pass parameters in the link to populate the 3D Banner securely
                    $linkParams = "?quiz_id={$quizId}&rank={$rank}&score={$score}&total={$total}&amt={$amount}";
                    
                    $msgTitle = '🏆 Claim Your Reward: ' . $title;
                    if ($amount > 0 && in_array($prizeType, ['money', 'data', 'airtime'])) {
                        // Data prizes are fractional (2.5 GB); rounding to whole units inflates the pool.
                        $amtFormatted = format_prize($amount, $prizeType);
                        $msgBody = "Congratulations! You finished {$rankLabel} for '{$title}' and won {$amtFormatted}! Click here to claim your reward now!";
                    } else {
                        $msgBody = "Congratulations! You finished {$rankLabel} for '{$title}'. Click here to claim your reward now!";
                    }
                    
                    $link = SITE_URL . '/claim_reward.php' . $linkParams;
                    $notifModel->create($userId, 'reward_win', $msgTitle, $msgBody, $link);
                }
            }
            
            // Mark as notified
            $upd = $this->conn->prepare("UPDATE quizzes SET rewards_notified = 1 WHERE id = ?");
            $upd->execute([$quizId]);
            $processed++;
        }

        return $processed;
    }
}

// Admin helpers (CRUD)
class QuizAdmin extends Quiz
{
    // List all quizzes including challenges (for admin panel)
    public function listAll(int $limit = 100, int $offset = 0, ?string $level = null): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        if ($level) {
            $where .= ' AND level = :lvl';
            $params[':lvl'] = $level;
        }
        $sql = "SELECT * FROM quizzes $where ORDER BY created_at DESC LIMIT :l OFFSET :o";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    // Create a new quiz and return its ID
    public function createQuiz(array $data): int
    {
        $sql = 'INSERT INTO quizzes (title, subject, level, description, type, is_prize, duration_seconds, slug, mode, event_start, event_end, prize_type, prize_amount, prize_text, total_phases, current_phase, is_paid, entry_fee, access_code, max_participants, required_level, exam_types) VALUES (:title,:subject,:level,:description,:type,:is_prize,:duration_seconds,:slug,:mode,:event_start,:event_end,:prize_type,:prize_amount,:prize_text,:total_phases,:current_phase,:is_paid,:entry_fee,:access_code,:max_participants,:req,:exam_types)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':title' => trim($data['title'] ?? ''),
            ':subject' => $data['subject'] ?? null,
            ':level' => $data['level'] ?? null,
            ':description' => $data['description'] ?? null,
            ':type' => $data['type'] ?? null,
            ':is_prize' => !empty($data['is_prize']) ? 1 : 0,
            ':duration_seconds' => (int)($data['duration_seconds'] ?? 900),
            ':slug' => $data['slug'] ?? null,
            ':mode' => in_array(($data['mode'] ?? 'normal'), ['normal','challenge'], true) ? $data['mode'] : 'normal',
            ':event_start' => $data['event_start'] ?? null,
            ':event_end' => $data['event_end'] ?? null,
            ':prize_type' => in_array(($data['prize_type'] ?? null), ['money','airtime','data','text'], true) ? $data['prize_type'] : null,
            ':prize_amount' => isset($data['prize_amount']) && $data['prize_amount'] !== '' ? $data['prize_amount'] : null,
            ':prize_text' => $data['prize_text'] ?? null,
            ':total_phases' => 1,
            ':current_phase' => 1,
            ':is_paid' => !empty($data['is_paid']) ? 1 : 0,
            ':entry_fee' => isset($data['entry_fee']) && $data['entry_fee'] !== '' ? $data['entry_fee'] : null,
            ':access_code' => $data['access_code'] ?? null,
            ':max_participants' => !empty($data['max_participants']) ? (int)$data['max_participants'] : null,
            ':req' => (int)($data['required_level'] ?? 0),
            ':exam_types' => $data['exam_types'] ?? null,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    // Update quiz by ID
    public function updateQuiz(int $id, array $data): bool
    {
        $sql = 'UPDATE quizzes SET title=:title, subject=:subject, level=:level, description=:description, type=:type, is_prize=:is_prize, duration_seconds=:duration_seconds, slug=:slug, mode=:mode, event_start=:event_start, event_end=:event_end, prize_type=:prize_type, prize_amount=:prize_amount, prize_text=:prize_text, total_phases=:total_phases, current_phase=:current_phase, is_paid=:is_paid, entry_fee=:entry_fee, access_code=:access_code, max_participants=:max_participants, required_level=:req, exam_types=:exam_types WHERE id=:id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => trim($data['title'] ?? ''),
            ':subject' => $data['subject'] ?? null,
            ':level' => $data['level'] ?? null,
            ':description' => $data['description'] ?? null,
            ':type' => $data['type'] ?? null,
            ':is_prize' => !empty($data['is_prize']) ? 1 : 0,
            ':duration_seconds' => (int)($data['duration_seconds'] ?? 900),
            ':slug' => $data['slug'] ?? null,
            ':mode' => in_array(($data['mode'] ?? 'normal'), ['normal','challenge'], true) ? $data['mode'] : 'normal',
            ':event_start' => $data['event_start'] ?? null,
            ':event_end' => $data['event_end'] ?? null,
            ':prize_type' => in_array(($data['prize_type'] ?? null), ['money','airtime','data','text'], true) ? $data['prize_type'] : null,
            ':prize_amount' => isset($data['prize_amount']) && $data['prize_amount'] !== '' ? $data['prize_amount'] : null,
            ':prize_text' => $data['prize_text'] ?? null,
            ':total_phases' => 1,
            ':current_phase' => 1,
            ':is_paid' => !empty($data['is_paid']) ? 1 : 0,
            ':entry_fee' => isset($data['entry_fee']) && $data['entry_fee'] !== '' ? $data['entry_fee'] : null,
            ':access_code' => $data['access_code'] ?? null,
            ':max_participants' => !empty($data['max_participants']) ? (int)$data['max_participants'] : null,
            ':req' => (int)($data['required_level'] ?? 0),
            ':exam_types' => $data['exam_types'] ?? null,
        ]);
    }

    public function deleteQuiz(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM quizzes WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    // Get full quiz with questions and options
    public function getFull(int $id): ?array
    {
        $quiz = $this->getById($id);
        if (!$quiz) return null;
        $questions = $this->getQuestions($id);
        $quiz['questions'] = $questions;
        return $quiz;
    }

    // Save questions and options (replace strategy)
    public function saveQuestions(int $quizId, array $questions): void
    {
        // Replace existing: delete and insert
        $delQ = $this->conn->prepare('DELETE FROM quiz_questions WHERE quiz_id = :id');
        $delQ->execute([':id' => $quizId]);

        $qStmt = $this->conn->prepare('INSERT INTO quiz_questions (quiz_id, question, explanation, question_type, correct_answer, image_path) VALUES (:qid,:q,:e,:qt,:ca,:img)');
        $oStmt = $this->conn->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct, image_path) VALUES (:qid,:txt,:ok,:img)');

        foreach ($questions as $q) {
            $text = trim((string)($q['question'] ?? ''));
            if ($text === '') { continue; }
            $explain = trim((string)($q['explanation'] ?? '')) ?: null;
            $qType = (isset($q['question_type']) && $q['question_type'] === 'subjective') ? 'subjective' : 'objective';
            $correctAns = ($qType === 'subjective') ? trim((string)($q['correct_answer'] ?? '')) ?: null : null;
            $qImage = trim((string)($q['image_path'] ?? '')) ?: null;

            $qStmt->execute([':qid' => $quizId, ':q' => $text, ':e' => $explain, ':qt' => $qType, ':ca' => $correctAns, ':img' => $qImage]);
            $qid = (int)$this->conn->lastInsertId();

            // Only save options for objective questions
            if ($qType === 'objective') {
                $opts = $q['options'] ?? [];
                foreach ($opts as $opt) {
                    $optText = trim((string)($opt['text'] ?? ''));
                    $optImage = trim((string)($opt['image_path'] ?? '')) ?: null;
                    // A picture can be the whole option, so text alone is not the test.
                    if ($optText === '' && $optImage === null) { continue; }
                    $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
                    $oStmt->execute([':qid' => $qid, ':txt' => $optText, ':ok' => $isCorrect, ':img' => $optImage]);
                }
            }
        }
    }
}
