<?php
/**
 * The Study library: real past questions, keyed by exam, subject and year.
 *
 * The old quiz tables (quizzes, quiz_questions, quiz_options) are built for
 * timed, scored, prize quizzes - one quiz is one sitting. A study library is
 * different: the same question is part of a paper that a student reads at
 * their own pace, next to the other papers for that subject and year.
 *
 * So the library keeps its own two tables:
 *
 *   study_papers    one real paper. JAMB + English + 2024 + objective.
 *   study_questions the questions inside that paper, with their options and
 *                   the correct option.
 *
 * Both are created here, once, by whoever first needs them, and never again:
 * CREATE TABLE IF NOT EXISTS is free when the table is already there.
 */

if (!function_exists('msb_ensure_study_tables')) {

    /**
     * Create the Study tables if they are not there yet.
     *
     * A failure here must not take a page down with it - the page that calls
     * this catches the error and shows an empty library instead.
     */
    function msb_ensure_study_tables(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `study_papers` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `exam` VARCHAR(10) NOT NULL,
                `subject_slug` VARCHAR(120) NOT NULL,
                `subject_name` VARCHAR(160) NOT NULL,
                `year` SMALLINT(6) NOT NULL,
                `paper_code` VARCHAR(40) NOT NULL DEFAULT 'objective',
                `title` VARCHAR(255) NOT NULL,
                `source` VARCHAR(60) NOT NULL,
                `source_url` VARCHAR(500) DEFAULT NULL,
                `question_count` INT(11) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_paper` (`exam`, `subject_slug`, `year`, `paper_code`, `source`),
                INDEX `idx_paper_browse` (`exam`, `subject_slug`, `year`),
                INDEX `idx_paper_exam` (`exam`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `study_questions` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `paper_id` INT(11) NOT NULL,
                `display_number` INT(11) NOT NULL,
                `question` TEXT NOT NULL,
                `options_json` TEXT NOT NULL,
                `correct_option` VARCHAR(8) NOT NULL DEFAULT '',
                `explanation` TEXT DEFAULT NULL,
                `topic` VARCHAR(190) DEFAULT NULL,
                `source_question_id` VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_question` (`paper_id`, `display_number`),
                INDEX `idx_question_paper` (`paper_id`),
                CONSTRAINT `study_questions_paper_fk`
                    FOREIGN KEY (`paper_id`) REFERENCES `study_papers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
