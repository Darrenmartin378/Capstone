-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2025 at 08:29 PM
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
(7, 27, 'Match the following items with their correct answers:', '[\"Run\",\"Beautiful\",\"Yesterday\",\"Book\",\"Quickly\"]', '[\"Noun\",\"Adverb\",\"Verb\",\"Adjective\",\"Adverb of time\"]', '[\"Verb\",\"Adjective\",\"Adverb of time\",\"Noun\",\"Adverb\"]', 5, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(8, 27, 'Match the following items with their correct answers:', '[\"She is eating lunch.\",\"They will go to the park.\",\"He played basketball yesterday.\",\"I read every night.\"]', '[\"Simple Past Tense\",\"Simple Present Tense\",\"Simple Future Tense\",\"Present Continuous Tense\"]', '[\"Present Continuous Tense\",\"Simple Future Tense\",\"Simple Past Tense\",\"Simple Present Tense\"]', 4, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(9, 28, 'Match the following items with their correct answers:', '[\"Red\",\"Yellow\",\"Blue\"]', '[\"Blueberry\",\"Banana\",\"Cherry\"]', '[\"Cherry\",\"Banana\",\"Blueberry\"]', 3, 0, '2025-09-29 14:43:34', '2025-09-29 14:43:34', ''),
(10, 30, 'Match the following items with their correct answers:', '[\"Run\",\"Beautiful\"]', '[\"Verb\",\"Adjective\"]', '[\"Verb\",\"Adjective\"]', 2, 0, '2025-09-29 18:14:54', '2025-09-29 18:14:54', ''),
(11, 31, 'Match the following items with their correct answers:', '[\"Red\",\"Yellow\",\"Blue\"]', '[\"Blueberry\",\"Banana\",\"Cherry\"]', '[\"Cherry\",\"Banana\",\"Blueberry\"]', 2, 0, '2025-09-29 18:27:39', '2025-09-29 18:27:39', '');

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
(20, 26, 'Color Yellow', 'Lemon', 'Blueberry', 'Durian', 'Pomelo', 'A', 1, 0, '2025-09-29 13:42:08', '2025-09-29 13:42:08', ''),
(21, 27, 'Which of the following is a declarative sentence?', 'Are you going to school today?', 'Please close the door.', 'I am reading my English book.', 'What a beautiful day!', 'C', 1, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(22, 27, 'Choose the correct form of the verb:\r\n“She ___ to the market every Sunday.”', 'go', 'goes', 'going', 'gone', 'B', 1, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(23, 27, 'What is the plural form of the word child?', 'childs', 'children', 'childes', 'childrens', 'B', 1, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(24, 27, 'Which word is a synonym of happy?', 'sad', 'angry', 'joyful', 'tired', 'C', 1, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(25, 27, 'Choose the correct punctuated sentence:', 'maria likes apples oranges and bananas.', 'Maria likes apples, oranges, and bananas.', 'Maria likes, apples, oranges, and bananas.', 'Maria, likes apples oranges and bananas.', 'B', 1, 0, '2025-09-29 14:32:43', '2025-09-29 14:32:43', ''),
(26, 29, 'Which of the following is not part of primary colors', 'Yellow', 'Black', 'Red', 'Blue', 'B', 1, 0, '2025-09-29 16:55:12', '2025-09-29 16:55:12', ''),
(27, 30, 'Which of the following is a declarative sentence?', 'Are you going to school today?', 'Please close the door.', 'I am reading my English book.', 'What a beautiful day!', 'C', 1, 0, '2025-09-29 18:14:54', '2025-09-29 18:14:54', ''),
(28, 30, 'Choose the correct form of the verb:\r\n“She ___ to the market every Sunday.”', 'go', 'goes', 'going', 'gone', 'B', 1, 0, '2025-09-29 18:14:54', '2025-09-29 18:14:54', ''),
(29, 30, 'What is the plural form of the word child?', 'childs', 'children', 'childes', 'childrens', 'B', 1, 0, '2025-09-29 18:14:54', '2025-09-29 18:14:54', '');

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
(26, 5, 1, 'Quiz1', '', '2025-09-29 13:42:08', '2025-09-29 13:42:08', 30, '2025-09-29 21:41:00', 'medium'),
(27, 5, 1, 'Quiz2', '', '2025-09-29 14:32:43', '2025-09-29 14:32:43', 30, NULL, 'medium'),
(28, 5, 1, 'Quiz 3', '', '2025-09-29 14:43:34', '2025-09-29 14:43:34', 30, '2025-09-29 22:44:00', 'easy'),
(29, 5, 2, 'Quiz1', '', '2025-09-29 16:55:12', '2025-09-29 16:55:12', 30, '2025-09-30 00:54:00', 'easy'),
(30, 5, 1, 'Quiz4', '', '2025-09-29 18:14:54', '2025-09-29 18:14:54', 30, '2025-09-30 02:13:00', 'medium'),
(31, 5, 1, 'Quiz5', '', '2025-09-29 18:27:39', '2025-09-29 18:27:39', 10, '2025-09-30 02:27:00', 'easy');

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
  `title` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reading_materials`
--

INSERT INTO `reading_materials` (`id`, `teacher_id`, `title`, `content`, `theme_settings`, `created_at`, `updated_at`) VALUES
(21, 5, 'Lion', '<section id=\"ref325410\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; scroll-margin: 50px; color: rgb(26, 26, 26); font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\" data-level=\"1\" data-has-spy=\"true\">\r\n<h2 class=\"h1\" style=\"box-sizing: border-box; margin: 0px 0px 16px; padding: 0px; border: 0px solid; font-size: 28.686px; font-weight: 800; font-family: Georgia, serif; font-style: normal; line-height: 1.2;\">General characteristics</h2>\r\n<div class=\"assemblies multiple medialist slider js-slider position-relative d-inline-flex align-items-center mw-100 initialized\" style=\"box-sizing: border-box; margin: 0px 0px 30px 30px; padding: 0px; border: 0px solid; display: flex; max-width: 300px; position: relative; align-items: center; --floated-module-margin: 0 0 30px 30px; --floated-module-width: 300px; min-width: 280px; clear: right; float: right;\" data-type=\"other\">\r\n<div class=\"slider-container js-slider-container overflow-hidden d-flex rw-slider rw-prev-disabled\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; position: relative; display: flex; overflow: hidden; width: 300px;\">\r\n<div class=\"rw-track d-flex align-items-center\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; overflow-x: scroll; scroll-snap-type: x mandatory; scrollbar-width: none; white-space: nowrap; display: flex; align-items: center; width: 300px;\">\r\n<div class=\"position-relative rw-slide col-100 px-20\" style=\"box-sizing: border-box; margin: 0px; padding: 0px 20px; border: 0px solid; display: inline-block; scroll-snap-align: start; white-space: normal; flex: 0 0 auto; width: 300px; position: relative; vertical-align: top;\">\r\n<figure class=\"md-assembly m-0 mb-md-0 card card-borderless print-false\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: none; --card-background-color: #fff; --card-border-width: 1px; --card-border-color: #f2f2f2; --card-border-color-hover: #ddd; --card-border-radius: 0.5rem; --card-box-shadow: 0 0 8px rgba(0, 0, 0, 0.08); --card-font-size: 1rem; --card-line-height: 1.2; --card-padding-x: 20px; --card-padding-y: 15px; --card-media-horizontal-min-width: 100px; background-color: rgb(255, 255, 255); border-radius: 8px; box-shadow: none; font-size: 16px; line-height: 1.2;\" data-assembly-id=\"159005\" data-asm-type=\"image\">\r\n<div class=\"md-assembly-wrapper card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-image: none 100% / 1 / 0 stretch; border-radius: inherit; overflow: hidden; border: 1px solid rgb(242, 242, 242);\" data-type=\"image\"><a class=\"gtm-assembly-link position-relative d-flex align-items-center justify-content-center media-overlay-link card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); overflow: hidden; display: flex; position: relative; align-items: center; justify-content: center; border-radius: inherit; cursor: pointer;\" href=\"https://cdn.britannica.com/29/150929-050-547070A1/lion-Kenya-Masai-Mara-National-Reserve.jpg\" data-href=\"/media/1/342664/159005\"><picture style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 258px;\"><source style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\" srcset=\"https://cdn.britannica.com/29/150929-050-547070A1/lion-Kenya-Masai-Mara-National-Reserve.jpg?w=300\" media=\"(min-width: 680px)\"><img style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: block; vertical-align: middle; max-width: 100%; height: auto; max-height: 100%; width: 258px;\" src=\"https://cdn.britannica.com/29/150929-050-547070A1/lion-Kenya-Masai-Mara-National-Reserve.jpg?w=300\" alt=\"male lion\" loading=\"eager\" data-width=\"1600\" data-height=\"1085\"></picture>\r\n<div class=\"position-absolute top-10 left-10 assembly-slide-tag rounded-lg\" style=\"box-sizing: border-box; margin: 0px; padding: 5px 8px; border: 0px solid; position: absolute; top: 10px; left: 10px; border-radius: 8px; background-color: rgb(14, 63, 112); color: rgb(255, 255, 255); font-size: 12px; font-weight: bold;\">1 of 2</div>\r\n</a></div>\r\n<figcaption class=\"card-body\" style=\"box-sizing: border-box; margin: 0px; padding: 15px 0px 0px; border: 0px solid; font-size: 16px;\">\r\n<div class=\"md-assembly-caption text-muted font-14 font-serif line-clamp\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; white-space: normal; color: rgb(102, 102, 102); font-family: Georgia, serif; font-size: 14px; -webkit-line-clamp: 3; position: relative;\"><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"><a class=\"gtm-assembly-link md-assembly-title font-weight-bold d-inline font-sans-serif mr-5 media-overlay-link\" style=\"box-sizing: border-box; margin: 0px 5px 0px 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); display: inline; font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-weight: bold; cursor: pointer;\" href=\"https://cdn.britannica.com/29/150929-050-547070A1/lion-Kenya-Masai-Mara-National-Reserve.jpg\" data-href=\"/media/1/342664/159005\">male lion</a><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Male lion (<em style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Panthera leo</em>) in the Masai Mara National Reserve, Kenya.</span></span></div>\r\n</figcaption>\r\n</figure>\r\n</div>\r\n<div class=\"position-relative rw-slide col-100 px-20\" style=\"box-sizing: border-box; margin: 0px; padding: 0px 20px; border: 0px solid; display: inline-block; scroll-snap-align: start; white-space: normal; flex: 0 0 auto; width: 300px; position: relative; vertical-align: top;\">\r\n<figure class=\"md-assembly m-0 mb-md-0 card card-borderless print-false\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: none; --card-background-color: #fff; --card-border-width: 1px; --card-border-color: #f2f2f2; --card-border-color-hover: #ddd; --card-border-radius: 0.5rem; --card-box-shadow: 0 0 8px rgba(0, 0, 0, 0.08); --card-font-size: 1rem; --card-line-height: 1.2; --card-padding-x: 20px; --card-padding-y: 15px; --card-media-horizontal-min-width: 100px; background-color: rgb(255, 255, 255); border-radius: 8px; box-shadow: none; font-size: 16px; line-height: 1.2;\" data-assembly-id=\"97911\" data-asm-type=\"image\">\r\n<div class=\"md-assembly-wrapper card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-image: none 100% / 1 / 0 stretch; border-radius: inherit; overflow: hidden; border: 1px solid rgb(242, 242, 242);\" data-type=\"image\"><a class=\"gtm-assembly-link position-relative d-flex align-items-center justify-content-center media-overlay-link card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); overflow: hidden; display: flex; position: relative; align-items: center; justify-content: center; border-radius: inherit; cursor: pointer;\" href=\"https://cdn.britannica.com/70/92770-050-1648428C/Lioness.jpg\" data-href=\"/media/1/342664/97911\"><picture style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 258px;\"><source style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\" srcset=\"https://cdn.britannica.com/70/92770-050-1648428C/Lioness.jpg?w=300\" media=\"(min-width: 680px)\"><img style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: block; vertical-align: middle; max-width: 100%; height: auto; max-height: 100%; width: 258px;\" src=\"https://cdn.britannica.com/70/92770-050-1648428C/Lioness.jpg?w=300\" alt=\"lion\" loading=\"eager\" data-width=\"1040\" data-height=\"1600\"></picture>\r\n<div class=\"position-absolute top-10 left-10 assembly-slide-tag rounded-lg\" style=\"box-sizing: border-box; margin: 0px; padding: 5px 8px; border: 0px solid; position: absolute; top: 10px; left: 10px; border-radius: 8px; background-color: rgb(14, 63, 112); color: rgb(255, 255, 255); font-size: 12px; font-weight: bold;\">2 of 2</div>\r\n</a></div>\r\n<figcaption class=\"card-body\" style=\"box-sizing: border-box; margin: 0px; padding: 15px 0px 0px; border: 0px solid; font-size: 16px;\">\r\n<div class=\"md-assembly-caption text-muted font-14 font-serif line-clamp\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; white-space: normal; color: rgb(102, 102, 102); font-family: Georgia, serif; font-size: 14px; -webkit-line-clamp: 3; position: relative;\"><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"><a class=\"gtm-assembly-link md-assembly-title font-weight-bold d-inline font-sans-serif mr-5 media-overlay-link\" style=\"box-sizing: border-box; margin: 0px 5px 0px 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); display: inline; font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-weight: bold; cursor: pointer;\" href=\"https://cdn.britannica.com/70/92770-050-1648428C/Lioness.jpg\" data-href=\"/media/1/342664/97911\">lion</a><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Lioness (<em style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Panthera leo</em>).</span></span></div>\r\n</figcaption>\r\n</figure>\r\n</div>\r\n</div>\r\n</div>\r\n</div>\r\n<p class=\"topic-paragraph\" style=\"box-sizing: border-box; margin: 0px; padding: 0px 0px 30px; border: 0px solid; font-size: 18px; line-height: 1.6; overflow-wrap: break-word; font-family: Georgia, serif;\">The lion is a well-muscled cat with a long body, large head, and short legs. Size and appearance vary considerably between the sexes. The male&rsquo;s outstanding characteristic is his&nbsp;<span id=\"ref75418\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"></span>mane, which varies between different individuals and populations. It may be entirely lacking; it may fringe the face; or it may be full and shaggy, covering the back of the head, neck, and shoulders and continuing onto the throat and chest to join a fringe along the belly. In some lions the mane and fringe are very dark, almost black, giving the cat a&nbsp;<a class=\"md-dictionary-link md-dictionary-tt-off eb\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-width: 0px 0px 2px; border-style: solid solid dotted; border-image: initial; color: rgb(20, 89, 157); text-decoration: underline; border-color: initial initial rgb(20, 89, 157) initial;\" href=\"https://www.britannica.com/dictionary/majestic\" data-term=\"majestic\" data-type=\"EB\">majestic</a>&nbsp;appearance. Manes make males look larger and may serve to intimidate rivals or impress prospective mates. A full-grown male is about 1.8&ndash;2.1 metres (6&ndash;7 feet) long, excluding the 1-metre tail; he stands about 1.2 metres high at the shoulder and weighs 170&ndash;230 kg (370&ndash;500 pounds). The female, or lioness, is smaller, with a body length of 1.5 metres, a shoulder height of 0.9&ndash;1.1 metres, and a weight of 120&ndash;180 kg. The lion&rsquo;s coat is short and varies in colour from buff yellow, orange-brown, or silvery gray to dark brown, with a tuft on the tail tip that is usually darker than the rest of the coat.</p>\r\n</section>\r\n<div class=\"md-sentinel--spy-target\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(26, 26, 26); font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">&nbsp;</div>\r\n<section id=\"ref325411\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; scroll-margin: 50px; color: rgb(26, 26, 26); font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\" data-level=\"1\" data-has-spy=\"true\">\r\n<h2 class=\"h1\" style=\"box-sizing: border-box; margin: 0px 0px 16px; padding: 0px; border: 0px solid; font-size: 28.686px; font-weight: 800; font-family: Georgia, serif; font-style: normal; line-height: 1.2;\"><span id=\"ref75419\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"></span><a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157);\" href=\"https://www.britannica.com/science/pride-animal-behavior\">Prides</a></h2>\r\n<div class=\"assemblies\" style=\"box-sizing: border-box; margin: 0px 0px 30px 30px; padding: 0px; border: 0px solid; --floated-module-margin: 0 0 30px 30px; --floated-module-width: 280px; display: flex; min-width: 280px; clear: right; float: right; max-width: 280px;\">\r\n<div class=\"w-100 assembly-container\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 280px;\">\r\n<figure class=\"md-assembly m-0 mb-md-0 card card-borderless print-false\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: none; --card-background-color: #fff; --card-border-width: 1px; --card-border-color: #f2f2f2; --card-border-color-hover: #ddd; --card-border-radius: 0.5rem; --card-box-shadow: 0 0 8px rgba(0, 0, 0, 0.08); --card-font-size: 1rem; --card-line-height: 1.2; --card-padding-x: 20px; --card-padding-y: 15px; --card-media-horizontal-min-width: 100px; background-color: rgb(255, 255, 255); border-radius: 8px; box-shadow: none; font-size: 16px; line-height: 1.2;\" data-assembly-id=\"159007\" data-asm-type=\"image\">\r\n<div class=\"md-assembly-wrapper card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-image: none 100% / 1 / 0 stretch; border-radius: inherit; overflow: hidden; border: 1px solid rgb(242, 242, 242);\" data-type=\"image\"><a class=\"gtm-assembly-link position-relative d-flex align-items-center justify-content-center media-overlay-link card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); overflow: hidden; display: flex; position: relative; align-items: center; justify-content: center; border-radius: inherit; cursor: pointer;\" href=\"https://cdn.britannica.com/05/75105-050-AE61BF35/Pride-lions.jpg\" data-href=\"/media/1/342664/159007\"><picture style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 278px;\"><source style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\" srcset=\"https://cdn.britannica.com/05/75105-050-AE61BF35/Pride-lions.jpg?w=300\" media=\"(min-width: 680px)\"><img style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: block; vertical-align: middle; max-width: 100%; height: auto; max-height: 100%; width: 278px;\" src=\"https://cdn.britannica.com/05/75105-050-AE61BF35/Pride-lions.jpg?w=300\" alt=\"pride of lions\" loading=\"eager\" data-width=\"1600\" data-height=\"926\"></picture></a></div>\r\n<figcaption class=\"card-body\" style=\"box-sizing: border-box; margin: 0px; padding: 15px 0px 0px; border: 0px solid; font-size: 16px;\">\r\n<div class=\"md-assembly-caption text-muted font-14 font-serif line-clamp\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; white-space: normal; color: rgb(102, 102, 102); font-family: Georgia, serif; font-size: 14px; -webkit-line-clamp: 3; position: relative;\"><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"><a class=\"gtm-assembly-link md-assembly-title font-weight-bold d-inline font-sans-serif mr-5 media-overlay-link\" style=\"box-sizing: border-box; margin: 0px 5px 0px 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); display: inline; font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-weight: bold; cursor: pointer;\" href=\"https://cdn.britannica.com/05/75105-050-AE61BF35/Pride-lions.jpg\" data-href=\"/media/1/342664/159007\">pride of lions</a><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Pride of lions (<em style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\">Panthera leo</em>).</span></span></div>\r\n</figcaption>\r\n</figure>\r\n</div>\r\n</div>\r\n<p class=\"topic-paragraph\" style=\"box-sizing: border-box; margin: 0px 0px 16px; padding: 0px; border: 0px solid; font-size: 18px; line-height: 1.6; overflow-wrap: break-word; font-family: Georgia, serif;\">Lions are&nbsp;<a class=\"md-dictionary-link md-dictionary-tt-off eb\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-width: 0px 0px 2px; border-style: solid solid dotted; border-image: initial; color: rgb(20, 89, 157); text-decoration: underline; border-color: initial initial rgb(20, 89, 157) initial;\" href=\"https://www.britannica.com/dictionary/unique\" data-term=\"unique\" data-type=\"EB\">unique</a>&nbsp;among cats in that they live in a group, or pride. The members of a pride typically spend the day in several scattered groups that may unite to hunt or share a meal. A pride consists of several generations of lionesses, some of which are related, a smaller number of breeding males, and their cubs. The group may consist of as few as 4 or as many as 37 members, but about 15 is the average size. Each pride has a well-defined&nbsp;<span id=\"ref75420\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"></span><a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/science/territorial-behaviour\" data-show-preview=\"true\">territory</a>&nbsp;consisting of a core area that is strictly defended against intruding lions and a fringe area where some overlap is tolerated. Where prey is abundant, a territory area may be as small as 20 square km (8 square miles), but if game is&nbsp;<a class=\"md-dictionary-link md-dictionary-tt-off eb\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-width: 0px 0px 2px; border-style: solid solid dotted; border-image: initial; color: rgb(20, 89, 157); text-decoration: underline; border-color: initial initial rgb(20, 89, 157) initial;\" href=\"https://www.britannica.com/dictionary/sparse\" data-term=\"sparse\" data-type=\"EB\">sparse</a>, it may cover up to 400 square km. Some prides have been known to use the same territory for decades, passing the area on between females. Lions proclaim their territory by roaring and by&nbsp;<span id=\"ref75421\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"></span><a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/science/scent-mark\">scent marking</a>. Their distinctive roar is generally delivered in the evening before a night&rsquo;s&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/sports/hunting-sport\" data-show-preview=\"true\">hunting</a>&nbsp;and again before getting up at dawn. Males also proclaim their presence by urinating on bushes, trees, or simply on the ground, leaving a pungent scent behind.&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/science/defecation-physiology\" data-show-preview=\"true\">Defecation</a>&nbsp;and rubbing against bushes leave different scent markings.</p>\r\n<p class=\"topic-paragraph\" style=\"box-sizing: border-box; margin: 0px; padding: 0px 0px 30px; border: 0px solid; font-size: 18px; line-height: 1.6; overflow-wrap: break-word; font-family: Georgia, serif;\">There are a number of competing evolutionary explanations for why lions form groups. Large body size and high&nbsp;<a class=\"md-dictionary-link md-dictionary-tt-off eb\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-width: 0px 0px 2px; border-style: solid solid dotted; border-image: initial; color: rgb(20, 89, 157); text-decoration: underline; border-color: initial initial rgb(20, 89, 157) initial;\" href=\"https://www.britannica.com/dictionary/density\" data-term=\"density\" data-type=\"EB\">density</a>&nbsp;of their main prey probably make group life more efficient for females in terms of energy expenditure. Groups of females, for example, hunt more effectively and are better able to defend cubs against infanticidal males and their hunting territory against other females. The relative importance of these factors is debated, and it is not clear which was responsible for the establishment of group life and which are secondary benefits.</p>\r\n</section>\r\n<div class=\"md-sentinel--spy-target\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(26, 26, 26); font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">&nbsp;</div>\r\n<section id=\"ref325412\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; scroll-margin: 50px; color: rgb(26, 26, 26); font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: start; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; background-color: rgb(255, 255, 255); text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\" data-level=\"1\" data-has-spy=\"true\">\r\n<h2 class=\"h1\" style=\"box-sizing: border-box; margin: 0px 0px 16px; padding: 0px; border: 0px solid; font-size: 28.686px; font-weight: 800; font-family: Georgia, serif; font-style: normal; line-height: 1.2;\">Hunting</h2>\r\n<div class=\"assemblies\" style=\"box-sizing: border-box; margin: 0px 0px 30px 30px; padding: 0px; border: 0px solid; --floated-module-margin: 0 0 30px 30px; --floated-module-width: 280px; display: flex; min-width: 280px; clear: right; float: right; max-width: 280px;\">\r\n<div class=\"w-100 assembly-container\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 280px;\">\r\n<figure class=\"md-assembly m-0 mb-md-0 card card-borderless print-false\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: none; --card-background-color: #fff; --card-border-width: 1px; --card-border-color: #f2f2f2; --card-border-color-hover: #ddd; --card-border-radius: 0.5rem; --card-box-shadow: 0 0 8px rgba(0, 0, 0, 0.08); --card-font-size: 1rem; --card-line-height: 1.2; --card-padding-x: 20px; --card-padding-y: 15px; --card-media-horizontal-min-width: 100px; background-color: rgb(255, 255, 255); border-radius: 8px; box-shadow: none; font-size: 16px; line-height: 1.2;\" data-assembly-id=\"159006\" data-asm-type=\"image\">\r\n<div class=\"md-assembly-wrapper card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border-image: none 100% / 1 / 0 stretch; border-radius: inherit; overflow: hidden; border: 1px solid rgb(242, 242, 242);\" data-type=\"image\"><a class=\"gtm-assembly-link position-relative d-flex align-items-center justify-content-center media-overlay-link card-media\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); overflow: hidden; display: flex; position: relative; align-items: center; justify-content: center; border-radius: inherit; cursor: pointer;\" href=\"https://cdn.britannica.com/97/92697-050-39C05D91/Lions-warthog.jpg\" data-href=\"/media/1/342664/159006\"><picture style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; width: 278px;\"><source style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\" srcset=\"https://cdn.britannica.com/97/92697-050-39C05D91/Lions-warthog.jpg?w=300\" media=\"(min-width: 680px)\"><img style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: block; vertical-align: middle; max-width: 100%; height: auto; max-height: 100%; width: 278px;\" src=\"https://cdn.britannica.com/97/92697-050-39C05D91/Lions-warthog.jpg?w=300\" alt=\"lions chasing a warthog\" loading=\"eager\" data-width=\"1600\" data-height=\"1064\"></picture></a></div>\r\n<figcaption class=\"card-body\" style=\"box-sizing: border-box; margin: 0px; padding: 15px 0px 0px; border: 0px solid; font-size: 16px;\">\r\n<div class=\"md-assembly-caption text-muted font-14 font-serif line-clamp\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; white-space: normal; color: rgb(102, 102, 102); font-family: Georgia, serif; font-size: 14px; -webkit-line-clamp: 3; position: relative;\"><span style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"><a class=\"gtm-assembly-link md-assembly-title font-weight-bold d-inline font-sans-serif mr-5 media-overlay-link\" style=\"box-sizing: border-box; margin: 0px 5px 0px 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: rgb(20, 89, 157); display: inline; font-family: -apple-system, BlinkMacSystemFont, \'Helvetica Neue\', \'Segoe UI\', Roboto, Arial, sans-serif; font-weight: bold; cursor: pointer;\" href=\"https://cdn.britannica.com/97/92697-050-39C05D91/Lions-warthog.jpg\" data-href=\"/media/1/342664/159006\">lions chasing a warthog</a></span></div>\r\n</figcaption>\r\n</figure>\r\n</div>\r\n</div>\r\n<p class=\"topic-paragraph\" style=\"box-sizing: border-box; margin: 0px 0px 16px; padding: 0px; border: 0px solid; font-size: 18px; line-height: 1.6; overflow-wrap: break-word; font-family: Georgia, serif;\">Lions&nbsp;<span id=\"ref75422\" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid;\"></span><a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/science/predation\" data-show-preview=\"true\">prey</a>&nbsp;on a large variety of animals ranging in size from&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/rodent\" data-show-preview=\"true\">rodents</a>&nbsp;and&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/baboon\" data-show-preview=\"true\">baboon</a>s to&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/Cape-buffalo\" data-show-preview=\"true\">Cape (or African) buffalo</a>&nbsp;and&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/hippopotamus-mammal-species\" data-show-preview=\"true\">hippopotamuses</a>, but they predominantly hunt medium- to large-sized hoofed animals such as wildebeests,&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/zebra\" data-show-preview=\"true\">zebras</a>, and&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/antelope-mammal\" data-show-preview=\"true\">antelopes</a>. Prey preferences vary geographically as well as between neighbouring prides. Lions are known to take&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/elephant-mammal\" data-show-preview=\"true\">elephants</a>&nbsp;and&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/giraffe\" data-show-preview=\"true\">giraffes</a>, but only if the individual is young or especially sick. They readily eat any meat they can find, including carrion and fresh kills that they scavenge or forcefully steal from&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/hyena\" data-show-preview=\"true\">hyenas</a>,&nbsp;<a class=\"md-crosslink \" style=\"box-sizing: border-box; margin: 0px; padding: 0px; border: 0px solid; color: rgb(20, 89, 157); text-decoration: underline;\" href=\"https://www.britannica.com/animal/cheetah-mammal\" data-show-preview=\"true\">cheetahs</a>, or wild dogs. Lionesses living in open savanna do most of the hunting, whereas males typically appropriate their meals from the female&rsquo;s kills. However, male lions are also adept hunters, and in some areas they hunt frequently. Pride males in scrub or wooded habitat spend less time with the females and hunt most of their own meals. Nomadic males must always secure their own food.</p>\r\n</section>', '{\"bg_color\":\"#ffffff\"}', '2025-09-29 09:23:06', '2025-09-29 09:23:06'),
(24, 5, 'Adverb', '<ul style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 1rem; color: rgb(32, 33, 36); font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 16px; font-style: normal; font-variant-ligatures: normal; font-variant-caps: normal; font-weight: 400; letter-spacing: normal; orphans: 2; text-align: left; text-indent: 0px; text-transform: none; widows: 2; word-spacing: 0px; -webkit-text-stroke-width: 0px; white-space: normal; text-decoration-thickness: initial; text-decoration-style: initial; text-decoration-color: initial;\">\r\n<li style=\"box-sizing: border-box; font-size: 18pt;\"><span style=\"box-sizing: border-box; font-size: 18pt;\">An&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">adverb</strong>&nbsp;is a word that modifies or describes a verb (&ldquo;he sings&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">loudly</strong>&rdquo;), an adjective (&ldquo;<strong style=\"box-sizing: border-box; font-weight: bold;\">very</strong>&nbsp;tall&rdquo;), another adverb (&ldquo;ended&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">too</strong>&nbsp;quickly&rdquo;), or even a whole sentence (&ldquo;<strong style=\"box-sizing: border-box; font-weight: bold;\">Fortunately</strong>, I had brought an umbrella.&rdquo;).</span></li>\r\n<li style=\"box-sizing: border-box; font-size: 18pt;\"><span style=\"box-sizing: border-box; font-size: 18pt;\">Adverbs provide additional context, such as how, when, where, to what extent, or how often something happens.</span></li>\r\n<li style=\"box-sizing: border-box; font-size: 18pt;\"><span style=\"box-sizing: border-box; font-size: 18pt;\">Adverbs are categorized into several types based on their function and what they describe:&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">time</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">frequency</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">duration</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">manner</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">place</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">degree</strong>,&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">purpose</strong>, and&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">conjunctive</strong>&nbsp;<strong style=\"box-sizing: border-box; font-weight: bold;\">adverbs</strong>.</span></li>\r\n<li style=\"box-sizing: border-box; font-size: 18pt;\"><span style=\"box-sizing: border-box; font-size: 18pt;\">Adverbs often end in&nbsp;<em style=\"box-sizing: border-box;\">-ly</em>, but some (such as&nbsp;<em style=\"box-sizing: border-box;\">fast</em>) look the same as their adjective counterparts.</span></li>\r\n<li style=\"box-sizing: border-box; font-size: 18pt;\"><span style=\"box-sizing: border-box; font-size: 18pt;\">Adverbs can show comparison (&ldquo;<strong style=\"box-sizing: border-box; font-weight: bold;\">more quickly</strong>,&rdquo; &ldquo;<strong style=\"box-sizing: border-box; font-weight: bold;\">most quickly</strong>&rdquo;) and should be placed near the words they modify to avoid ambiguity.</span></li>\r\n</ul>', '{\"bg_color\":\"#ffffff\"}', '2025-09-29 17:17:47', '2025-09-29 17:17:47');

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
(32, 5, 26, 'mcq', 20, 'C', 0, 0.00, '2025-09-29 13:42:36', NULL, NULL, NULL),
(33, 5, 27, 'matching', 7, '[\"Verb\",\"Adjective\",\"Adverb of time\",\"Noun\",\"Adverb\"]', 1, 5.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(34, 5, 27, 'matching', 8, '[\"Present Continuous Tense\",\"Simple Future Tense\",\"Simple Past Tense\",\"Simple Present Tense\"]', 1, 4.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(35, 5, 27, 'mcq', 21, 'C', 1, 1.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(36, 5, 27, 'mcq', 22, 'B', 1, 1.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(37, 5, 27, 'mcq', 23, 'B', 1, 1.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(38, 5, 27, 'mcq', 24, 'A', 0, 0.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(39, 5, 27, 'mcq', 25, 'A', 0, 0.00, '2025-09-29 14:37:02', NULL, NULL, NULL),
(40, 5, 28, 'matching', 9, '[\"Cherry\",\"Banana\",\"Blueberry\"]', 1, 3.00, '2025-09-29 14:44:18', NULL, NULL, NULL),
(41, 6, 29, 'mcq', 26, 'B', 1, 1.00, '2025-09-29 17:02:06', NULL, NULL, NULL),
(42, 5, 30, 'matching', 10, '[\"Verb\",\"Adjective\"]', 1, 2.00, '2025-09-29 18:20:41', NULL, NULL, NULL),
(43, 5, 30, 'mcq', 27, 'C', 1, 1.00, '2025-09-29 18:20:41', NULL, NULL, NULL),
(44, 5, 30, 'mcq', 28, 'B', 1, 1.00, '2025-09-29 18:20:41', NULL, NULL, NULL),
(45, 5, 30, 'mcq', 29, 'B', 1, 1.00, '2025-09-29 18:20:41', NULL, NULL, NULL),
(46, 5, 31, 'matching', 11, '[\"Cherry\",\"Banana\",\"Blueberry\"]', 1, 2.00, '2025-09-29 18:28:10', NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `matching_questions`
--
ALTER TABLE `matching_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `mcq_questions`
--
ALTER TABLE `mcq_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

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
