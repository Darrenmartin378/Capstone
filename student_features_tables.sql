-- Additional tables for student features
-- Run this SQL to add the missing tables for student functionality

-- Table for test results (to track student test performance)
CREATE TABLE IF NOT EXISTS `test_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `date_taken` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('completed','in_progress','abandoned') NOT NULL DEFAULT 'completed',
  `time_spent_minutes` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `test_id` (`test_id`),
  KEY `date_taken` (`date_taken`),
  CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for practice materials
CREATE TABLE IF NOT EXISTS `practice_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('quiz','exercise','reading') NOT NULL DEFAULT 'quiz',
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `content` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `practice_materials_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for practice progress tracking
CREATE TABLE IF NOT EXISTS `practice_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `score` decimal(5,2) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempted` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_material` (`student_id`,`material_id`),
  KEY `student_id` (`student_id`),
  KEY `material_id` (`material_id`),
  CONSTRAINT `practice_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `practice_progress_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `practice_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for reading lists
CREATE TABLE IF NOT EXISTS `reading_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `grade_level` int(11) NOT NULL DEFAULT 6,
  `books` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`books`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `reading_lists_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for reading progress tracking
CREATE TABLE IF NOT EXISTS `reading_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `list_id` int(11) NOT NULL,
  `books_read` int(11) NOT NULL DEFAULT 0,
  `total_books` int(11) NOT NULL DEFAULT 0,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_list` (`student_id`,`list_id`),
  KEY `student_id` (`student_id`),
  KEY `list_id` (`list_id`),
  CONSTRAINT `reading_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reading_progress_ibfk_2` FOREIGN KEY (`list_id`) REFERENCES `reading_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for performance alerts
CREATE TABLE IF NOT EXISTS `performance_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `alert_type` enum('performance','attendance','behavior','achievement') NOT NULL DEFAULT 'performance',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `is_read` (`is_read`),
  KEY `severity` (`severity`),
  CONSTRAINT `performance_alerts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert some sample data for testing
INSERT INTO `practice_materials` (`teacher_id`, `title`, `description`, `type`, `difficulty`, `content`) VALUES
(1, 'Math Practice Quiz', 'Basic arithmetic operations for grade 6 students', 'quiz', 'easy', 'Practice addition, subtraction, multiplication, and division problems.'),
(1, 'Reading Comprehension Exercise', 'Improve reading skills with comprehension questions', 'exercise', 'medium', 'Read passages and answer questions to improve comprehension.'),
(1, 'Science Vocabulary Quiz', 'Test your knowledge of science terms', 'quiz', 'medium', 'Match science terms with their definitions.'),
(1, 'Creative Writing Exercise', 'Express your creativity through writing', 'exercise', 'hard', 'Write short stories and essays on given topics.');

INSERT INTO `reading_lists` (`teacher_id`, `title`, `description`, `category`, `grade_level`, `books`) VALUES
(1, 'Adventure Stories', 'Exciting adventure books for young readers', 'Fiction', 6, '["The Magic Tree House", "Percy Jackson", "Harry Potter", "The Chronicles of Narnia"]'),
(1, 'Science & Nature', 'Educational books about science and nature', 'Non-fiction', 6, '["National Geographic Kids", "The Magic School Bus", "Science Encyclopedia", "Animal Encyclopedia"]'),
(1, 'Biography Collection', 'Inspiring stories of famous people', 'Biography', 6, '["Who Was Series", "I Am Malala", "The Boy Who Harnessed the Wind", "Hidden Figures"]');

INSERT INTO `performance_alerts` (`student_id`, `title`, `message`, `severity`, `alert_type`) VALUES
(1, 'Great Job!', 'You scored 95% on your last math test! Keep up the excellent work!', 'low', 'achievement'),
(1, 'Reading Progress', 'You have completed 3 out of 4 books in your reading list. Almost there!', 'low', 'achievement'),
(1, 'Practice Reminder', 'Consider practicing more math problems to improve your skills.', 'medium', 'performance');
