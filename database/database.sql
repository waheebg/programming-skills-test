-- ============================================================
-- Programming Skills Test
-- Database Schema - Hybrid Exam System
-- MySQL / MariaDB
-- Version: 1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS programming_skills_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE programming_skills_test;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempt_questions;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS exam_question_rules;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS programming_languages;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. Users
-- ============================================================

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Roles
-- ============================================================

CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Permissions
-- ============================================================

CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. User Roles
-- ============================================================

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (user_id, role_id),

    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Role Permissions
-- ============================================================

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (role_id, permission_id),

    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id)
        REFERENCES permissions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. Programming Languages
-- ============================================================

CREATE TABLE programming_languages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_programming_languages_name (name),
    UNIQUE KEY uq_programming_languages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. Categories
-- ============================================================

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',

    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent_id (parent_id),
    KEY idx_categories_created_by (created_by),

    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id)
        REFERENCES categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_categories_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. Questions
-- ============================================================

CREATE TABLE questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    programming_language_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single_choice', 'true_false') NOT NULL DEFAULT 'single_choice',
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    default_mark DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    explanation TEXT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_questions_language (programming_language_id),
    KEY idx_questions_category (category_id),
    KEY idx_questions_created_by (created_by),
    KEY idx_questions_difficulty (difficulty),
    KEY idx_questions_status (status),

    CONSTRAINT fk_questions_language
        FOREIGN KEY (programming_language_id)
        REFERENCES programming_languages(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_questions_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_questions_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_questions_default_mark
        CHECK (default_mark >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. Question Options
-- ============================================================

CREATE TABLE question_options (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id BIGINT UNSIGNED NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    UNIQUE KEY uq_question_options_order (question_id, sort_order),
    KEY idx_question_options_question (question_id),

    CONSTRAINT fk_question_options_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. Exams
-- ============================================================

CREATE TABLE exams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    programming_language_id INT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    selection_mode ENUM('manual', 'random', 'hybrid') NOT NULL DEFAULT 'manual',
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    pass_percentage DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    max_attempts SMALLINT UNSIGNED NULL,
    randomize_questions BOOLEAN NOT NULL DEFAULT TRUE,
    randomize_options BOOLEAN NOT NULL DEFAULT TRUE,
    show_result_immediately BOOLEAN NOT NULL DEFAULT TRUE,
    show_correct_answers BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_exams_language (programming_language_id),
    KEY idx_exams_created_by (created_by),
    KEY idx_exams_status (status),

    CONSTRAINT fk_exams_language
        FOREIGN KEY (programming_language_id)
        REFERENCES programming_languages(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_exams_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_exams_duration
        CHECK (duration_minutes > 0),

    CONSTRAINT chk_exams_pass_percentage
        CHECK (pass_percentage >= 0 AND pass_percentage <= 100),

    CONSTRAINT chk_exams_dates
        CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. Exam Questions - Manual Selection
-- ============================================================

CREATE TABLE exam_questions (
    exam_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    mark DECIMAL(8,2) NULL,

    PRIMARY KEY (exam_id, question_id),
    UNIQUE KEY uq_exam_questions_order (exam_id, sort_order),
    KEY idx_exam_questions_question (question_id),

    CONSTRAINT fk_exam_questions_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_questions_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_exam_questions_mark
        CHECK (mark IS NULL OR mark >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. Exam Question Rules - Random Selection
-- ============================================================

CREATE TABLE exam_question_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id BIGINT UNSIGNED NOT NULL,
    programming_language_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    difficulty ENUM('easy', 'medium', 'hard') NULL,
    question_count INT UNSIGNED NOT NULL,
    mark DECIMAL(8,2) NULL,
    rule_order INT UNSIGNED NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_question_rules_order (exam_id, rule_order),
    KEY idx_exam_question_rules_exam (exam_id),
    KEY idx_exam_question_rules_language (programming_language_id),
    KEY idx_exam_question_rules_category (category_id),

    CONSTRAINT fk_exam_question_rules_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_question_rules_language
        FOREIGN KEY (programming_language_id)
        REFERENCES programming_languages(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_question_rules_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_exam_question_rules_count
        CHECK (question_count > 0),

    CONSTRAINT chk_exam_question_rules_mark
        CHECK (mark IS NULL OR mark >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. Attempts
-- ============================================================

CREATE TABLE attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    status ENUM('in_progress', 'submitted', 'expired', 'cancelled') NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    score DECIMAL(10,2) NULL,
    percentage DECIMAL(5,2) NULL,
    passed BOOLEAN NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_attempts_number (exam_id, user_id, attempt_number),
    KEY idx_attempts_user (user_id),
    KEY idx_attempts_exam (exam_id),
    KEY idx_attempts_status (status),

    CONSTRAINT fk_attempts_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_attempts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_attempts_number
        CHECK (attempt_number > 0),

    CONSTRAINT chk_attempts_percentage
        CHECK (percentage IS NULL OR (percentage >= 0 AND percentage <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. Attempt Questions - Snapshot
-- ============================================================

CREATE TABLE attempt_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    mark DECIMAL(8,2) NOT NULL,
    question_text_snapshot TEXT NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_attempt_questions_question (attempt_id, question_id),
    UNIQUE KEY uq_attempt_questions_order (attempt_id, sort_order),
    KEY idx_attempt_questions_question (question_id),

    CONSTRAINT fk_attempt_questions_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_attempt_questions_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_attempt_questions_mark
        CHECK (mark >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. Attempt Answers
-- ============================================================

CREATE TABLE attempt_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_question_id BIGINT UNSIGNED NOT NULL,
    selected_option_id BIGINT UNSIGNED NULL,
    answer_text TEXT NULL,
    is_correct BOOLEAN NULL,
    awarded_mark DECIMAL(8,2) NULL,
    answered_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_attempt_answers_question (attempt_question_id),
    KEY idx_attempt_answers_option (selected_option_id),

    CONSTRAINT fk_attempt_answers_attempt_question
        FOREIGN KEY (attempt_question_id)
        REFERENCES attempt_questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_attempt_answers_selected_option
        FOREIGN KEY (selected_option_id)
        REFERENCES question_options(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_attempt_answers_mark
        CHECK (awarded_mark IS NULL OR awarded_mark >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. Results
-- ============================================================

CREATE TABLE results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    total_questions INT UNSIGNED NOT NULL,
    answered_questions INT UNSIGNED NOT NULL,
    correct_answers INT UNSIGNED NOT NULL,
    wrong_answers INT UNSIGNED NOT NULL,
    total_marks DECIMAL(10,2) NOT NULL,
    score DECIMAL(10,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    passed BOOLEAN NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_results_attempt (attempt_id),

    CONSTRAINT fk_results_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_results_percentage
        CHECK (percentage >= 0 AND percentage <= 100),

    CONSTRAINT chk_results_counts
        CHECK (
            answered_questions <= total_questions
            AND correct_answers <= answered_questions
            AND wrong_answers <= answered_questions
        ),

    CONSTRAINT chk_results_marks
        CHECK (total_marks >= 0 AND score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. Audit Logs
-- ============================================================

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_logs_user (user_id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_entity (entity_type, entity_id),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Initial Roles
-- ============================================================

INSERT INTO roles (name, description) VALUES
('admin', 'System administrator'),
('teacher', 'Question and exam author'),
('student', 'Exam participant');

-- ============================================================
-- Initial Permissions
-- ============================================================

INSERT INTO permissions (name, description) VALUES
('users.view', 'View users'),
('users.create', 'Create users'),
('users.update', 'Update users'),
('users.delete', 'Delete users'),
('roles.manage', 'Manage roles and permissions'),
('languages.view', 'View programming languages'),
('languages.manage', 'Manage programming languages'),
('categories.view', 'View categories'),
('categories.manage', 'Manage categories'),
('questions.view', 'View questions'),
('questions.create', 'Create questions'),
('questions.update', 'Update questions'),
('questions.delete', 'Archive questions'),
('exams.view', 'View exams'),
('exams.create', 'Create exams'),
('exams.update', 'Update exams'),
('exams.delete', 'Archive exams'),
('exams.take', 'Take exams'),
('results.view', 'View results'),
('audit.view', 'View audit logs');

-- ============================================================
-- Basic Role Permissions
-- ============================================================

-- Admin receives all permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT
    r.id,
    p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin';

-- Teacher permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT
    r.id,
    p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'teacher'
  AND p.name IN (
      'languages.view',
      'categories.view',
      'categories.manage',
      'questions.view',
      'questions.create',
      'questions.update',
      'questions.delete',
      'exams.view',
      'exams.create',
      'exams.update',
      'exams.delete',
      'results.view'
  );

-- Student permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT
    r.id,
    p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'student'
  AND p.name IN (
      'languages.view',
      'categories.view',
      'exams.view',
      'exams.take',
      'results.view'
  );

-- ============================================================
-- Basic Programming Languages
-- These are initial reference records, not test questions.
-- ============================================================

INSERT INTO programming_languages (name, slug, description) VALUES
('PHP', 'php', 'PHP programming language'),
('JavaScript', 'javascript', 'JavaScript programming language'),
('Python', 'python', 'Python programming language'),
('Java', 'java', 'Java programming language'),
('C++', 'cpp', 'C++ programming language'),
('C#', 'csharp', 'C# programming language');

-- ============================================================
-- End of schema
-- ============================================================
