<?php
/**
 * Every table this site knows about, written out so a server can make them for
 * itself.
 *
 * WHY THIS FILE EXISTS
 *   The live database and the development database are the same site at two
 *   moments in time. When code is written that needs a table nobody has run
 *   the SQL for, the question "does the server have it?" is answered by
 *   guessing, and a guess that is wrong shows up as a blank page or a 500
 *   rather than as a missing feature.
 *
 *   So the tables are shipped with the code, exactly as they are on the
 *   development machine, and a server creates any that are missing. Every
 *   statement is "IF NOT EXISTS", so a table that is already there is never
 *   touched - not its columns, not its rows, not its indexes. This can only
 *   ever add what is missing.
 *
 * HOW OFTEN IT RUNS
 *   Once. The version below is written to a small file in the cache folder
 *   after the tables have been checked, and the work is skipped while that
 *   file still matches. A deploy that adds a table changes the version, and
 *   the next request does the work once.
 *
 * The statements come from the development database itself, dumped with
 * mysqldump --no-data, so they are the shape the code actually expects. When
 * the development database changes, this file is regenerated with:
 *
 *   mysqldump --no-data --skip-comments --skip-add-drop-table --compact \
 *     <database> > schema.sql
 *   node tools/make-schema-php.js schema.sql includes/schema-tables.php
 */

define('MSB_SCHEMA_VERSION', 'f7760090dbdf');

/**
 * @return array<int, string> the CREATE TABLE statements, in order
 */
