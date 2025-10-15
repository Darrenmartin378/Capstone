-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 08:12 AM
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
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@comprelearn.com', '$2y$10$uXxHjewefyErkbGYQ44EPezRLxE2z32C5f8WTssbB0ViCcGszAxku', 'System Administrator', 1, NULL, '2025-09-24 04:59:27', '2025-09-24 04:59:27');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `teacher_id`, `section_id`, `title`, `content`, `created_at`) VALUES
(1, 5, NULL, 'Announcement!', 'I will post an materials for you to read.', '2025-09-29 17:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `original_set_title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `difficulty` varchar(20) NOT NULL DEFAULT 'medium',
  `theme_settings` longtext DEFAULT NULL,
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
  `section_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
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
  `options` longtext DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `difficulty` varchar(20) DEFAULT 'medium',
  `word_limit` int(11) DEFAULT 50,
  `time_limit` int(11) DEFAULT 30,
  `rubrics` text DEFAULT NULL,
  `original_question_id` int(11) DEFAULT NULL,
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
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `response` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `essay_questions`
--

CREATE TABLE `essay_questions` (
  `question_id` int(11) NOT NULL,
  `set_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `points` int(11) DEFAULT 1,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `difficulty` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matching_pairs`
--

CREATE TABLE `matching_pairs` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `left_item` varchar(255) NOT NULL,
  `right_item` varchar(255) NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `pair_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matching_questions`
--

CREATE TABLE `matching_questions` (
  `question_id` int(11) NOT NULL,
  `set_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `left_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`left_items`)),
  `right_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`right_items`)),
  `correct_pairs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`correct_pairs`)),
  `points` int(11) DEFAULT 1,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `difficulty` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matching_questions`
--

