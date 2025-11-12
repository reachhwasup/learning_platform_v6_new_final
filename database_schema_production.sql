-- Learning Platform v6.0 - Production Database Schema
-- Date: November 12, 2025
-- Target: MySQL 5.7+ / MariaDB 10.3+
-- Source: Exported from working XAMPP local database

-- This schema matches your actual working database structure

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================
-- CREATE DATABASE
-- ============================================
CREATE DATABASE IF NOT EXISTS `learning_platform` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `learning_platform`;

-- ============================================
-- 1. DEPARTMENTS TABLE
-- ============================================
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default departments
INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'Information Technology'),
(2, 'Human Resources'),
(3, 'Finance'),
(4, 'Operations'),
(5, 'Security'),
(6, 'Marketing'),
(7, 'Sales'),
(8, 'Administration');

-- ============================================
-- 2. USERS TABLE
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `fullname` varchar(201) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `staff_id` varchar(50) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `password_reset_required` tinyint(1) NOT NULL DEFAULT 0,
  `password_last_changed` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT 'default_avatar.png',
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `username` (`username`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default users
-- Admin user 1: admin / admin123 (CHANGE IMMEDIATELY AFTER FIRST LOGIN!)
-- Admin user 2: admin.reach / password
-- Regular user: user.reach / password
INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `password`, `staff_id`, `position`, `department_id`, `role`, `status`) VALUES
(1, 'System', 'Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN001', 'System Administrator', 1, 'admin', 'active'),
(2, 'Reach', 'Admin', 'admin.reach', '$2y$10$p/Jj4tXigLNtc.9BGQU3iux3nOmjFbth3KfIqLbeRMjiint2XezyK', 'ADMIN002', 'Administrator', 1, 'admin', 'active'),
(3, 'Reach', 'User', 'user.reach', '$2y$10$p/Jj4tXigLNtc.9BGQU3iux3nOmjFbth3KfIqLbeRMjiint2XezyK', 'USER001', 'Staff Member', 2, 'user', 'active');