function msb_schema_statements(): array
{
    static $statements = null;
    if ($statements !== null) return $statements;

    $statements = [
        // users
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `users` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','university','secondary') NOT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `points` int(11) DEFAULT 0,
  `current_streak` int(11) DEFAULT 0,
  `longest_streak` int(11) DEFAULT 0,
  `last_quiz_date` date DEFAULT NULL,
  `report_strikes` int(11) DEFAULT 0,
  `is_frozen` tinyint(1) DEFAULT 0,
  `show_level_on_users_page` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // admin_activity_log
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_activity_log` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_user_id` (`admin_user_id`),
  CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // admin_permissions
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_permissions` (
`admin_user_id` int(11) NOT NULL,
  `page_key` varchar(50) NOT NULL,
  PRIMARY KEY (`admin_user_id`,`page_key`),
  CONSTRAINT `admin_permissions_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // api_tokens
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_tokens` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `device_label` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `api_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // battles
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `battles` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `challenger_id` int(11) NOT NULL,
  `opponent_id` int(11) DEFAULT NULL,
  `quiz_id` int(11) NOT NULL,
  `status` enum('pending','negotiating','declined','p1_turn','p2_turn','completed','open') DEFAULT 'pending',
  `match_type` enum('friendly','ranked') DEFAULT 'friendly',
  `xp_wager` int(11) DEFAULT 0,
  `challenger_score` int(11) DEFAULT NULL,
  `challenger_time_seconds` int(11) DEFAULT NULL,
  `opponent_score` int(11) DEFAULT NULL,
  `opponent_time_seconds` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `custom_timer_seconds` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `decline_reason` varchar(255) DEFAULT NULL,
  `proposed_changes` text DEFAULT NULL,
  `invite_code` varchar(20) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `max_participants` int(11) DEFAULT 1,
  `accepted_count` int(11) DEFAULT 0,
  `parent_battle_id` int(11) DEFAULT NULL,
  `escrow_mode` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `challenger_id` (`challenger_id`),
  KEY `opponent_id` (`opponent_id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `battles_ibfk_1` FOREIGN KEY (`challenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `battles_ibfk_2` FOREIGN KEY (`opponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `battles_ibfk_3` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // cgpa_entries
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `cgpa_entries` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_code` varchar(32) NOT NULL,
  `course_unit` int(11) NOT NULL,
  `grade_letter` varchar(2) NOT NULL,
  `semester` varchar(32) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // challenge_participants
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `challenge_participants` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `notified` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_join` (`quiz_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // contact_messages
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `contact_messages` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) DEFAULT '',
  `message` text NOT NULL,
  `admin_reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // contact_replies
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `contact_replies` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `from_user` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // conversations
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `conversations` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_one_id` int(11) NOT NULL,
  `user_two_id` int(11) NOT NULL,
  `last_message_id` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `deleted_by_user_one` tinyint(1) DEFAULT 0,
  `deleted_by_user_two` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`user_one_id`,`user_two_id`),
  KEY `idx_user_one` (`user_one_id`),
  KEY `idx_user_two` (`user_two_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // device_push_tokens
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `device_push_tokens` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` varchar(16) DEFAULT NULL,
  `rich` tinyint(1) DEFAULT 0,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_push_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // discussion_comments
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `discussion_comments` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `discussion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `reply_to_user_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `upvotes` int(11) NOT NULL DEFAULT 0,
  `downvotes` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `discussion_id` (`discussion_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  KEY `reply_to_user_id` (`reply_to_user_id`),
  CONSTRAINT `discussion_comments_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discussion_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discussion_comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `discussion_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discussion_comments_ibfk_4` FOREIGN KEY (`reply_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=928 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // discussion_reports
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `discussion_reports` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` enum('discussion','comment') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `discussion_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // discussion_votes
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `discussion_votes` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` enum('discussion','comment') NOT NULL,
  `target_id` int(11) NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_target` (`user_id`,`target_type`,`target_id`),
  CONSTRAINT `discussion_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1997 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // discussions
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `discussions` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `subject` varchar(120) DEFAULT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  `upvotes` int(11) NOT NULL DEFAULT 0,
  `downvotes` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `discussions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // material_categories
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `material_categories` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // material_payments
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `material_payments` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_ref` varchar(100) NOT NULL,
  `status` enum('open','successful','failed') NOT NULL DEFAULT 'open',
  `gateway_status` varchar(40) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_ref` (`payment_ref`),
  KEY `material_id` (`material_id`),
  KEY `idx_user_material` (`user_id`,`material_id`,`status`),
  KEY `idx_status_created` (`status`,`created_at`),
  CONSTRAINT `material_payments_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // material_progress
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `material_progress` (
`user_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `page` int(11) NOT NULL DEFAULT 1,
  `pages` int(11) NOT NULL DEFAULT 1,
  `percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`material_id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // materials
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `materials` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `uploader_id` int(11) NOT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `level` varchar(20) NOT NULL DEFAULT 'Secondary',
  `secondary_board` enum('General','WAEC','NECO','JAMB','GCE') NOT NULL DEFAULT 'General',
  `university_id` int(11) DEFAULT NULL,
  `is_university_general` tinyint(1) NOT NULL DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `price` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `material_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // mentor_usage
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `mentor_usage` (
`user_id` int(11) NOT NULL,
  `day` date NOT NULL,
  `calls` int(11) NOT NULL DEFAULT 0,
  `neurons` decimal(10,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`user_id`,`day`),
  KEY `day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // message_hidden_for_user
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `message_hidden_for_user` (
`message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // message_reads
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `message_reads` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_message_id` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`conversation_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31666 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // messages
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `messages` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `deleted_for_sender` tinyint(1) DEFAULT 0,
  `deleted_for_everyone` tinyint(1) DEFAULT 0,
  `deleted_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv_created` (`conversation_id`,`created_at`),
  KEY `idx_sender` (`sender_id`)
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // notifications
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `notifications` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1046 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // offline_attempts
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `offline_attempts` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `client_ref` char(36) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `synced_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_ref` (`client_ref`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `offline_attempts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // post_images
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `post_images` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // posts
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `posts` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `level` varchar(20) NOT NULL DEFAULT 'Secondary',
  `university_id` int(11) DEFAULT NULL,
  `is_university_general` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // push_subscriptions
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(500) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1567 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_access_codes
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_access_codes` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `quiz_id` (`quiz_id`),
  KEY `used_by_user_id` (`used_by_user_id`),
  CONSTRAINT `quiz_access_codes_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_access_codes_ibfk_2` FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_answers
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_answers` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `text_answer` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6826 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_attempts
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `duration_used` int(11) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT current_timestamp(),
  `phase` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=591 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_materials
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_materials` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quiz_material` (`quiz_id`,`material_id`),
  KEY `material_id` (`material_id`),
  CONSTRAINT `quiz_materials_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // quiz_options
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_options` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `quiz_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11889 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_payments
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_payments` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','successful','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_ref` (`payment_ref`),
  KEY `user_id` (`user_id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `quiz_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_payments_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quiz_questions
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quiz_questions` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `explanation` text DEFAULT NULL,
  `question_type` varchar(20) NOT NULL DEFAULT 'objective',
  `correct_answer` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // quizzes
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `quizzes` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subject` varchar(120) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('secondary','university','both') DEFAULT NULL,
  `is_prize` tinyint(1) NOT NULL DEFAULT 0,
  `duration_seconds` int(11) NOT NULL DEFAULT 900,
  `slug` varchar(160) DEFAULT NULL,
  `mode` enum('normal','challenge') NOT NULL DEFAULT 'normal',
  `event_start` datetime DEFAULT NULL,
  `event_end` datetime DEFAULT NULL,
  `prize_type` enum('money','airtime','data','text') DEFAULT NULL,
  `prize_amount` decimal(12,2) DEFAULT NULL,
  `prize_text` varchar(255) DEFAULT NULL,
  `total_phases` int(11) NOT NULL DEFAULT 1,
  `current_phase` int(11) NOT NULL DEFAULT 1,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `entry_fee` decimal(12,2) DEFAULT NULL,
  `access_code` varchar(50) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `rewards_notified` tinyint(1) DEFAULT 0,
  `required_level` int(11) NOT NULL DEFAULT 0,
  `exam_types` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // rate_limits
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `rate_limits` (
`rate_key` varchar(191) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`rate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // reward_claims
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `reward_claims` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL DEFAULT '',
  `bank_name` varchar(255) NOT NULL DEFAULT '',
  `account_number` varchar(50) NOT NULL DEFAULT '',
  `account_name` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('pending','processing','sent','paid','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_claim` (`quiz_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // site_settings
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_settings` (
`setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // timetable_entries
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `timetable_entries` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // tutorial_course_map
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `tutorial_course_map` (
`tutorial_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  PRIMARY KEY (`tutorial_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `tutorial_course_map_ibfk_1` FOREIGN KEY (`tutorial_id`) REFERENCES `tutorials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tutorial_course_map_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `tutorial_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // tutorial_courses
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `tutorial_courses` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // tutorials
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `tutorials` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `youtube_id` varchar(32) NOT NULL,
  `youtube_url` varchar(255) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `level` varchar(20) NOT NULL DEFAULT 'University',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // typing_status
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `typing_status` (
`user_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `last_typing_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`partner_id`),
  KEY `idx_partner_typing` (`partner_id`,`last_typing_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // universities
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `universities` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `acronym` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `acronym` (`acronym`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL,

        // user_badges
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `user_badges` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge_unique` (`user_id`,`badge_name`)
) ENGINE=InnoDB AUTO_INCREMENT=407 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // user_battle_stats
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `user_battle_stats` (
`user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT 1000,
  `wins` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `draws` int(11) DEFAULT 0,
  `current_streak` int(11) DEFAULT 0,
  `highest_streak` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_battle_stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // user_blocks
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `user_blocks` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`blocker_id`,`blocked_id`),
  KEY `idx_blocker` (`blocker_id`),
  KEY `idx_blocked` (`blocked_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,

        // user_meta
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `user_meta` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `meta_key` varchar(100) NOT NULL,
  `meta_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_meta_key` (`user_id`,`meta_key`),
  CONSTRAINT `user_meta_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    ];

    return $statements;
}

/**
 * Make sure every table this site needs exists, once per schema version.
 *
 * @param PDO $pdo
 * @return array{ok:bool, created:int, version:string, error:?string}
 */
function msb_ensure_schema_tables(PDO $pdo): array
{
    // The marker lives in a folder the server can write to. The cache folder is
    // the natural home; the logs folder is the fallback, because on shared
    // hosting the cache folder is sometimes not writable.
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $dir = '';
    foreach ([$root . '/cache', $root . '/logs'] as $candidate) {
        if (!is_dir($candidate)) @mkdir($candidate, 0775, true);
        if (is_dir($candidate) && is_writable($candidate)) { $dir = $candidate; break; }
    }
    if ($dir === '') {
        return ['ok' => false, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => 'no writable folder for the schema marker'];
    }
    $marker = $dir . '/schema-version.txt';

    if (is_readable($marker) && trim((string)@file_get_contents($marker)) === MSB_SCHEMA_VERSION) {
        return ['ok' => true, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => null];
    }

    // A server whose database user cannot create tables would otherwise run
    // every statement again on every request. After a failure the work waits
    // ten minutes before it is tried again.
    $failed = $dir . '/schema-failed.txt';
    if (is_readable($failed) && (time() - (int)@file_get_contents($failed)) < 600) {
        return ['ok' => false, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => 'schema check failed recently; waiting before trying again'];
    }

    $created = 0;
    $error = null;
    foreach (msb_schema_statements() as $sql) {
        try {
            $pdo->exec($sql);
            $created++;
        } catch (Throwable $e) {
            // One table that the server refuses is reported and the rest are
            // still attempted: a partial schema is better than none, and the
            // message says which one is missing.
            $error = $e->getMessage();
        }
    }

    if ($error === null) {
        @file_put_contents($marker, MSB_SCHEMA_VERSION);
        @unlink($failed);
    } else {
        @file_put_contents($failed, (string)time());
    }

    return ['ok' => $error === null, 'created' => $created, 'version' => MSB_SCHEMA_VERSION, 'error' => $error];
}
