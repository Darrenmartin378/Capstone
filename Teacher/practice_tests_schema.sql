-- Practice Tests Database Schema
-- This file contains the SQL statements to create the necessary tables for the Practice Test feature

-- Create practice_tests table
CREATE TABLE IF NOT EXISTS practice_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 30,
    skill_focus VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_created_at (created_at)
);

-- Create practice_test_questions table (junction table)
CREATE TABLE IF NOT EXISTS practice_test_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    practice_test_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
    UNIQUE KEY unique_practice_question (practice_test_id, question_id),
    INDEX idx_practice_test_id (practice_test_id),
    INDEX idx_question_id (question_id),
    INDEX idx_question_order (question_order)
);

-- Create practice_test_attempts table (to track student attempts)
CREATE TABLE IF NOT EXISTS practice_test_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    practice_test_id INT NOT NULL,
    student_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    score DECIMAL(5,2) NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL DEFAULT 0,
    time_spent_minutes INT NULL,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_practice_test_id (practice_test_id),
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
);

-- Create practice_test_responses table (to store individual question responses)
CREATE TABLE IF NOT EXISTS practice_test_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    time_spent_seconds INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES practice_test_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt_question (attempt_id, question_id),
    INDEX idx_attempt_id (attempt_id),
    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct)
);

-- Insert some sample data (optional - for testing)
-- Note: This assumes you have existing teachers and questions in your database

-- Sample practice test (uncomment and modify as needed)
/*
INSERT INTO practice_tests (teacher_id, title, description, duration_minutes, skill_focus) 
VALUES (1, 'Reading Comprehension Practice', 'Practice test focusing on reading comprehension skills', 30, 'Reading Comprehension');

-- Add questions to the practice test (uncomment and modify as needed)
INSERT INTO practice_test_questions (practice_test_id, question_id, question_order) 
VALUES (1, 1, 1), (1, 2, 2), (1, 3, 3);
*/