-- ============================================
-- 3. PASSWORD HISTORY TABLE
-- ============================================
DROP TABLE IF EXISTS `password_history`;
CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. MODULES TABLE
-- ============================================
DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `module_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_order` (`module_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. VIDEOS TABLE
-- ============================================
DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `video_order` int(11) DEFAULT 1,
  `upload_by` int(11) NOT NULL,
  `upload_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edit_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `module_id` (`module_id`),
  KEY `upload_by` (`upload_by`),
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `videos_ibfk_2` FOREIGN KEY (`upload_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. USER PROGRESS TABLE
-- ============================================
DROP TABLE IF EXISTS `user_progress`;
CREATE TABLE `user_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_module_unique` (`user_id`,`module_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `user_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. QUESTIONS TABLE
-- ============================================
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single','multiple') NOT NULL,
  `is_final_exam_question` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. QUESTION OPTIONS TABLE
-- ============================================
DROP TABLE IF EXISTS `question_options`;
CREATE TABLE `question_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. USER ANSWERS TABLE
-- ============================================
DROP TABLE IF EXISTS `user_answers`;
CREATE TABLE `user_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `assessment_id` int(11) DEFAULT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `question_id` (`question_id`),
  KEY `selected_option_id` (`selected_option_id`),
  CONSTRAINT `user_answers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `question_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. FINAL ASSESSMENTS TABLE
-- ============================================
DROP TABLE IF EXISTS `final_assessments`;
CREATE TABLE `final_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `status` enum('passed','failed') NOT NULL,
  `quiz_started_at` datetime DEFAULT NULL,
  `quiz_ended_at` datetime DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `final_assessments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CREATE VIEWS FOR REPORTING
-- ============================================

-- View: User Progress Summary
CREATE OR REPLACE VIEW `v_user_progress_summary` AS
SELECT 
    u.id AS user_id,
    u.username,
    u.fullname,
    u.staff_id,
    d.name AS department,
    COUNT(DISTINCT up.module_id) AS modules_completed,
    MAX(up.completed_at) AS last_activity
FROM 
    users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN user_progress up ON u.id = up.user_id
WHERE 
    u.role = 'user' AND u.status = 'active'
GROUP BY 
    u.id, u.username, u.fullname, u.staff_id, d.name;

-- View: Assessment Summary
CREATE OR REPLACE VIEW `v_assessment_summary` AS
SELECT 
    u.id AS user_id,
    u.username,
    u.fullname,
    u.staff_id,
    d.name AS department,
    COUNT(fa.id) AS total_attempts,
    MAX(fa.score) AS highest_score,
    MIN(fa.score) AS lowest_score,
    ROUND(AVG(fa.score), 2) AS avg_score,
    SUM(CASE WHEN fa.status = 'passed' THEN 1 ELSE 0 END) AS passed_attempts,
    SUM(CASE WHEN fa.status = 'failed' THEN 1 ELSE 0 END) AS failed_attempts,
    MAX(CASE WHEN fa.status = 'passed' THEN fa.completed_at END) AS first_pass_date,
    MAX(fa.completed_at) AS last_attempt_date
FROM 
    users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN final_assessments fa ON u.id = fa.user_id
WHERE 
    u.role = 'user' AND u.status = 'active'
GROUP BY 
    u.id, u.username, u.fullname, u.staff_id, d.name;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Add indexes for common queries
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_users_department ON users(department_id);
CREATE INDEX idx_modules_order ON modules(module_order);
CREATE INDEX idx_videos_module ON videos(module_id);
CREATE INDEX idx_questions_module ON questions(module_id);
CREATE INDEX idx_questions_final ON questions(is_final_exam_question);
CREATE INDEX idx_user_answers_user ON user_answers(user_id);
CREATE INDEX idx_user_answers_assessment ON user_answers(assessment_id);
CREATE INDEX idx_final_assessments_user_status ON final_assessments(user_id, status);
CREATE INDEX idx_password_history_user ON password_history(user_id);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Show all tables
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME 
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE()
ORDER BY 
    TABLE_NAME;

-- Show database size
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM 
    information_schema.tables 
WHERE 
    table_schema = DATABASE()
GROUP BY 
    table_schema;

-- ============================================
-- DEPLOYMENT NOTES
-- ============================================
-- 
-- 1. Default admin credentials:
--    Username: admin
--    Password: admin123
--    Staff ID: ADMIN001
--    **CHANGE PASSWORD IMMEDIATELY AFTER FIRST LOGIN!**
--
-- 2. Database name on production should be: learning_platform
--    (Your local DB is: security_awareness_db_v6)
--
-- 3. Update db_connect.php on production:
--    define('DB_NAME', 'learning_platform');
--
-- 4. Key differences from local:
--    - Database name changed to 'learning_platform'
--    - Added more default departments
--    - Added performance indexes
--    - Added reporting views
--
-- 5. Features included:
--    - Password history tracking
--    - User account locking after failed attempts
--    - Generated fullname column (first_name + last_name)
--    - Question options with multiple choice support
--    - User answers tracking for detailed reporting
--    - Videos with upload tracking
--    - Module-based quiz questions
--    - Final exam questions (is_final_exam_question = 1)
--
-- 6. Security features:
--    - Foreign key constraints with CASCADE delete
--    - Password reset enforcement
--    - Account lock mechanism
--    - Failed login tracking
--    - Password history to prevent reuse
--
-- 7. File uploads directory structure:
--    /uploads/profile_pictures/  - User profile images
--    /uploads/videos/            - Training video files
--    /uploads/thumbnails/        - Video thumbnail images
--    (Ensure proper permissions: chmod 775)
--
-- 8. Remember to backup database regularly!
--    mysqldump -u backup -p learning_platform | gzip > backup_$(date +%Y%m%d).sql.gz
--
-- ============================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
