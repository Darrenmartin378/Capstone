-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2025 at 11:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `compre_learn`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `related_material_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_assignments`
--

CREATE TABLE `assessment_assignments` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_type` enum('multiple_choice','matching','essay') NOT NULL,
  `question_text` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `answer` text DEFAULT NULL,
  `question_bank_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_responses`
--

CREATE TABLE `assessment_responses` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `user_type` enum('teacher','student','parent') NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `user_type`, `token`, `expires_at`, `used`, `created_at`, `code`) VALUES
(16, 'martindarren3561@gmail.com', 'student', 'dd48056c5310055a428c8d6dcd7fce5ee22b1f6d71c85484d92368b8cc52866a', '2025-07-18 15:06:55', 0, '2025-07-18 12:06:55', '040364'),
(17, 'martindarren3561@gmail.com', 'student', '0364a3a3808ac258569312653baf6d748c51c10264063fdc24e9065e2c025c8c', '2025-07-18 15:09:17', 0, '2025-07-18 12:09:17', '734575'),
(18, 'martindarren3561@gmail.com', 'student', '7553a42f6b2021fd68beb631236d64602fb1f0136f1a3e5ee50a9676544fb914', '2025-07-18 15:11:15', 0, '2025-07-18 12:11:15', '281463'),
(19, 'martindarren3561@gmail.com', 'student', 'ef32a9ee31a910d8bb024e5260b3b3023e9c41e05d61cc308a1eb78aa67dc028', '2025-07-18 15:15:28', 0, '2025-07-18 12:15:28', '211788');

-- --------------------------------------------------------

--
-- Table structure for table `question_bank`
--

CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `question_type` enum('multiple_choice','matching','essay') NOT NULL,
  `question_text` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_materials`
--

CREATE TABLE `reading_materials` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reading_materials`
--

INSERT INTO `reading_materials` (`id`, `teacher_id`, `title`, `content`, `theme_settings`, `created_at`, `updated_at`) VALUES
(1, 1, 'Boorat', 'sadddddddddddddddddddd', '{\"bg_color\":\"#ffffff\",\"question_font_family\":\"Arial\",\"question_font_size\":\"36\",\"question_font_color\":\"#000000\",\"text_font_family\":\"Arial\",\"text_font_size\":\"16\",\"text_font_color\":\"#000000\"}', '2025-07-25 03:52:01', '2025-07-25 03:52:01');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `student_number`, `email`, `password`, `section_id`, `created_at`, `updated_at`) VALUES
(1, 'Darren B. Martin', '2203561', 'martindarren3561@gmail.com', '$2y$10$Ni6UIsG9Gl4H41YupamXDOF5NG6bWal7dg8pxC/RAT6KMtfVhQPDq', NULL, '2025-07-18 08:30:31', '2025-07-18 08:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `username`, `email`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Uchiha Itachi', 'uchiha11', 'uchiha123@gmail.com', '$2y$10$2dCvGQsjZHSDJxVKBKn86eTVD.Cvs.H8qOIhYWlSRLH3smz2XIJQq', '2025-07-18 08:41:18', '2025-07-18 08:41:18');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_sections`
--

CREATE TABLE `teacher_sections` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warmup_responses`
--

CREATE TABLE `warmup_responses` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warm_ups`
--

CREATE TABLE `warm_ups` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `related_material_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warm_up_questions`
--

CREATE TABLE `warm_up_questions` (
  `id` int(11) NOT NULL,
  `warm_up_id` int(11) NOT NULL,
  `question_type` enum('multiple_choice','matching','essay') NOT NULL,
  `question_text` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `related_material_id` (`related_material_id`);

--
-- Indexes for table `assessment_assignments`
--
ALTER TABLE `assessment_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `question_bank_id` (`question_bank_id`);

--
-- Indexes for table `assessment_responses`
--
ALTER TABLE `assessment_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `reading_materials`
--
ALTER TABLE `reading_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_section` (`teacher_id`,`section_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `warmup_responses`
--
ALTER TABLE `warmup_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `warm_ups`
--
ALTER TABLE `warm_ups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `related_material_id` (`related_material_id`);

--
-- Indexes for table `warm_up_questions`
--
ALTER TABLE `warm_up_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warm_up_id` (`warm_up_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_assignments`
--
ALTER TABLE `assessment_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_responses`
--
ALTER TABLE `assessment_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reading_materials`
--
ALTER TABLE `reading_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warmup_responses`
--
ALTER TABLE `warmup_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warm_ups`
--
ALTER TABLE `warm_ups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warm_up_questions`
--
ALTER TABLE `warm_up_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`related_material_id`) REFERENCES `reading_materials` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assessment_assignments`
--
ALTER TABLE `assessment_assignments`
  ADD CONSTRAINT `assessment_assignments_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_assignments_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_assignments_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_questions_ibfk_2` FOREIGN KEY (`question_bank_id`) REFERENCES `question_bank` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assessment_responses`
--
ALTER TABLE `assessment_responses`
  ADD CONSTRAINT `assessment_responses_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_responses_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD CONSTRAINT `question_bank_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_materials`
--
ALTER TABLE `reading_materials`
  ADD CONSTRAINT `reading_materials_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD CONSTRAINT `teacher_sections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_sections_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warmup_responses`
--
ALTER TABLE `warmup_responses`
  ADD CONSTRAINT `warmup_responses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warmup_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warm_ups`
--
ALTER TABLE `warm_ups`
  ADD CONSTRAINT `warm_ups_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warm_ups_ibfk_2` FOREIGN KEY (`related_material_id`) REFERENCES `reading_materials` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warm_up_questions`
--
ALTER TABLE `warm_up_questions`
  ADD CONSTRAINT `warm_up_questions_ibfk_1` FOREIGN KEY (`warm_up_id`) REFERENCES `warm_ups` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `practice_tests`
--

CREATE TABLE `practice_tests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `skill_focus` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_questions`
--

CREATE TABLE `practice_test_questions` (
  `id` int(11) NOT NULL,
  `practice_test_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `question_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_attempts`
--

CREATE TABLE `practice_test_attempts` (
  `id` int(11) NOT NULL,
  `practice_test_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL DEFAULT 0,
  `time_spent_minutes` int(11) DEFAULT NULL,
  `status` enum('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_responses`
--

CREATE TABLE `practice_test_responses` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `time_spent_seconds` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_practice_question` (`practice_test_id`,`question_id`),
  ADD KEY `practice_test_id` (`practice_test_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_question_order` (`question_order`);

--
-- Indexes for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `practice_test_id` (`practice_test_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Indexes for table `practice_test_responses`
--
ALTER TABLE `practice_test_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_is_correct` (`is_correct`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `practice_tests`
--
ALTER TABLE `practice_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_responses`
--
ALTER TABLE `practice_test_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD CONSTRAINT `practice_tests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  ADD CONSTRAINT `practice_test_questions_ibfk_1` FOREIGN KEY (`practice_test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  ADD CONSTRAINT `practice_test_attempts_ibfk_1` FOREIGN KEY (`practice_test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_responses`
--
ALTER TABLE `practice_test_responses`
  ADD CONSTRAINT `practice_test_responses_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `practice_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practice_test_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
