-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 07:34 PM
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
(1, 'admin', 'admin@comprelearn.com', '$2y$10$v5Atb/mLopapFKUcR9dDT.ZvdRtblvZF4b..9soIskFrTQtz/aKi6', 'System Administrator', 1, '2025-09-15 17:10:30', '2025-09-08 04:21:06', '2025-09-15 17:10:30');

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

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `teacher_id`, `title`, `content`, `created_at`) VALUES
(1, 2, 'Assessment – Reminder', 'Hello Class! 👋\r\nJust a reminder that our Midterm Assessment will be on September 20, 2025 (Friday) at 10:00 AM. Please review the uploaded materials and don’t forget to bring your calculators.', '2025-09-14 16:57:09');

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

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `teacher_id`, `title`, `description`, `theme_settings`, `related_material_id`, `created_at`, `updated_at`) VALUES
(8, 2, 'Assessment1', 'asdasd', NULL, NULL, '2025-09-14 08:37:08', '2025-09-14 08:37:08'),
(9, 2, 'Assessment2', 'asdasd', NULL, NULL, '2025-09-14 08:38:30', '2025-09-14 08:38:30'),
(10, 2, 'fdsfdas', 'dadfds', NULL, NULL, '2025-09-14 08:47:45', '2025-09-14 08:47:45'),
(11, 2, 'ddad', 'dsadasd', NULL, NULL, '2025-09-14 08:50:13', '2025-09-14 08:50:13'),
(12, 2, 'Demo Assessment - Questions from Bank', 'This assessment demonstrates using questions from the question bank', NULL, NULL, '2025-09-14 09:16:12', '2025-09-14 09:16:12'),
(13, 2, 'Demo Assessment - Questions from Bank', 'This assessment demonstrates using questions from the question bank', NULL, NULL, '2025-09-14 09:16:33', '2025-09-14 09:16:33'),
(14, 2, 'dsqwewqe21321', 'ewqwqwqwqwqwqwqwqwqwqwqasd', NULL, NULL, '2025-09-15 03:05:23', '2025-09-15 03:05:23');

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

--
-- Dumping data for table `assessment_assignments`
--

