<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Logout support
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: /capstone/Teacher/teacher_login.php');
    exit();
}

// Auth guard: pages under Teacher/ require auth
if (!isset($_SESSION['teacher_logged_in']) || !$_SESSION['teacher_logged_in']) {
    header('Location: /capstone/Teacher/teacher_login.php');
    exit();
}

$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$teacherName = $_SESSION['teacher_name'] ?? '';

// If name is missing but we have an id, fetch it
if ($teacherName === '' && $teacherId > 0) {
    $tmp = $conn->prepare('SELECT name FROM teachers WHERE id = ? LIMIT 1');
    if ($tmp) {
        $tmp->bind_param('i', $teacherId);
        $tmp->execute();
        $res = $tmp->get_result();
        if ($row = $res->fetch_assoc()) {
            $teacherName = (string)($row['name'] ?? '');
            if ($teacherName !== '') { $_SESSION['teacher_name'] = $teacherName; }
        }
        $tmp->close();
    }
}

if ($teacherName === '') { $teacherName = 'Teacher'; }

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'compre_learn');
if ($conn->connect_error) {
    die('DB connection failed: ' . $conn->connect_error);
}

// Ensure core tables exist (safe to run each request)
$conn->query("CREATE TABLE IF NOT EXISTS question_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colCheck = $conn->query("SHOW COLUMNS FROM question_bank LIKE 'set_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE question_bank ADD COLUMN set_id INT NULL AFTER teacher_id");
}

$conn->query("CREATE TABLE IF NOT EXISTS reading_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    theme_settings JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS question_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT NULL,
    set_id INT NULL,
    question_type ENUM('multiple_choice','matching','essay') NOT NULL,
    question_text TEXT NOT NULL,
    options_json JSON NULL,
    answer TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add section_id column if it doesn't exist
$colCheck = $conn->query("SHOW COLUMNS FROM question_bank LIKE 'section_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE question_bank ADD COLUMN section_id INT NULL AFTER teacher_id");
}

// Add set_title column if it doesn't exist
$colCheck = $conn->query("SHOW COLUMNS FROM question_bank LIKE 'set_title'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE question_bank ADD COLUMN set_title VARCHAR(255) NULL AFTER section_id");
}

// Add options_json column if it doesn't exist
$colCheck = $conn->query("SHOW COLUMNS FROM question_bank LIKE 'options_json'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE question_bank ADD COLUMN options_json JSON NULL AFTER question_text");
}

// Migrate existing options data to options_json if needed
$result = $conn->query("SELECT id, options FROM question_bank WHERE options IS NOT NULL AND options != '' AND (options_json IS NULL OR options_json = '')");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ? WHERE id = ?");
        $updateStmt->bind_param('si', $row['options'], $row['id']);
        $updateStmt->execute();
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    theme_settings JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_type ENUM('multiple_choice','matching','essay') NOT NULL,
    question_text TEXT NOT NULL,
    options_json JSON NULL,
    answer TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS assessment_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    section_id INT NULL,
    student_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Practice Tests use existing warm_ups and warm_up_questions tables

// Helper for escaping output
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>


