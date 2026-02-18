CREATE DATABASE IF NOT EXISTS module_quizzer
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE module_quizzer;

-- ──────────────────────────────────────────────
-- Course
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS course (
    course_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    description  TEXT         NOT NULL DEFAULT '',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Module (belongs to Course)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS module (
    module_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    course_id    INT UNSIGNED NOT NULL,
    title        VARCHAR(255) NOT NULL,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_module_course
        FOREIGN KEY (course_id) REFERENCES course (course_id)
        ON DELETE CASCADE,

    INDEX ix_module_course_id (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Lesson (one per Module — unique constraint)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lesson (
    lesson_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    module_id    INT UNSIGNED NOT NULL,
    content_html MEDIUMTEXT   NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lesson_module
        FOREIGN KEY (module_id) REFERENCES module (module_id)
        ON DELETE CASCADE,

    CONSTRAINT uq_lesson_module UNIQUE (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Question (belongs to Module)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS question (
    question_id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    module_id         INT UNSIGNED NOT NULL,
    question_type     ENUM('true_false','multiple_choice','multiple_select','fill_blank','flashcard') NOT NULL,
    prompt            TEXT         NOT NULL,
    sort_order        INT UNSIGNED NOT NULL DEFAULT 0,
    correct_boolean   TINYINT(1) UNSIGNED      DEFAULT NULL,
    flashcard_answer  TEXT                      DEFAULT NULL,
    fill_blank_answers JSON                     DEFAULT NULL,
    is_case_sensitive TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    feedback_correct  TEXT                      DEFAULT NULL,
    feedback_incorrect TEXT                     DEFAULT NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_question_module
        FOREIGN KEY (module_id) REFERENCES module (module_id)
        ON DELETE CASCADE,

    INDEX ix_question_module_id (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Question option (for MCQ / MSQ)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS question_option (
    option_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question_id  INT UNSIGNED NOT NULL,
    option_text  TEXT         NOT NULL,
    is_correct   TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,

    CONSTRAINT fk_option_question
        FOREIGN KEY (question_id) REFERENCES question (question_id)
        ON DELETE CASCADE,

    INDEX ix_option_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- Attempt (quiz score record)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attempt (
    attempt_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    module_id       INT UNSIGNED    NOT NULL,
    score           DECIMAL(5,2)    NOT NULL,
    total_questions INT UNSIGNED    NOT NULL,
    points_earned   DECIMAL(7,2)    NOT NULL,
    attempted_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attempt_module
        FOREIGN KEY (module_id) REFERENCES module (module_id)
        ON DELETE CASCADE,

    INDEX ix_attempt_module_id (module_id),
    INDEX ix_attempt_date (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