INSERT INTO `matching_questions` (`question_id`, `set_id`, `question_text`, `left_items`, `right_items`, `correct_pairs`, `points`, `order_index`, `created_at`, `updated_at`, `difficulty`) VALUES
(13, 35, 'Match the following items with their correct answers:', '[\"Ball\",\"Black\"]', '[\"Board\",\"Pen\"]', '[\"Pen\",\"Board\"]', 2, 0, '2025-09-30 20:22:31', '2025-09-30 20:22:31', ''),
(14, 36, 'Match the following items with their correct answers:', '[\"Ball\",\"Black\"]', '[\"Board\",\"Pen\"]', '[\"Pen\",\"Board\"]', 2, 0, '2025-09-30 20:22:31', '2025-09-30 20:22:31', ''),
(15, 37, 'Match the following items with their correct answers:', '[\"Ball\",\"Black\"]', '[\"Board\",\"Pen\"]', '[\"Pen\",\"Board\"]', 2, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(16, 38, 'Match the following items with their correct answers:', '[\"Ball\",\"Black\"]', '[\"Board\",\"Pen\"]', '[\"1\",\"0\"]', 2, 0, '2025-09-30 20:35:40', '2025-10-02 14:19:29', ''),
(41, 74, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[1,2,0,3,4]', 5, 1, '2025-10-15 01:46:42', '2025-10-15 01:46:42', ''),
(42, 75, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[1,2,0,3,4]', 5, 1, '2025-10-15 01:46:42', '2025-10-15 01:46:42', ''),
(43, 76, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 02:48:44', '2025-10-15 02:48:44', ''),
(44, 77, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"Charcoal\",\"Sky\"]', 2, 2, '2025-10-15 02:48:44', '2025-10-15 04:02:45', ''),
(45, 78, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:26:49', '2025-10-15 04:26:49', ''),
(46, 78, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:26:49', '2025-10-15 04:26:49', ''),
(47, 79, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:26:49', '2025-10-15 04:26:49', ''),
(48, 79, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:26:49', '2025-10-15 04:26:49', ''),
(49, 80, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:36:42', '2025-10-15 04:36:42', ''),
(50, 80, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:36:42', '2025-10-15 04:36:42', ''),
(51, 81, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:36:42', '2025-10-15 04:36:42', ''),
(52, 81, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:36:42', '2025-10-15 04:36:42', ''),
(53, 81, 'Match the following colors with their descriptions:', '[\"Red\",\"Blue\",\"Green\"]', '[\"Color of blood\",\"Color of sky\",\"Color of grass\"]', '[\"0\",\"1\",\"2\"]', 3, 0, '2025-10-15 04:47:33', '2025-10-15 04:47:33', NULL),
(54, 81, 'Match the following animals with their habitats:', '[\"Lion\",\"Penguin\",\"Dolphin\",\"Eagle\"]', '[\"Ocean\",\"Savanna\",\"Arctic\",\"Sky\"]', '[\"1\",\"2\",\"0\",\"3\"]', 4, 1, '2025-10-15 04:47:46', '2025-10-15 04:47:46', NULL),
(55, 82, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:52:15', '2025-10-15 04:52:15', ''),
(56, 82, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:52:15', '2025-10-15 04:52:15', ''),
(57, 83, 'Match the following items with their correct answers:', '[\"Athena\",\"Aphrodite\",\"Hephaestus\",\"Hermes\",\"Apollo\"]', '[\"Molded Pandora\\u2019s body out of clay\",\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', '[\"1\",\"2\",\"0\",\"3\",\"4\"]', 5, 1, '2025-10-15 04:52:15', '2025-10-15 04:52:15', ''),
(58, 83, 'Match the following items with their correct answers:', '[\"Black\",\"Blue\"]', '[\"Sky\",\"Charcoal\"]', '[\"1\",\"0\"]', 2, 2, '2025-10-15 04:52:15', '2025-10-15 04:52:15', ''),
(59, 84, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 04:55:07', '2025-10-15 04:55:07', ''),
(60, 85, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 04:55:07', '2025-10-15 04:55:07', ''),
(61, 86, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 05:53:15', '2025-10-15 05:53:15', ''),
(62, 87, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 05:53:15', '2025-10-15 05:53:15', ''),
(63, 88, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 05:55:13', '2025-10-15 05:55:13', ''),
(64, 89, 'Match the following items with their correct answers:', '[\"Red\",\"Blue\",\"Green\",\"Yellow\"]', '[\"Apple\",\"Watermelon\",\"Blueberry\",\"Lemon\"]', '[\"0\",\"2\",\"1\",\"3\"]', 4, 1, '2025-10-15 05:55:13', '2025-10-15 05:55:13', '');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `material_type` enum('text','pdf','image','document') DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_question_links`
--

CREATE TABLE `material_question_links` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `question_set_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_question_links`
--

INSERT INTO `material_question_links` (`id`, `material_id`, `question_set_id`, `created_at`) VALUES
(11, 39, 74, '2025-10-15 01:46:42'),
(12, 39, 75, '2025-10-15 01:46:42');

-- --------------------------------------------------------

--
-- Table structure for table `mcq_questions`
--

CREATE TABLE `mcq_questions` (
  `question_id` int(11) NOT NULL,
  `set_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `choice_a` varchar(500) NOT NULL,
  `choice_b` varchar(500) NOT NULL,
  `choice_c` varchar(500) NOT NULL,
  `choice_d` varchar(500) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `points` int(11) DEFAULT 1,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `difficulty` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mcq_questions`
--

INSERT INTO `mcq_questions` (`question_id`, `set_id`, `question_text`, `choice_a`, `choice_b`, `choice_c`, `choice_d`, `correct_answer`, `points`, `order_index`, `created_at`, `updated_at`, `difficulty`) VALUES
(30, 32, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-09-30 19:58:30', '2025-09-30 19:58:30', ''),
(32, 37, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(33, 37, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(34, 38, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(35, 38, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 2, 0, '2025-09-30 20:35:40', '2025-10-02 14:19:29', ''),
(36, 39, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-10-02 12:43:02', '2025-10-02 12:43:02', ''),
(37, 39, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 1, 0, '2025-10-02 12:43:02', '2025-10-02 12:43:02', ''),
(38, 40, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-10-02 12:43:02', '2025-10-02 12:43:02', ''),
(39, 40, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 1, 0, '2025-10-02 12:43:02', '2025-10-02 12:43:02', ''),
(40, 41, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-10-02 13:16:25', '2025-10-02 13:16:25', ''),
(41, 42, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-10-02 13:16:25', '2025-10-02 13:16:25', ''),
(42, 43, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-10-02 13:19:57', '2025-10-02 13:19:57', ''),
(43, 44, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-10-02 13:19:57', '2025-10-02 13:19:57', ''),
(58, 76, 'Red', 'Apple', 'Banana', 'Grapes', 'Ponkan', 'A', 1, 1, '2025-10-15 02:48:44', '2025-10-15 02:48:44', ''),
(59, 77, 'Red', 'Apple', 'Banana', 'Grapes', 'Ponkan', 'A', 1, 1, '2025-10-15 02:48:44', '2025-10-15 02:48:44', '');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `username`, `name`, `email`, `password`, `student_id`, `created_at`) VALUES
(1, 'parent1', 'Mr. Dela Cruz', 'parent1@email.com', '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2203561, '2025-09-24 06:03:11'),
(2, 'parent2', 'Mrs. Santos', 'parent2@email.com', '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2203562, '2025-09-24 06:03:11'),
(3, 'parent_5', 'Rubelyn B. Martin', 'ruby@gmail.com', '$2y$10$S78c47l2YDT2xkrUxlpiseHomN7BUop097vpNWsCivcTphplQZ6LO', 5, '2025-09-24 06:08:28'),
(4, 'parent_6', 'Merlinda P. Cartagenas', 'merlinda@gmail.com', '$2y$10$TpLmghRLJK9voJqjPMSbQ.4vSLIfuXQd.apMs8OYSXFRkGLLcvbJS', 6, '2025-09-29 04:30:55');

-- --------------------------------------------------------

--
-- Table structure for table `practice_tests`
--

CREATE TABLE `practice_tests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `section_id` int(11) DEFAULT 0,
  `difficulty` enum('easy','medium','hard') DEFAULT 'easy',
  `timer_minutes` int(11) DEFAULT 30,
  `question_type` enum('mcq','matching','essay','mixed') DEFAULT 'mcq',
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
  `question_order` int(11) DEFAULT 0,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','matching','essay') NOT NULL,
  `points` int(11) DEFAULT 1,
  `mcq_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mcq_options`)),
  `correct_answer` varchar(10) DEFAULT NULL,
  `matching_left_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matching_left_items`)),
  `matching_right_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matching_right_items`)),
  `correct_matches` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`correct_matches`)),
  `essay_rubric` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_test_submissions`
--

CREATE TABLE `practice_test_submissions` (
  `id` int(11) NOT NULL,
  `practice_test_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` decimal(10,2) DEFAULT 0.00,
  `percentage` decimal(5,2) DEFAULT 0.00,
  `answered_questions` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `set_id` int(11) NOT NULL,
  `type` enum('mcq','matching','essay') NOT NULL,
  `question_text` text NOT NULL,
  `choices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`choices`)),
  `answer_key` text DEFAULT NULL,
  `points` int(11) DEFAULT 1,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group_id` int(11) DEFAULT NULL,
  `pair_order` int(11) DEFAULT 0,
  `left_item` varchar(500) DEFAULT NULL,
  `right_item` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_bank`
--

CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `set_title` varchar(255) NOT NULL,
  `set_id` int(11) DEFAULT NULL,
  `question_type` enum('multiple_choice','matching','essay') NOT NULL,
  `question_category` enum('comprehension','practice') DEFAULT 'comprehension',
  `question_text` text NOT NULL,
  `options_json` longtext DEFAULT NULL,
  `options` longtext DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_responses`
--

CREATE TABLE `question_responses` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_sets`
--

CREATE TABLE `question_sets` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `set_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `timer_minutes` int(11) DEFAULT 0,
  `open_at` datetime DEFAULT NULL,
  `difficulty` varchar(16) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_sets`
--

INSERT INTO `question_sets` (`id`, `teacher_id`, `section_id`, `set_title`, `description`, `created_at`, `updated_at`, `timer_minutes`, `open_at`, `difficulty`, `is_archived`) VALUES
(32, 5, 2, 'Test1', '', '2025-09-30 19:58:30', '2025-10-14 15:34:34', 10, '2025-10-01 03:59:00', 'easy', 0),
(35, 5, 2, 'Test2', '', '2025-09-30 20:22:30', '2025-10-14 15:34:37', 10, '2025-10-01 04:21:00', 'easy', 0),
(36, 5, 1, 'Test2', '', '2025-09-30 20:22:31', '2025-10-14 15:34:31', 10, '2025-10-01 04:21:00', 'easy', 0),
(37, 5, 2, 'Test3', '', '2025-09-30 20:35:40', '2025-10-14 15:33:39', 10, '2025-10-01 04:21:00', 'easy', 1),
(38, 5, 1, 'Test3', '', '2025-09-30 20:35:40', '2025-10-15 02:50:17', 10, '2025-10-03 04:21:00', 'easy', 1),
(39, 5, 2, 'Test4', '', '2025-10-02 12:43:02', '2025-10-14 15:33:39', 1, '2025-10-02 20:43:00', '', 1),
(40, 5, 1, 'Test4', '', '2025-10-02 12:43:02', '2025-10-14 15:33:39', 1, '2025-10-02 20:43:00', '', 1),
(41, 5, 2, 'Test5', '', '2025-10-02 13:16:25', '2025-10-14 15:33:39', 1, '2025-10-02 21:16:00', 'easy', 1),
(42, 5, 1, 'Test5', '', '2025-10-02 13:16:25', '2025-10-14 15:33:39', 1, '2025-10-02 21:16:00', 'easy', 1),
(43, 5, 2, 'Test6', '', '2025-10-02 13:19:57', '2025-10-14 15:33:39', 1, '2025-10-02 21:19:00', 'easy', 1),
(44, 5, 1, 'Test6', '', '2025-10-02 13:19:57', '2025-10-14 15:33:39', 1, '2025-10-02 21:19:00', 'easy', 1),
(74, 5, 2, 'Comprehension Questions - The Myth of Pandora’s Box', '', '2025-10-15 01:46:42', '2025-10-15 02:49:12', 0, NULL, NULL, 0),
(75, 5, 1, 'Comprehension Questions - The Myth of Pandora’s Box', '', '2025-10-15 01:46:42', '2025-10-15 02:08:16', 0, NULL, NULL, 1),
(76, 5, 2, 'TestA', '', '2025-10-15 02:48:44', '2025-10-15 02:48:44', 10, '2025-10-16 10:47:00', 'easy', 0),
(77, 5, 1, 'TestA', '', '2025-10-15 02:48:44', '2025-10-15 04:02:45', 10, '2025-10-15 12:03:00', 'easy', 0),
(78, 5, 2, 'TestB', '', '2025-10-15 04:26:48', '2025-10-15 04:26:49', 10, '2025-10-15 12:28:00', 'easy', 0),
(79, 5, 1, 'TestB', '', '2025-10-15 04:26:49', '2025-10-15 04:26:49', 10, '2025-10-15 12:28:00', 'easy', 0),
(80, 5, 2, 'TestC', '', '2025-10-15 04:36:42', '2025-10-15 04:36:42', 10, '2025-10-15 12:38:00', 'easy', 0),
(81, 5, 1, 'TestC', '', '2025-10-15 04:36:42', '2025-10-15 04:36:42', 10, '2025-10-15 12:38:00', 'easy', 0),
(82, 5, 2, 'TestD', '', '2025-10-15 04:52:15', '2025-10-15 04:52:15', 10, '2025-10-15 12:53:00', 'easy', 0),
(83, 5, 1, 'TestD', '', '2025-10-15 04:52:15', '2025-10-15 04:52:15', 10, '2025-10-15 12:53:00', 'easy', 0),
(84, 5, 2, 'TestE', '', '2025-10-15 04:55:07', '2025-10-15 04:55:07', 10, NULL, 'easy', 0),
(85, 5, 1, 'TestE', '', '2025-10-15 04:55:07', '2025-10-15 04:55:07', 10, NULL, 'easy', 0),
(86, 5, 2, 'TestF', '', '2025-10-15 05:53:14', '2025-10-15 05:54:24', 10, '2025-10-15 13:55:00', 'easy', 0),
(87, 5, 1, 'TestF', '', '2025-10-15 05:53:15', '2025-10-15 05:53:15', 10, '2025-10-15 13:53:00', 'easy', 0),
(88, 5, 2, 'TestG', '', '2025-10-15 05:55:13', '2025-10-15 05:55:13', 5, '2025-10-15 13:57:00', 'easy', 0),
(89, 5, 1, 'TestG', '', '2025-10-15 05:55:13', '2025-10-15 05:55:13', 5, '2025-10-15 13:57:00', 'easy', 0);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_responses`
--

CREATE TABLE `quiz_responses` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `set_title` varchar(255) NOT NULL,
  `section_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_answer` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `partial_score` decimal(5,2) DEFAULT NULL,
  `total_matches` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` decimal(10,2) DEFAULT 0.00,
  `max_score` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_scores`
--

CREATE TABLE `quiz_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `set_title` varchar(255) NOT NULL,
  `section_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_points` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_materials`
--

CREATE TABLE `reading_materials` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(128) DEFAULT NULL,
  `attachment_size` int(11) DEFAULT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reading_materials`
--

INSERT INTO `reading_materials` (`id`, `teacher_id`, `section_id`, `title`, `content`, `attachment_path`, `attachment_name`, `attachment_type`, `attachment_size`, `theme_settings`, `created_at`, `updated_at`) VALUES
(26, 5, 1, 'Types of Figurative Language', '<h1 class=\"post-title global-title\" style=\"font-size: 55px; margin: 0px 0px 20px; font-family: Alata, sans-serif; font-weight: 400; line-height: 1.1; color: rgb(26, 42, 48); font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: center; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;Types of Figurative Language</h1>\r\n<p><span style=\"color: rgb(26, 42, 48); font-family: Alata, sans-serif; font-size: 24px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">Figurative language is a form of expression that uses nonliteral meanings to convey a more abstract meaning or message. There are many types, including: similes, metaphors, idioms, hyperboles, and personification.</span></p>\r\n<h3 id=\"simile\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Simile</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using the words &ldquo;like&rdquo; or &ldquo;as&rdquo; to compare two things.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;His new shoes shined bright like a diamond.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;She ran as quick as a cheetah.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"metaphor\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Metaphor</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Comparing two things; however, unlike similes, they do&nbsp;<em>not</em>&nbsp;include &ldquo;like&rdquo; or &ldquo;as.&rdquo;</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Time is money.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Life is a highway.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"idiom\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Idiom</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using a phrase to state a message different from its literal meaning. Idioms are often culturally specific and have been accepted as common use.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Hit the sack!&rdquo; &rarr; translates to&nbsp;<em>go to bed</em></strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Under the weather.&rdquo; &rarr; translates to&nbsp;<em>feeling</em>&nbsp;<em>sick/ill</em></strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"hyperbole\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Hyperbole</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using an extreme exaggeration to emphasize a point.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;My bag weighs a ton!&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;I&rsquo;m so tired I could sleep for days.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"personification\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Personification</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Giving human characteristics to non-living objects or animals.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;The leaves on the tree danced in the wind.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;The birds sang a sweet melody in their nest.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<p>&nbsp;</p>', NULL, NULL, NULL, NULL, '{\"bg_color\":\"#ffffff\"}', '2025-09-30 18:44:01', '2025-09-30 18:44:01'),
(35, 5, 0, 'Quarter 1 Module 1.pdf', '<p></p>', 'uploads/materials/mat_68dd09b8552c67.17684286.pdf', 'Quarter 1 Module 1.pdf', 'application/pdf', 1224121, '{\"bg_color\":\"#ffffff\"}', '2025-10-01 11:00:08', '2025-10-01 11:00:08'),
(39, 5, 0, 'The Myth of Pandora’s Box', '<p><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">The&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">myth of Pandora</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;is one of the most enduring tales in Greek mythology, rich with symbolism about the human condition. To understand this ancient story, let&rsquo;s start by getting to know its central character. In Greek mythology, Pandora was created by the gods as a punishment to mankind. She was given a&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">box</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;by&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Zeus</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;and told never to open it.&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Curiosity</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;got the better of her, and when she opened the box, she released all the&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">evils</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;and&nbsp;</span><strong style=\"box-sizing: inherit; font-weight: bold; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">miseries</strong><span style=\"color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;of the world, such as sickness, death, and sorrow.</span></p>\r\n<h2 id=\"who-was-pandora\" class=\"wp-block-heading\" style=\"box-sizing: inherit; padding: 0px; margin: 1.5em 0px 0.5em; font-family: \'Roboto Condensed\', sans-serif; font-style: normal; font-weight: 600; font-size: 38px; line-height: 1.5; color: rgb(144, 85, 22); font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Who was Pandora?</h2>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Pandora&rsquo;s name itself provides clues. In Greek, &ldquo;Pandora&rdquo; (<em style=\"box-sizing: inherit; font-style: italic;\">&Pi;&alpha;&nu;&delta;ώ&rho;&alpha;</em>) translates to &ldquo;all-gifted&rdquo; or &ldquo;the one who bears all gifts.&rdquo; This refers to the many blessings bestowed upon her by the gods. Each deity granted her a specific talent or charm</p>\r\n<ul class=\"wp-block-list\" style=\"box-sizing: border-box; margin: 0px 0px 32px; padding: 0px 0px 0px 2em; list-style: disc; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"box-sizing: inherit;\">Athena gave her cleverness</li>\r\n<li style=\"box-sizing: inherit;\">Aphrodite gave her beauty</li>\r\n<li style=\"box-sizing: inherit;\">Apollo gave her musical skill, and so on.</li>\r\n</ul>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">In this way, Pandora was crafted to be the &ldquo;perfect woman.&rdquo;</p>\r\n<h2 id=\"who-created-pandora-and-why\" class=\"wp-block-heading\" style=\"box-sizing: inherit; padding: 0px; margin: 1.5em 0px 0.5em; font-family: \'Roboto Condensed\', sans-serif; font-style: normal; font-weight: 600; font-size: 38px; line-height: 1.5; color: rgb(144, 85, 22); font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Who Created Pandora and Why?</h2>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">In the Greek myths, Pandora is not born in the usual way, but is instead crafted by the gods themselves. This divine origin sets her apart from other mortals and highlights her significance in the grand scheme of the cosmos.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">The creation of Pandora was a collaborative effort among the gods, with each deity contributing their own special gift or attribute. Let&rsquo;s imagine the scene on Mount Olympus:</p>\r\n<ul class=\"wp-block-list\" style=\"box-sizing: border-box; margin: 0px 0px 32px; padding: 0px 0px 0px 2em; list-style: disc; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"box-sizing: inherit;\"><strong style=\"box-sizing: inherit; font-weight: bold;\">Hephaestus</strong>, the god of craftsmanship, molds Pandora&rsquo;s body out of clay, shaping her into a form of perfect beauty.</li>\r\n<li style=\"box-sizing: inherit;\"><strong style=\"box-sizing: inherit; font-weight: bold;\">Athena</strong>, goddess of wisdom and war, dresses Pandora in a shimmering gown and teaches her needlework and weaving.</li>\r\n<li style=\"box-sizing: inherit;\"><strong style=\"box-sizing: inherit; font-weight: bold;\">Aphrodite</strong>, the goddess of love, bestows upon Pandora irresistible charm and grace.</li>\r\n<li style=\"box-sizing: inherit;\"><strong style=\"box-sizing: inherit; font-weight: bold;\">Hermes</strong>, the messenger god, gives her a cunning, deceitful mind and a seductive voice.</li>\r\n</ul>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Each god, in turn, adds their blessing (or curse) to this new creation. It&rsquo;s almost as if Pandora is a divine project, a showcase of the gods&rsquo; powers and a testament to their superiority over mortal beings.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">But why would the gods go to all this trouble? What purpose could they have for creating this perfect woman? To answer that, we need to look at the larger context of the myth.</p>\r\n<h2 id=\"the-creation-of-the-first-woman--pandoras-box\" class=\"wp-block-heading\" style=\"box-sizing: inherit; padding: 0px; margin: 1.5em 0px 0.5em; font-family: \'Roboto Condensed\', sans-serif; font-style: normal; font-weight: 600; font-size: 38px; line-height: 1.5; color: rgb(144, 85, 22); font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">The Creation of the First Woman &amp; Pandora&rsquo;s Box</h2>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">In Greek mythology, Pandora is not just the first woman, but a key figure in the story of how suffering and evil came into the world. Her creation was intricately tied to the actions of Prometheus and his brother Epimetheus.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">According to the myth,&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">Prometheus</strong>, a Titan known for his intelligence, stole fire from the gods and gave it to humanity. Angered by this defiance,&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">Zeus</strong>, the king of the gods, decided to punish both Prometheus and mankind. He ordered Hephaestus, the god of craftsmanship, to create the first woman &ndash; Pandora.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\"><strong style=\"box-sizing: inherit; font-weight: bold;\">Epimetheus</strong>, the brother of Prometheus, played a crucial role in this story. He was tasked by Zeus to distribute various qualities to the animals of the earth. However, by the time he got to humans, he had already given out all the good qualities, leaving none for mankind. This lack of foresight is what allowed Prometheus to trick Zeus and steal fire for humans in the first place.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">When Prometheus was caught and punished for his theft, Zeus turned his attention to Epimetheus. He offered Pandora as a&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">wife</strong>&nbsp;to Epimetheus, who was captivated by her beauty and eagerly welcomed her.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Pandora, whose name means &ldquo;all-gifted,&rdquo; was blessed by the gods with numerous talents and charms. She was beautiful, clever, and alluring. However, she was also given less desirable traits &ndash; most notably, an insatiable&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">curiosity</strong>.</p>\r\n<p style=\"box-sizing: inherit; margin-top: 0px; margin-bottom: 32px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Before sending Pandora to earth, Zeus gave her a&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">jar</strong>&nbsp;(in later versions, a&nbsp;<strong style=\"box-sizing: inherit; font-weight: bold;\">box</strong>) which he warned her never to open. Epimetheus, in his shortsightedness, failed to heed his brother&rsquo;s warnings about accepting gifts from the gods. He allowed Pandora into his home, and inevitably, her curiosity got the better of her.</p>\r\n<div class=\"wp-block-image\" style=\"box-sizing: inherit; margin-bottom: 0px; margin-top: 0px; color: rgb(62, 76, 89); font-family: \'Roboto Flex\', sans-serif; font-size: 17px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">&nbsp;</div>', NULL, NULL, NULL, NULL, '{\"bg_color\":\"#ffffff\"}', '2025-10-14 14:12:10', '2025-10-14 14:12:10'),
(42, 5, 0, 'The Life of Hercules in Myth & Legend', '<p><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/hercules/\" data-ci-uid=\"1-10115-en\">Hercules</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;is the&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/disambiguation/Roman/\" data-ci-uid=\"1-382-en\">Roman</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;name for the&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/disambiguation/greek/\" data-ci-uid=\"1-143-en\">Greek</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;hero&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/hercules/\" data-ci-uid=\"1-10290-en\">Herakles</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">, the most popular figure from ancient&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/Greek_Mythology/\" data-ci-uid=\"1-11221-en\">Greek mythology</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">. Hercules was the son of&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/zeus/\" data-ci-uid=\"1-538-en\">Zeus</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">, king of the gods, and the mortal woman Alcmene. Zeus, who was always chasing one woman or another, took on the form of Alcmene\'s husband, Amphitryon, and visited Alcmene one night in her bed, and so Hercules was born a demi-</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/God/\" data-ci-uid=\"1-10299-en\">god</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;with incredible strength and stamina. He performed amazing feats, including wrestling&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/disambiguation/Death/\" data-ci-uid=\"1-416-en\">death</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;and traveling twice to the underworld, and his stories were told throughout&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/greece/\" data-ci-uid=\"1-119-en\">Greece</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">&nbsp;and later in&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/Rome/\" data-ci-uid=\"1-68-en\">Rome</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">, yet his life was far from easy from the moment of his birth, and his relationships with others were often disastrous. This was because&nbsp;</span><a style=\"-webkit-font-smoothing: antialiased; text-rendering: optimizelegibility; color: rgb(181, 38, 0); font-weight: bold; text-decoration: none; font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255);\" href=\"https://www.worldhistory.org/Hera/\" data-ci-uid=\"1-10295-en\">Hera</a><span style=\"color: rgb(51, 51, 51); font-family: \'Libre Baskerville\', \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif; font-size: 18px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">, the wife of Zeus, knew that Hercules was her husband\'s illegitimate son and sought to destroy him. In fact, he was born with the name Alcaeus and later took the name Herakles, meaning \"Glory of Hera\", signifying that he would become famous through his difficulties with the goddess.</span></p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>', NULL, NULL, NULL, NULL, '{\"bg_color\":\"#ffffff\"}', '2025-10-15 01:44:31', '2025-10-15 01:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `is_correct` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `graded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Rizal', NULL, '2025-09-26 17:57:03'),
(2, 'Bonifacio', NULL, '2025-09-29 04:27:45'),
(3, 'Mabini', NULL, '2025-09-29 17:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `student_number`, `email`, `gender`, `password`, `section_id`, `created_at`, `updated_at`) VALUES
(4, '2203562', 'Maria Santos', '2203562', 'maria@student.com', 'female', '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, '2025-09-24 06:03:02', '2025-09-24 06:03:02'),
(5, NULL, 'Darren B. Martin', '2203561', 'martindarren3561@gmail.com', 'male', '$2y$10$q20wF23AJrnVSsUD2U7M3.jUy7Jd1TVrueo5d2mDy/gvcnV9uPc16', 1, '2025-09-24 06:07:56', '2025-09-26 18:29:03'),
(6, NULL, 'Clarisse P. Cartagena', '2203661', 'clarisse@gmail.com', 'female', '$2y$10$4ASkHhDQwBWLiaSioSdAyOVMkuxcdjhLfZFTnIcUcjZqXggUz/6hO', 2, '2025-09-29 04:29:45', '2025-09-29 04:29:45');

-- --------------------------------------------------------

--
-- Table structure for table `student_responses`
--

CREATE TABLE `student_responses` (
  `response_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_set_id` int(11) NOT NULL,
  `question_type` enum('mcq','matching','essay') NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `graded_at` timestamp NULL DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_responses`
--

INSERT INTO `student_responses` (`response_id`, `student_id`, `question_set_id`, `question_type`, `question_id`, `answer`, `is_correct`, `score`, `submitted_at`, `graded_at`, `graded_by`, `feedback`) VALUES
(48, 5, 36, 'matching', 14, '[\"Pen\",\"Board\"]', 1, 2.00, '2025-10-02 12:29:11', NULL, NULL, NULL),
(49, 5, 40, 'mcq', 38, 'B', 1, 1.00, '2025-10-02 12:59:48', NULL, NULL, NULL),
(50, 5, 38, 'matching', 16, '[\"Pen\",\"Board\"]', 1, 2.00, '2025-10-02 13:08:30', NULL, NULL, NULL),
(51, 5, 38, 'mcq', 34, 'B', 1, 1.00, '2025-10-02 13:08:30', NULL, NULL, NULL),
(52, 5, 38, 'mcq', 35, 'B', 1, 1.00, '2025-10-02 13:08:30', NULL, NULL, NULL),
(53, 5, 42, 'mcq', 41, 'A', 1, 1.00, '2025-10-02 13:16:47', NULL, NULL, NULL),
(54, 5, 44, 'mcq', 43, 'A', 1, 1.00, '2025-10-02 13:20:22', NULL, NULL, NULL),
(59, 5, 75, 'mcq', 42, '[\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Molded Pandora\\u2019s body out of clay\",\"Gave her a deceitful mind and a seductive voice\",\"Gave her musical skill\"]', 0, 0.00, '2025-10-15 01:49:08', NULL, NULL, NULL),
(61, 5, 77, 'matching', 44, '[\"Charcoal\",\"Sky\"]', 1, 2.00, '2025-10-15 04:03:16', NULL, NULL, NULL),
(62, 5, 77, 'mcq', 59, 'A', 1, 1.00, '2025-10-15 04:03:16', NULL, NULL, NULL),
(63, 5, 79, 'matching', 47, '[\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Molded Pandora\\u2019s body out of clay\",\"Gave her musical skill\",\"Gave her a deceitful mind and a seductive voice\"]', 0, 0.00, '2025-10-15 04:29:29', NULL, NULL, NULL),
(64, 5, 79, 'matching', 48, '[\"Charcoal\",\"Sky\"]', 0, 0.00, '2025-10-15 04:29:29', NULL, NULL, NULL),
(65, 5, 81, 'matching', 51, '[\"Gave her cleverness and taught weaving\",\"Gave her irresistible beauty and charm\",\"Molded Pandora\\u2019s body out of clay\",\"Gave her musical skill\",\"Gave her a deceitful mind and a seductive voice\"]', 0, 0.00, '2025-10-15 04:38:45', NULL, NULL, NULL),
(66, 5, 81, 'matching', 52, '[\"Charcoal\",\"Sky\"]', 0, 0.00, '2025-10-15 04:38:46', NULL, NULL, NULL),
(67, 5, 83, 'matching', 57, '[\"1\",\"2\",\"0\",\"4\",\"3\"]', 0, 3.00, '2025-10-15 04:53:25', NULL, NULL, NULL),
(68, 5, 83, 'mcq', 58, '[\"1\",\"0\"]', 0, 0.00, '2025-10-15 04:53:25', NULL, NULL, NULL),
(69, 5, 85, 'matching', 60, '[\"0\",\"2\",\"1\",\"3\"]', 1, 4.00, '2025-10-15 04:55:35', NULL, NULL, NULL),
(70, 5, 87, 'matching', 62, '[\"0\",\"2\",\"1\",\"3\"]', 1, 4.00, '2025-10-15 06:08:08', NULL, NULL, NULL);

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
(5, 'Jeffry M. Duria', 'SirJeff', 'jeff@gmail.com', '$2y$10$13RBLbSe.16CKCuSwfpBk.iCDEIKRPo4I8r9cGuiLRtGbzWJX3LSS', '2025-09-28 15:32:53', '2025-09-28 15:32:53'),
(6, 'John E. Doe', 'JohnDoe', 'john@gmail.com', '$2y$10$PlYFaYgxyqXucrekcUb5iuJxZeN5F3zpDKUHnCrMdWsEifwvUD/ua', '2025-09-29 17:44:28', '2025-09-29 17:44:28');

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

--
-- Dumping data for table `teacher_sections`
--

INSERT INTO `teacher_sections` (`id`, `teacher_id`, `section_id`, `created_at`) VALUES
(12, 5, 2, '2025-09-29 04:28:32'),
(13, 5, 1, '2025-09-29 04:28:32'),
(14, 6, 3, '2025-09-29 17:44:28');

-- --------------------------------------------------------

--
-- Table structure for table `viewed_notifications`
--

CREATE TABLE `viewed_notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `notification_type` enum('announcement','question_set','material') NOT NULL,
  `notification_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warm_ups`
--

CREATE TABLE `warm_ups` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `section_id` (`section_id`);

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
  ADD KEY `student_id` (`student_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `essay_questions`
--
ALTER TABLE `essay_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `set_id` (`set_id`);

--
-- Indexes for table `matching_pairs`
--
ALTER TABLE `matching_pairs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_order` (`question_id`,`pair_order`);

--
-- Indexes for table `matching_questions`
--
ALTER TABLE `matching_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `set_id` (`set_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `material_question_links`
--
ALTER TABLE `material_question_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_material_set` (`material_id`,`question_set_id`),
  ADD KEY `question_set_id` (`question_set_id`);

--
-- Indexes for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `set_id` (`set_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `practice_test_id` (`practice_test_id`);

--
-- Indexes for table `practice_test_submissions`
--
ALTER TABLE `practice_test_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`practice_test_id`,`student_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_set_type` (`set_id`,`type`),
  ADD KEY `idx_order` (`set_id`,`order_index`),
  ADD KEY `idx_questions_group_id` (`group_id`),
  ADD KEY `idx_questions_type_group` (`type`,`group_id`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `question_responses`
--
ALTER TABLE `question_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_question_student` (`question_id`,`student_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `question_sets`
--
ALTER TABLE `question_sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response` (`student_id`,`question_id`,`set_title`,`section_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_quiz_per_student` (`student_id`,`set_title`,`section_id`,`teacher_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `reading_materials`
--
ALTER TABLE `reading_materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response` (`question_id`,`student_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `student_responses`
--
ALTER TABLE `student_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `question_set_id` (`question_set_id`),
  ADD KEY `idx_student_question` (`student_id`,`question_set_id`,`question_type`,`question_id`),
  ADD KEY `idx_question_type` (`question_type`,`question_id`);

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
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `viewed_notifications`
--
ALTER TABLE `viewed_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`student_id`,`notification_type`,`notification_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `notification_type` (`notification_type`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `warm_ups`
--
ALTER TABLE `warm_ups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `essay_questions`
--
ALTER TABLE `essay_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `matching_pairs`
--
ALTER TABLE `matching_pairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `matching_questions`
--
ALTER TABLE `matching_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_question_links`
--
ALTER TABLE `material_question_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `practice_tests`
--
ALTER TABLE `practice_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `practice_test_submissions`
--
ALTER TABLE `practice_test_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `question_responses`
--
ALTER TABLE `question_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_sets`
--
ALTER TABLE `question_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `reading_materials`
--
ALTER TABLE `reading_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_responses`
--
ALTER TABLE `student_responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `viewed_notifications`
--
ALTER TABLE `viewed_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warm_ups`
--
ALTER TABLE `warm_ups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_assignments`
--
ALTER TABLE `assessment_assignments`
  ADD CONSTRAINT `assessment_assignments_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_assignments_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_responses`
--
ALTER TABLE `assessment_responses`
  ADD CONSTRAINT `assessment_responses_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_responses_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_responses_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `essay_questions`
--
ALTER TABLE `essay_questions`
  ADD CONSTRAINT `essay_questions_ibfk_1` FOREIGN KEY (`set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matching_pairs`
--
ALTER TABLE `matching_pairs`
  ADD CONSTRAINT `matching_pairs_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matching_questions`
--
ALTER TABLE `matching_questions`
  ADD CONSTRAINT `matching_questions_ibfk_1` FOREIGN KEY (`set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `material_question_links`
--
ALTER TABLE `material_question_links`
  ADD CONSTRAINT `material_question_links_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `reading_materials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_question_links_ibfk_2` FOREIGN KEY (`question_set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  ADD CONSTRAINT `mcq_questions_ibfk_1` FOREIGN KEY (`set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD CONSTRAINT `practice_tests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  ADD CONSTRAINT `practice_test_questions_ibfk_1` FOREIGN KEY (`practice_test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practice_test_submissions`
--
ALTER TABLE `practice_test_submissions`
  ADD CONSTRAINT `practice_test_submissions_ibfk_1` FOREIGN KEY (`practice_test_id`) REFERENCES `practice_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD CONSTRAINT `question_bank_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_bank_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `question_responses`
--
ALTER TABLE `question_responses`
  ADD CONSTRAINT `question_responses_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_responses_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_sets`
--
ALTER TABLE `question_sets`
  ADD CONSTRAINT `question_sets_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD CONSTRAINT `quiz_responses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_responses_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_responses_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  ADD CONSTRAINT `quiz_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_scores_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_scores_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_responses`
--
ALTER TABLE `student_responses`
  ADD CONSTRAINT `student_responses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_responses_ibfk_2` FOREIGN KEY (`question_set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD CONSTRAINT `teacher_sections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_sections_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warm_ups`
--
ALTER TABLE `warm_ups`
  ADD CONSTRAINT `warm_ups_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