INSERT INTO `assessment_assignments` (`id`, `assessment_id`, `section_id`, `student_id`, `assigned_at`) VALUES
(2, 11, 4, NULL, '2025-09-14 09:01:08'),
(3, 13, 4, NULL, '2025-09-14 09:16:33'),
(4, 14, 4, NULL, '2025-09-15 03:05:23');

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

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_type`, `question_text`, `options`, `answer`, `question_bank_id`, `created_at`) VALUES
(3, 8, 'multiple_choice', 'dsdsad', '{\"A\":\"a\",\"B\":\"s\",\"C\":\"d\",\"D\":\"f\"}', 'D', NULL, '2025-09-14 08:37:08'),
(4, 9, 'multiple_choice', 'dsadasd', '{\"A\":\"1\",\"B\":\"23\",\"C\":\"3\",\"D\":\"4\"}', 'B', NULL, '2025-09-14 08:38:30'),
(5, 10, 'multiple_choice', 'fdsfds', '{\"A\":\"1\",\"B\":\"23\",\"C\":\"45\",\"D\":\"56\"}', 'A', NULL, '2025-09-14 08:47:45'),
(6, 11, 'multiple_choice', 'dsadsa', '{\"A\":\"a\",\"B\":\"sd\",\"C\":\"df\",\"D\":\"fg\"}', 'A', NULL, '2025-09-14 08:50:13'),
(7, 12, 'multiple_choice', 'caxfcq', '{\"A\":\"qw\",\"B\":\"er\",\"C\":\"rt\",\"D\":\"ty\"}', 'C', NULL, '2025-09-14 09:16:12'),
(8, 12, 'multiple_choice', 'ytudfs', '{\"A\":\"gh\",\"B\":\"jk\",\"C\":\"nm\",\"D\":\"as\"}', 'C', NULL, '2025-09-14 09:16:12'),
(9, 12, 'matching', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"qewwqe\",\"wqeewq\",\"vsfds\"],\"rights\":[\"bvvd\",\"wewq\",\"phgdf\"]}', '{\"qewwqe\":\"wewq\",\"wqeewq\":\"phgdf\",\"vsfds\":\"bvvd\"}', NULL, '2025-09-14 09:16:12'),
(10, 13, 'multiple_choice', 'caxfcq', '{\"A\":\"qw\",\"B\":\"er\",\"C\":\"rt\",\"D\":\"ty\"}', 'C', NULL, '2025-09-14 09:16:33'),
(11, 13, 'multiple_choice', 'ytudfs', '{\"A\":\"gh\",\"B\":\"jk\",\"C\":\"nm\",\"D\":\"as\"}', 'C', NULL, '2025-09-14 09:16:33'),
(12, 13, 'matching', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"qewwqe\",\"wqeewq\",\"vsfds\"],\"rights\":[\"bvvd\",\"wewq\",\"phgdf\"]}', '{\"qewwqe\":\"wewq\",\"wqeewq\":\"phgdf\",\"vsfds\":\"bvvd\"}', NULL, '2025-09-14 09:16:33'),
(13, 14, 'multiple_choice', 'qweeqwewqew', '{\"A\":\"1\",\"B\":\"2\",\"C\":\"3\",\"D\":\"5\"}', 'D', NULL, '2025-09-15 03:05:23');

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

--
-- Dumping data for table `assessment_responses`
--

INSERT INTO `assessment_responses` (`id`, `assessment_id`, `question_id`, `student_id`, `answer`, `submitted_at`) VALUES
(1, 11, 6, 1, 'A', '2025-09-14 09:08:12');

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

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `name`, `username`, `email`, `student_id`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Rubelyn B. Martin', 'parent_1', 'rubelyn@gmail.com', 1, '$2y$10$3vUa9MCpfFEhoqJLNlXEHuEOzon30QirTFCEGh56FV59iz5/G213e', '2025-09-03 13:24:42', '2025-09-03 13:24:42'),
(2, 'Rowen Q. Martin', 'parent_2', 'owen@gmail.com', 2, '$2y$10$/Bgek4Hv8Gr9OEsRMlxx0e4AUVBMHTDsyFl8q3.MpuDK7kPr6D57G', '2025-09-03 13:27:57', '2025-09-03 13:27:57'),
(6, 'Cecil M Floress', '', 'cecil@gmail.com', 6, '$2y$10$hJpXsLcB7gjpUWONRKyNVe0KYYyxdFzg43RqFvGY57xhPEZOhSitu', '2025-09-08 05:10:30', '2025-09-10 17:12:53'),
(7, 'Grace E Doe', 'parent_8', 'grace@gmail.com', 8, '$2y$10$tzJaKmdB/g4XEOJL6wOFDO1i.wjtCqV2mN7NK2NeWhZzUovrjJBmC', '2025-09-15 16:51:06', '2025-09-15 16:51:06');

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
(16, 'martindarren3561@gmail.com', 'student', 'dd48056c5310055a428c8d6dcd7fce5ee22b1f6d71c85484d92368b8cc52866a', '2025-07-18 15:06:55', 0, '2025-07-18 04:06:55', '040364'),
(17, 'martindarren3561@gmail.com', 'student', '0364a3a3808ac258569312653baf6d748c51c10264063fdc24e9065e2c025c8c', '2025-07-18 15:09:17', 0, '2025-07-18 04:09:17', '734575'),
(18, 'martindarren3561@gmail.com', 'student', '7553a42f6b2021fd68beb631236d64602fb1f0136f1a3e5ee50a9676544fb914', '2025-07-18 15:11:15', 0, '2025-07-18 04:11:15', '281463'),
(19, 'martindarren3561@gmail.com', 'student', 'ef32a9ee31a910d8bb024e5260b3b3023e9c41e05d61cc308a1eb78aa67dc028', '2025-07-18 15:15:28', 0, '2025-07-18 04:15:28', '211788'),
(20, 'martindarren3561@gmail.com', 'student', '2b9be62ccc8bc02cf96e941b6c5f88a5d5deee5a61a8900e5b9766c147c09364', '2025-09-15 20:03:27', 0, '2025-09-15 17:03:27', '188803'),
(21, 'martindarren3561@gmail.com', 'student', 'e88a1a24f1597ff1de9b7fa6ee39fd750a05e2135cb4d4b236748cc8e103663a', '2025-09-15 20:05:59', 0, '2025-09-15 17:05:59', '695870'),
(22, 'martindarren3561@gmail.com', 'student', '3a09f7a5eb0d6a63c87c28a592d702629b6080415b7f81094dd388a169ea3577', '2025-09-15 20:07:47', 0, '2025-09-15 17:07:47', '617847'),
(23, 'martindarren410@gmail.com', 'student', '33bc48036f22ab97197e5b07074d207169860ed48a0004a660f60550801941d8', '2025-09-15 20:11:00', 0, '2025-09-15 17:11:00', '217938'),
(24, 'martindarren410@gmail.com', 'student', 'ee9f54e0a92e3664e3770e49dc8d4dc7373b629ad997dea73a779e5186949d29', '2025-09-15 20:15:35', 0, '2025-09-15 17:15:35', '033401');

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
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress'
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
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_bank`
--

