-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 04:43 AM
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
(16, 38, 'Match the following items with their correct answers:', '[\"Ball\",\"Black\"]', '[\"Board\",\"Pen\"]', '[\"Pen\",\"Board\"]', 2, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', '');

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
(31, 33, 'Which of the following is color red?', 'Cherry', 'Guava', 'Banana', 'Grapes', 'A', 1, 0, '2025-09-30 20:03:47', '2025-09-30 20:03:47', ''),
(32, 37, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(33, 37, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(34, 38, 'Sky____', 'Ball', 'Way', 'Board', 'Toy', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', ''),
(35, 38, 'Electric___', 'Wall', 'Fan', 'Water', 'Tree', 'B', 1, 0, '2025-09-30 20:35:40', '2025-09-30 20:35:40', '');

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
  `difficulty` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_sets`
--

INSERT INTO `question_sets` (`id`, `teacher_id`, `section_id`, `set_title`, `description`, `created_at`, `updated_at`, `timer_minutes`, `open_at`, `difficulty`) VALUES
(32, 5, 2, 'Test1', '', '2025-09-30 19:58:30', '2025-09-30 19:58:30', 10, '2025-10-01 03:59:00', 'easy'),
(33, 5, 1, 'Test1', '', '2025-09-30 20:03:46', '2025-09-30 20:03:47', 30, NULL, ''),
(35, 5, 2, 'Test2', '', '2025-09-30 20:22:30', '2025-09-30 20:22:31', 10, '2025-10-01 04:21:00', 'easy'),
(36, 5, 1, 'Test2', '', '2025-09-30 20:22:31', '2025-09-30 20:22:31', 10, '2025-10-01 04:21:00', 'easy'),
(37, 5, 2, 'Test3', '', '2025-09-30 20:35:40', '2025-09-30 20:35:40', 10, '2025-10-01 04:21:00', 'easy'),
(38, 5, 1, 'Test3', '', '2025-09-30 20:35:40', '2025-09-30 20:35:40', 10, '2025-10-01 04:21:00', 'easy');

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
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reading_materials`
--

INSERT INTO `reading_materials` (`id`, `teacher_id`, `section_id`, `title`, `content`, `theme_settings`, `created_at`, `updated_at`) VALUES
(26, 5, 1, 'Types of Figurative Language', '<h1 class=\"post-title global-title\" style=\"font-size: 55px; margin: 0px 0px 20px; font-family: Alata, sans-serif; font-weight: 400; line-height: 1.1; color: rgb(26, 42, 48); font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: center; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;Types of Figurative Language</h1>\r\n<p><span style=\"color: rgb(26, 42, 48); font-family: Alata, sans-serif; font-size: 24px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial; display: inline !important; float: none;\">Figurative language is a form of expression that uses nonliteral meanings to convey a more abstract meaning or message. There are many types, including: similes, metaphors, idioms, hyperboles, and personification.</span></p>\r\n<h3 id=\"simile\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Simile</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using the words &ldquo;like&rdquo; or &ldquo;as&rdquo; to compare two things.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;His new shoes shined bright like a diamond.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;She ran as quick as a cheetah.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"metaphor\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Metaphor</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Comparing two things; however, unlike similes, they do&nbsp;<em>not</em>&nbsp;include &ldquo;like&rdquo; or &ldquo;as.&rdquo;</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Time is money.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Life is a highway.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"idiom\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Idiom</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using a phrase to state a message different from its literal meaning. Idioms are often culturally specific and have been accepted as common use.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Hit the sack!&rdquo; &rarr; translates to&nbsp;<em>go to bed</em></strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;Under the weather.&rdquo; &rarr; translates to&nbsp;<em>feeling</em>&nbsp;<em>sick/ill</em></strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"hyperbole\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Hyperbole</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Using an extreme exaggeration to emphasize a point.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;My bag weighs a ton!&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;I&rsquo;m so tired I could sleep for days.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<h3 id=\"personification\" style=\"font-family: Alata, sans-serif; font-weight: 400; line-height: 1.5; color: rgb(26, 42, 48); margin: 45px 0px 20px -1px; font-size: 31px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">Personification</h3>\r\n<ul style=\"margin: 0px 0px 40px 15px; padding-left: 15px; list-style: outside disc; color: rgb(69, 69, 69); font-family: Muli, sans-serif; font-size: 19px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"margin-bottom: 10px;\">Definition: Giving human characteristics to non-living objects or animals.</li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">Examples:</strong>\r\n<ul style=\"margin: 15px 0px; padding-left: 15px; list-style: outside circle; font-size: 17.1px;\">\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;The leaves on the tree danced in the wind.&rdquo;</strong></li>\r\n<li style=\"margin-bottom: 10px;\"><strong style=\"font-weight: bolder; color: rgb(26, 42, 48);\">&ldquo;The birds sang a sweet melody in their nest.&rdquo;</strong></li>\r\n</ul>\r\n</li>\r\n</ul>\r\n<p>&nbsp;</p>', '{\"bg_color\":\"#ffffff\"}', '2025-09-30 18:44:01', '2025-09-30 18:44:01');

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
(47, 5, 33, 'mcq', 31, 'A', 1, 1.00, '2025-10-01 02:31:17', NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `matching_questions`
--
ALTER TABLE `matching_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

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
-- Constraints for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  ADD CONSTRAINT `mcq_questions_ibfk_1` FOREIGN KEY (`set_id`) REFERENCES `question_sets` (`id`) ON DELETE CASCADE;

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
