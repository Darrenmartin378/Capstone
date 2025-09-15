-- Database setup for Compre Learn Admin Dashboard
-- Run this SQL in your MySQL database

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS compre_learn;
USE compre_learn;

-- Drop existing tables in reverse order of dependency to ensure a clean setup
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS parents;
DROP TABLE IF EXISTS teacher_sections;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS reading_materials;
DROP TABLE IF EXISTS assessments;
DROP TABLE IF EXISTS assessment_assignments;
DROP TABLE IF EXISTS question_bank;
DROP TABLE IF EXISTS assessment_questions;

-- Sections table
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teachers table (removed section_id - now supports multiple sections)
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teacher-Sections junction table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS teacher_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_section (teacher_id, section_id)
);

-- Students table (with section_id - students belong to one section)
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    student_number VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    section_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
);

-- Parents table
CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    student_id INT,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Reading Materials table
CREATE TABLE IF NOT EXISTS reading_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    theme_settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Assessments table
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    theme_settings JSON,
    related_material_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (related_material_id) REFERENCES reading_materials(id) ON DELETE SET NULL
);

-- Assessment Assignments (which section or student gets the assessment)
CREATE TABLE IF NOT EXISTS assessment_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    section_id INT,
    student_id INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Question Bank (reusable questions)
CREATE TABLE IF NOT EXISTS question_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    question_type ENUM('multiple_choice', 'matching', 'essay') NOT NULL,
    question_text TEXT NOT NULL,
    options JSON,
    answer TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Assessment Questions (questions for a specific assessment)
CREATE TABLE IF NOT EXISTS assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_type ENUM('multiple_choice', 'matching', 'essay') NOT NULL,
    question_text TEXT NOT NULL,
    options JSON,
    answer TEXT,
    question_bank_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (question_bank_id) REFERENCES question_bank(id) ON DELETE SET NULL
);

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    user_type ENUM('teacher', 'student', 'parent') NOT NULL,
    token VARCHAR(255) NOT NULL,
    code VARCHAR(10) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO sections (name) VALUES ('Section A'), ('Section B'), ('Section C');

INSERT INTO teachers (name, username, email, password) VALUES
('John Smith', 'jsmith', 'jsmith@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Sarah Johnson', 'sjohnson', 'sjohnson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Michael Brown', 'mbrown', 'mbrown@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Assign sections to teachers (John handles A & B, Sarah handles B & C, Michael handles A & C)
INSERT INTO teacher_sections (teacher_id, section_id) VALUES
(1, 1), (1, 2),  -- John handles Section A and B
(2, 2), (2, 3),  -- Sarah handles Section B and C
(3, 1), (3, 3);  -- Michael handles Section A and C

INSERT INTO students (name, student_number, email, password, section_id) VALUES
('Emma Wilson', 'S2024001', 'emma@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('David Lee', 'S2024002', 'david@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Lisa Garcia', 'S2024003', 'lisa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

INSERT INTO parents (name, username, email, student_id, password) VALUES
('Robert Wilson', 'rwilson', 'rwilson@email.com', 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Jennifer Lee', 'jlee', 'jlee@email.com', 2, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Carlos Garcia', 'cgarcia', 'cgarcia@email.com', 3, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); 

-- ALTER TABLE statements for existing databases
ALTER TABLE teachers ADD COLUMN email VARCHAR(100) UNIQUE NOT NULL AFTER username;
ALTER TABLE students ADD COLUMN email VARCHAR(100) UNIQUE NOT NULL AFTER student_number;
ALTER TABLE parents ADD COLUMN email VARCHAR(100) UNIQUE NOT NULL AFTER username; 
ALTER TABLE password_resets ADD COLUMN code VARCHAR(10) DEFAULT NULL; 