INSERT INTO `question_bank` (`id`, `teacher_id`, `section_id`, `set_title`, `set_id`, `question_type`, `question_category`, `question_text`, `options_json`, `options`, `answer`, `created_at`) VALUES
(40, 2, 4, 'Quiz5', NULL, 'multiple_choice', 'comprehension', 'caxfcq', '{\"A\":\"qw\",\"B\":\"er\",\"C\":\"rt\",\"D\":\"ty\"}', NULL, 'C', '2025-09-11 14:38:42'),
(41, 2, 4, 'Quiz5', NULL, 'multiple_choice', 'comprehension', 'ytudfs', '{\"A\":\"gh\",\"B\":\"jk\",\"C\":\"nm\",\"D\":\"as\"}', NULL, 'C', '2025-09-11 14:38:42'),
(43, 2, 4, 'Quiz5', NULL, 'matching', 'comprehension', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"qewwqe\",\"wqeewq\",\"vsfds\"],\"rights\":[\"bvvd\",\"wewq\",\"phgdf\"]}', NULL, '{\"qewwqe\":\"wewq\",\"wqeewq\":\"phgdf\",\"vsfds\":\"bvvd\"}', '2025-09-11 14:38:42'),
(44, 2, 4, 'Quiz5', NULL, 'essay', 'comprehension', 'dsadasdsadsad', '{\"word_limit\":200,\"rubrics\":\"dsdswqewqe\"}', NULL, '', '2025-09-11 14:38:42'),
(45, 2, 4, 'Quiz6', NULL, 'multiple_choice', 'comprehension', 'dsadas', '{\"A\":\"1\",\"B\":\"2\",\"C\":\"3\",\"D\":\"4\"}', NULL, 'A', '2025-09-15 12:54:29'),
(47, 2, 4, 'Quiz6', NULL, 'matching', 'comprehension', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"sdsd\",\"dsad\",\"dsad\"],\"rights\":[\"1\",\"2\",\"3\"]}', NULL, '{\"sdsd\":\"3\",\"dsad\":\"1\"}', '2025-09-15 12:54:29'),
(48, 2, 4, 'Quiz6', NULL, 'essay', 'comprehension', 'dsadsad', '{\"word_limit\":100,\"rubrics\":\"3213213\"}', NULL, '', '2025-09-15 12:54:29'),
(49, 2, 4, 'Quiz7', NULL, 'matching', 'comprehension', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"32132\",\"3232\"],\"rights\":[\"3213\",\"4324\"]}', NULL, '{\"32132\":\"3213\",\"3232\":\"4324\"}', '2025-09-15 13:05:35'),
(51, 2, 4, 'dasdas', NULL, 'multiple_choice', 'comprehension', 'ewqewq', '{\"A\":\"1\",\"B\":\"2\",\"C\":\"3\",\"D\":\"4\"}', NULL, 'A', '2025-09-15 16:47:33'),
(52, 2, 4, 'dasdas', NULL, 'matching', 'comprehension', 'Match the items in Column A with the corresponding items in Column B.', '{\"lefts\":[\"21321\",\"44453\"],\"rights\":[\"ewqe\",\"asddas\"]}', NULL, '{\"21321\":\"asddas\",\"44453\":\"ewqe\"}', '2025-09-15 16:47:33'),
(53, 2, 4, 'dasdas', NULL, 'essay', 'comprehension', 'ewqewq', '{\"word_limit\":50,\"rubrics\":\"wqeqewq\"}', NULL, '', '2025-09-15 16:47:33'),
(54, 2, 5, 'wewqe', NULL, 'multiple_choice', 'comprehension', 'ewqewqe', '{\"A\":\"1\",\"B\":\"2\",\"C\":\"3\",\"D\":\"4\"}', NULL, 'A', '2025-09-15 16:48:53');

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
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `partial_score` int(11) DEFAULT 0,
  `total_matches` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_responses`
--

INSERT INTO `quiz_responses` (`id`, `student_id`, `question_id`, `set_title`, `section_id`, `teacher_id`, `student_answer`, `is_correct`, `partial_score`, `total_matches`, `submitted_at`) VALUES
(1, 1, 40, 'Quiz5', 4, 2, '0', 1, 0, 0, '2025-09-11 15:03:05'),
(2, 1, 41, 'Quiz5', 4, 2, '0', 1, 0, 0, '2025-09-11 15:03:05'),
(4, 1, 43, 'Quiz5', 4, 2, '0', 0, 1, 3, '2025-09-11 15:03:05'),
(13, 1, 44, 'Quiz5', 4, 2, 'This is a direct test essay answer', 0, 8, 0, '2025-09-15 16:59:45'),
(14, 1, 45, 'Quiz6', 4, 2, 'A', 1, 0, 0, '2025-09-15 13:06:41'),
(16, 1, 47, 'Quiz6', 4, 2, '{\"sdsd\":\"1\",\"dsad\":\"3\"}', 0, 0, 2, '2025-09-15 13:06:41'),
(17, 1, 48, 'Quiz6', 4, 2, 'dasfadfdasf', 0, 8, 0, '2025-09-15 16:59:31'),
(18, 1, 49, 'Quiz7', 4, 2, '{\"3232\":\"3213\",\"32132\":\"4324\"}', 0, 0, 2, '2025-09-15 16:19:08'),
(19, 8, 54, 'wewqe', 5, 2, 'A', 1, 0, 0, '2025-09-15 16:52:13');

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

--
-- Dumping data for table `quiz_scores`
--

INSERT INTO `quiz_scores` (`id`, `student_id`, `set_title`, `section_id`, `teacher_id`, `score`, `total_points`, `total_questions`, `correct_answers`, `submitted_at`) VALUES
(3, 1, 'Quiz5', 4, 2, 73.33, 15, 4, 11, '2025-09-11 15:03:05'),
(4, 1, 'Quiz6', 4, 2, 130.77, 13, 4, 17, '2025-09-15 13:06:41'),
(5, 1, 'Quiz7', 4, 2, 0.00, 2, 1, 0, '2025-09-15 16:19:08'),
(6, 8, 'wewqe', 5, 2, 100.00, 1, 1, 1, '2025-09-15 16:52:13');

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
(4, 2, 'Newton’s Laws of Motion', '<p><span style=\"font-family: Arial;\">Sir Isaac Newton’s laws of motion explain the relationship between a physical object and the forces acting upon it. Understanding this information provides us with the basis of modern physics.</span></p><h2 id=\"newtons-first-law-inertia\" style=\"box-sizing: inherit; clear: none; -webkit-font-smoothing: antialiased; margin-bottom: 0.5em; margin-top: 1.5em;\"><span style=\"font-family: Arial;\">Newton’s First Law: Inertia</span></h2><h4 style=\"box-sizing: inherit; clear: none; -webkit-font-smoothing: antialiased; margin-bottom: 0.5em; margin-top: 1.5em;\"><span style=\"font-family: Arial;\">An object at rest remains at rest, and an object in motion remains in motion at constant speed and in a straight line unless acted on by an unbalanced force.</span></h4><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\">Newton’s first law states that every object will remain at rest or in uniform motion in a straight line unless compelled to change its state by the action of an external force. This tendency to resist changes in a state of motion is&nbsp;</span><span style=\"box-sizing: inherit; font-family: Arial;\">inertia</span><span style=\"font-family: Arial;\">. If all the external forces cancel each other out, then there is no net force acting on the object.&nbsp; If there is no net force acting on the object, then the object will maintain a constant velocity.</span></p><h3 style=\"box-sizing: inherit; clear: none; -webkit-font-smoothing: antialiased; margin-bottom: 0.5em; margin-top: 1.5em;\"><span style=\"font-family: Arial;\">Examples of inertia involving aerodynamics:</span></h3><ul style=\"box-sizing: inherit; margin-top: 1em; margin-bottom: 1em; padding-left: 1.94em; max-width: 66ch; line-height: 1.7;\"><li style=\"box-sizing: inherit; line-height: 1.5; margin-bottom: 0.5em;\"><span style=\"font-family: Arial;\">The motion of an airplane when a pilot changes the throttle setting of an engine.</span></li><li style=\"box-sizing: inherit; line-height: 1.5; margin-bottom: 0.5em;\"><span style=\"font-family: Arial;\">The motion of a ball falling down through the atmosphere.</span></li><li style=\"box-sizing: inherit; line-height: 1.5; margin-bottom: 0.5em;\"><span style=\"font-family: Arial;\">A model rocket being launched up into the atmosphere.</span></li><li style=\"box-sizing: inherit; line-height: 1.5; margin-bottom: 0px;\"><span style=\"font-family: Arial;\">The motion of a kite when the wind changes.</span></li></ul><h2 id=\"newtons-second-law-force\" style=\"box-sizing: inherit; clear: none; -webkit-font-smoothing: antialiased; margin-bottom: 0.5em; margin-top: 1.5em;\"><span style=\"font-family: Arial;\">Newton’s Second Law: Force</span></h2><h4 style=\"box-sizing: inherit; clear: none; -webkit-font-smoothing: antialiased; margin-bottom: 0.5em; margin-top: 1.5em;\"><span style=\"font-family: Arial;\">The acceleration of an object depends on the mass of the object and the amount of force applied.</span></h4><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\">His second law defines a&nbsp;</span><font color=\"#212121\" face=\"Merriweather, Georgia, Cambria, Times New Roman, Times, serif\" style=\"\"><span style=\"font-size: 1.5rem; box-sizing: inherit; font-family: Arial;\">force</span><span style=\"font-family: Arial;\">&nbsp;to be equal to change in&nbsp;</span><span style=\"font-size: 1.5rem; box-sizing: inherit; font-family: Arial;\">momentum</span><span style=\"font-family: Arial;\">&nbsp;(mass times velocity) per change in time.&nbsp;Momentum is defined to be the mass&nbsp;</span><span style=\"font-size: 1.5rem; box-sizing: inherit; font-family: Arial;\">m</span><span style=\"font-family: Arial;\">&nbsp;of an object times its&nbsp;velocity&nbsp;</span><span style=\"font-size: 1.5rem; box-sizing: inherit; font-family: Arial;\">V</span></font><span style=\"font-family: Arial;\">.</span></p><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\"><br></span></p><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\"><br></span></p><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\"><br></span></p><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\"><br></span></p><p style=\"box-sizing: inherit; line-height: 1.7; margin-bottom: 1em; margin-top: 1em; max-width: 66ch;\"><span style=\"font-family: Arial;\"><br></span></p>', '{\"bg_color\":\"#ffffff\"}', '2025-09-10 17:11:39', '2025-09-10 17:11:39'),
(5, 2, 'Panda', '<p style=\"text-align: justify; margin: 0.5em 0px 1em; color: rgb(32, 33, 34); font-family: sans-serif;\">The&nbsp;<b>giant panda</b>&nbsp;(<i><b>Ailuropoda melanoleuca</b></i>), also known as the&nbsp;<b>panda bear</b>&nbsp;or simply&nbsp;<b>panda</b>, is a&nbsp;<a href=\"https://en.wikipedia.org/wiki/Bear\" title=\"Bear\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">bear</a>&nbsp;species&nbsp;<a href=\"https://en.wikipedia.org/wiki/Endemic\" class=\"mw-redirect\" title=\"Endemic\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">endemic</a>&nbsp;to&nbsp;<a href=\"https://en.wikipedia.org/wiki/China\" title=\"China\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">China</a>. It is characterised by its white&nbsp;<a href=\"https://en.wikipedia.org/wiki/Animal_coat\" title=\"Animal coat\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">coat</a>&nbsp;with black patches around the eyes, ears, legs and shoulders. Its body is rotund; adult individuals weigh 100 to 115&nbsp;kg (220 to 254&nbsp;lb) and are typically 1.2 to 1.9&nbsp;m (3&nbsp;ft 11&nbsp;in to 6&nbsp;ft 3&nbsp;in) long. It is&nbsp;<a href=\"https://en.wikipedia.org/wiki/Sexually_dimorphic\" class=\"mw-redirect\" title=\"Sexually dimorphic\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">sexually dimorphic</a>, with males being typically 10 to 20% larger than females. A&nbsp;<a href=\"https://en.wikipedia.org/wiki/Thumb\" title=\"Thumb\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">thumb</a>&nbsp;is visible on its forepaw, which helps in holding&nbsp;<a href=\"https://en.wikipedia.org/wiki/Bamboo\" title=\"Bamboo\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">bamboo</a>&nbsp;in place for feeding. It has large&nbsp;<a href=\"https://en.wikipedia.org/wiki/Molar_teeth\" class=\"mw-redirect\" title=\"Molar teeth\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">molar teeth</a>&nbsp;and expanded&nbsp;<a href=\"https://en.wikipedia.org/wiki/Temporal_fossa\" title=\"Temporal fossa\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">temporal fossa</a>&nbsp;to meet its dietary requirements. It can digest&nbsp;<a href=\"https://en.wikipedia.org/wiki/Starch\" title=\"Starch\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">starch</a>&nbsp;and is mostly&nbsp;<a href=\"https://en.wikipedia.org/wiki/Herbivorous\" class=\"mw-redirect\" title=\"Herbivorous\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">herbivorous</a>&nbsp;with a diet consisting almost entirely of bamboo and&nbsp;<a href=\"https://en.wikipedia.org/wiki/Bamboo_shoot\" title=\"Bamboo shoot\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">bamboo shoots</a>.</p><p style=\"text-align: justify; margin: 0.5em 0px 1em; color: rgb(32, 33, 34); font-family: sans-serif;\">The giant panda lives exclusively in six montane regions in a few Chinese provinces at elevations of up to 3,000&nbsp;m (9,800&nbsp;ft). It is solitary and gathers only in mating seasons. It relies on&nbsp;<a href=\"https://en.wikipedia.org/wiki/Olfactory_communication\" class=\"mw-redirect\" title=\"Olfactory communication\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">olfactory communication</a>&nbsp;to communicate and uses&nbsp;<a href=\"https://en.wikipedia.org/wiki/Scent_mark\" class=\"mw-redirect\" title=\"Scent mark\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">scent marks</a>&nbsp;as chemical cues and on landmarks like rocks or trees. Females rear cubs for an average of 18 to 24 months. The oldest known giant panda was 38 years old.</p><p style=\"text-align: justify; margin: 0.5em 0px 1em; color: rgb(32, 33, 34); font-family: sans-serif;\">As a result of farming,&nbsp;<a href=\"https://en.wikipedia.org/wiki/Deforestation\" title=\"Deforestation\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">deforestation</a>&nbsp;and infrastructural development, the giant panda has been driven out of the lowland areas where it once lived. The Fourth National Survey (2011–2014), published in 2015, estimated that the wild population of giant pandas aged over 1.5 years (i.e. excluding dependent young) had increased to 1,864 individuals; based on this number, and using the available estimated percentage of cubs in the population (9.6%), the&nbsp;<a href=\"https://en.wikipedia.org/wiki/IUCN\" class=\"mw-redirect\" title=\"IUCN\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">IUCN</a>&nbsp;estimated the total number of Pandas to be approximately 2,060.<sup id=\"cite_ref-iucn_1-2\" class=\"reference\" style=\"line-height: 1; unicode-bidi: isolate; text-wrap-mode: nowrap; font-size: 12.8px;\"><a href=\"https://en.wikipedia.org/wiki/Giant_panda#cite_note-iucn-1\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\"><span class=\"cite-bracket\" style=\"pointer-events: none;\">[</span>1<span class=\"cite-bracket\" style=\"pointer-events: none;\">]</span></a></sup><sup id=\"cite_ref-Swaisgood-2018_3-0\" class=\"reference\" style=\"line-height: 1; unicode-bidi: isolate; text-wrap-mode: nowrap; font-size: 12.8px;\"><a href=\"https://en.wikipedia.org/wiki/Giant_panda#cite_note-Swaisgood-2018-3\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\"><span class=\"cite-bracket\" style=\"pointer-events: none;\">[</span>3<span class=\"cite-bracket\" style=\"pointer-events: none;\">]</span></a></sup>&nbsp;Since 2016, it has been listed as&nbsp;<a href=\"https://en.wikipedia.org/wiki/Vulnerable_species\" title=\"Vulnerable species\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">Vulnerable</a>&nbsp;on the&nbsp;<a href=\"https://en.wikipedia.org/wiki/IUCN_Red_List\" title=\"IUCN Red List\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">IUCN Red List</a>. In July 2021, Chinese authorities also classified the giant panda as vulnerable. It is a&nbsp;<a href=\"https://en.wikipedia.org/wiki/Conservation-reliant_species\" title=\"\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">conservation-reliant species</a>. By 2007, the captive population comprised 239 giant pandas in China and another 27 outside the country. It has often served as China\'s&nbsp;<a href=\"https://en.wikipedia.org/wiki/National_symbol\" title=\"National symbol\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">national symbol</a>, appeared on&nbsp;<a href=\"https://en.wikipedia.org/wiki/Chinese_Gold_Panda\" title=\"Chinese Gold Panda\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">Chinese Gold Panda</a>&nbsp;coins since 1982 and as one of the five&nbsp;<a href=\"https://en.wikipedia.org/wiki/Fuwa\" title=\"Fuwa\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">Fuwa</a>&nbsp;mascots of the&nbsp;<a href=\"https://en.wikipedia.org/wiki/2008_Summer_Olympics\" title=\"2008 Summer Olympics\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">2008 Summer Olympics</a>&nbsp;held in&nbsp;<a href=\"https://en.wikipedia.org/wiki/Beijing\" title=\"Beijing\" style=\"color: rgb(51, 102, 204); background-image: none; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; border-radius: 2px; overflow-wrap: break-word;\">Beijing</a>.</p>', '{\"bg_color\":\"#ffffff\"}', '2025-09-14 16:46:03', '2025-09-14 16:46:03');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `created_at`) VALUES
(1, 'Rizal', '2025-09-05 08:34:33'),
(2, 'Bonifacio', '2025-09-08 03:29:21'),
(4, 'Mabini', '2025-09-08 04:52:06'),
(5, 'Aguinaldo', '2025-09-15 16:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
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

INSERT INTO `students` (`id`, `name`, `student_number`, `email`, `gender`, `password`, `section_id`, `created_at`, `updated_at`) VALUES
(1, 'Darren B. Martin', '2203561', 'martindarren410@gmail.com', 'male', '$2y$10$Ni6UIsG9Gl4H41YupamXDOF5NG6bWal7dg8pxC/RAT6KMtfVhQPDq', 4, '2025-07-18 00:30:31', '2025-09-15 17:10:41'),
(2, 'Darwin B. Martin', '2204561', 'darwin@gmail.com', 'male', '$2y$10$8Yo7rHy/0SHUsigqH6zDk.fjHQg07OWyqTcSJFfHM.sj4kHgv2mk2', 1, '2025-09-03 13:27:57', '2025-09-09 15:10:40'),
(4, 'Khein Lelouch C. Eusebio', '123456', 'merlindaborboncomoro@gmail.com', 'male', '$2y$10$h2j2Aq1CcRfWY0mxGjbkx.CwkxWZGq4/.sMyJF4tsYrnwRP9Zq8fS', 1, '2025-09-06 04:10:26', '2025-09-08 04:17:14'),
(6, 'Josua M Flores', '2203661', 'josua@gmail.com', 'male', '$2y$10$egaDhaADOM7mvUQhwqaRQuFGBbrHgJ4p6sYpw1cXxm0FKsTEvZFHe', 1, '2025-09-08 05:09:48', '2025-09-09 15:10:18'),
(7, 'Aeris  Ygusquiza', '2202561', 'aerisganda@gmail.com', 'female', '$2y$10$WRmRfPTHg3N65hOewEoP1u8bkgYM0Bpu9e4el.l49DrmIqw6trxMO', 1, '2025-09-09 15:09:35', '2025-09-15 16:42:48'),
(8, 'John E Doe', '2204771', 'john@outlook.com', 'male', '$2y$10$n7nQMv9cz6uMoLlUQk2.de6kkFzP5dcwCEzbuMK/eeP0SV0cz8ptu', 5, '2025-09-15 16:50:25', '2025-09-15 16:50:25');

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
(2, 'Jeffry M. Duria', 'SirJeff', 'jeff@gmail.com', '$2y$10$htkg.h19uoxm/GX2nRQZBueyPHktSQTo1MWkvzy6KDR03uKq7VWh2', '2025-09-05 05:47:36', '2025-09-05 05:47:36'),
(3, 'Clarisse P. Cartagenas', 'claaa', 'cla@gmail.com', '$2y$10$O7tjjz1j1qWg3IsvROjq0e68IDROFcdeMQpVM4b.MfT8RLC31f.Vq', '2025-09-05 08:32:35', '2025-09-05 08:32:35'),
(4, 'James G. Huelgas', 'James', 'james@gmail.com', '$2y$10$QCuHECUe2gximtoyeFpTvOBDSAa7NA8FKtGAZ7/dh.9Qtd31eQQ2y', '2025-09-05 08:35:23', '2025-09-05 08:35:23');

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
(1, 4, 1, '2025-09-05 08:35:23'),
(6, 3, 2, '2025-09-09 15:12:59'),
(8, 2, 5, '2025-09-15 16:45:30'),
(9, 2, 4, '2025-09-15 16:45:30');

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

--
-- Dumping data for table `warm_ups`
--

INSERT INTO `warm_ups` (`id`, `teacher_id`, `title`, `description`, `theme_settings`, `related_material_id`, `created_at`, `updated_at`) VALUES
(1, 2, 'DDSADA', 'ASDAS', NULL, NULL, '2025-09-11 16:24:32', '2025-09-11 16:24:32');

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
-- Dumping data for table `warm_up_questions`
--

INSERT INTO `warm_up_questions` (`id`, `warm_up_id`, `question_type`, `question_text`, `options`, `answer`, `created_at`) VALUES
(1, 1, 'multiple_choice', 'caxfcq', '{\"A\":\"qw\",\"B\":\"er\",\"C\":\"rt\",\"D\":\"ty\"}', 'C', '2025-09-11 16:24:32');

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
-- Indexes for table `practice_tests`
--
ALTER TABLE `practice_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_practice_test_id` (`practice_test_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Indexes for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_practice_question` (`practice_test_id`,`question_id`),
  ADD KEY `idx_practice_test_id` (`practice_test_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_question_order` (`question_order`);

--
-- Indexes for table `practice_test_responses`
--
ALTER TABLE `practice_test_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_is_correct` (`is_correct`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `question_id` (`question_id`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `assessment_assignments`
--
ALTER TABLE `assessment_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `assessment_responses`
--
ALTER TABLE `assessment_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `practice_tests`
--
ALTER TABLE `practice_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_attempts`
--
ALTER TABLE `practice_test_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_questions`
--
ALTER TABLE `practice_test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_test_responses`
--
ALTER TABLE `practice_test_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `question_responses`
--
ALTER TABLE `question_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `question_sets`
--
ALTER TABLE `question_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reading_materials`
--
ALTER TABLE `reading_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `warmup_responses`
--
ALTER TABLE `warmup_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warm_ups`
--
ALTER TABLE `warm_ups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `warm_up_questions`
--
ALTER TABLE `warm_up_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `question_responses`
--
ALTER TABLE `question_responses`
  ADD CONSTRAINT `question_responses_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_responses_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD CONSTRAINT `quiz_responses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_responses_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_responses_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_scores`
--
ALTER TABLE `quiz_scores`
  ADD CONSTRAINT `quiz_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_scores_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_scores_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
