-- New Question System Schema with Separate Tables for Question Types
-- This schema separates questions by type for better organization and performance

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (in correct order to avoid foreign key issues)
DROP TABLE IF EXISTS student_responses;
DROP TABLE IF EXISTS essay_questions;
DROP TABLE IF EXISTS matching_questions;
DROP TABLE IF EXISTS mcq_questions;
DROP TABLE IF EXISTS question_sets;
DROP TABLE IF EXISTS sections;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create sections table
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create question_sets table
CREATE TABLE question_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    set_title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create MCQ questions table
CREATE TABLE mcq_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    set_id INT NOT NULL,
    question_text TEXT NOT NULL,
    choice_a VARCHAR(500) NOT NULL,
    choice_b VARCHAR(500) NOT NULL,
    choice_c VARCHAR(500) NOT NULL,
    choice_d VARCHAR(500) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    points INT DEFAULT 1,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (set_id) REFERENCES question_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create matching questions table
CREATE TABLE matching_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    set_id INT NOT NULL,
    question_text TEXT NOT NULL,
    left_items JSON NOT NULL, -- Array of left items
    right_items JSON NOT NULL, -- Array of right items
    correct_pairs JSON NOT NULL, -- Array of correct pair mappings
    points INT DEFAULT 1,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (set_id) REFERENCES question_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create essay questions table
CREATE TABLE essay_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    set_id INT NOT NULL,
    question_text TEXT NOT NULL,
    points INT DEFAULT 1,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (set_id) REFERENCES question_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create student_responses table
CREATE TABLE student_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_set_id INT NOT NULL,
    question_type ENUM('mcq', 'matching', 'essay') NOT NULL,
    question_id INT NOT NULL, -- References the specific question table
    answer TEXT, -- For MCQ: 'A', 'B', 'C', 'D'; For matching: JSON of pairs; For essay: text response
    is_correct BOOLEAN DEFAULT NULL, -- NULL for essay (manual grading), TRUE/FALSE for auto-graded
    score DECIMAL(5,2) DEFAULT NULL, -- Points awarded for this response
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_at TIMESTAMP NULL,
    graded_by INT NULL, -- Teacher ID who graded (for essay questions)
    feedback TEXT, -- Teacher feedback for essay questions
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (question_set_id) REFERENCES question_sets(id) ON DELETE CASCADE,
    INDEX idx_student_question (student_id, question_set_id, question_type, question_id),
    INDEX idx_question_type (question_type, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
CREATE INDEX idx_mcq_set_id ON mcq_questions(set_id);
CREATE INDEX idx_matching_set_id ON matching_questions(set_id);
CREATE INDEX idx_essay_set_id ON essay_questions(set_id);
CREATE INDEX idx_responses_student ON student_responses(student_id);
CREATE INDEX idx_responses_set ON student_responses(question_set_id);

-- Insert sample data
INSERT INTO sections (name, description) VALUES 
('Rizal', 'Section for Rizal class'),
('Bonifacio', 'Section for Bonifacio class'),
('Luna', 'Section for Luna class');

-- Insert sample question set
INSERT INTO question_sets (teacher_id, section_id, set_title, description) VALUES 
(4, 3, 'QuizA', 'Sample quiz for testing');

-- Insert sample MCQ question
INSERT INTO mcq_questions (set_id, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points) VALUES 
(1, 'What is the capital of the Philippines?', 'Manila', 'Cebu', 'Davao', 'Quezon City', 'A', 1);

-- Insert sample matching question
INSERT INTO matching_questions (set_id, question_text, left_items, right_items, correct_pairs, points) VALUES 
(1, 'Match the following items with their correct answers:', 
'["Simile", "Personification", "Metaphor"]',
'["She is as brave as a lion.", "The wind whispered through the trees.", "Time is a thief."]',
'{"0": "She is as brave as a lion.", "1": "The wind whispered through the trees.", "2": "Time is a thief."}',
1);

-- Insert sample essay question
INSERT INTO essay_questions (set_id, question_text, points) VALUES 
(1, 'Explain the importance of education in society.', 5);
