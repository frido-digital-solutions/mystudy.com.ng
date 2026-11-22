<?php
/**
 * Tables the code assumes are already there.
 *
 * Most of this codebase creates its own tables when it needs them - the
 * models do it, the API does it, the admin pages do it. Three tables were
 * left out of that habit, and they happen to be the three a shared hosting
 * account does not have on a database that was set up by hand:
 *
 *   admin_permissions   which admin may open which page
 *   admin_activity_log  what an admin did, and when
 *   user_badges         the badges a student has earned
 *
 * A missing table is not a crash the user can act on - it is a permission
 * check that throws, or a badge that silently never appears. So the tables
 * are created here, once, by whoever first needs them, and never again:
 * CREATE TABLE IF NOT EXISTS is free when the table is already there.
 */

if (!function_exists('msb_ensure_admin_tables')) {

    /**
     * The two tables the admin pages read and write.
     */
    function msb_ensure_admin_tables(PDO $pdo): void
    {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `admin_permissions` (
                    `admin_user_id` INT(11) NOT NULL,
                    `page_key` VARCHAR(50) NOT NULL,
                    PRIMARY KEY (`admin_user_id`, `page_key`),
                    INDEX `idx_admin_perm_user` (`admin_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `admin_activity_log` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `admin_user_id` INT(11) NOT NULL,
                    `action` VARCHAR(255) NOT NULL,
                    `details` TEXT DEFAULT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_activity_admin` (`admin_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            // A table that cannot be created must not take the page with it.
            log_error('Could not ensure the admin tables', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('msb_ensure_badge_tables')) {

    /**
     * The table a student's earned badges live in.
     */
    function msb_ensure_badge_tables(PDO $pdo): void
    {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_badges` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) NOT NULL,
                    `badge_name` VARCHAR(100) NOT NULL,
                    `earned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_badge_unique` (`user_id`, `badge_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            log_error('Could not ensure the badge table', ['error' => $e->getMessage()]);
        }
    }
}
