<?php
// Only enable error display if not already suppressed and not in AJAX request
if (!ini_get('display_errors') && !isset($_POST['action'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    // For AJAX requests, suppress error display to prevent HTML output
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}
session_start();

// Logout support
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Auth guard: pages under Teacher/ require auth
if (!isset($_SESSION['teacher_logged_in']) || !$_SESSION['teacher_logged_in']) {
    // If this is an AJAX action request, return JSON instead of HTML redirect
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in again.']);
        exit();
    }
    header('Location: ../login.php');
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
    error_log('DB connection failed: ' . $conn->connect_error);
    if (!headers_sent()) {
        header('Location: ../login.php?error=db_connection');
    }
    exit;
}

// Note: Core tables are now managed by the new modular system

$conn->query("CREATE TABLE IF NOT EXISTS reading_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    theme_settings JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration code removed - using new modular system

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

// Function to get teacher's current section assignments
function getTeacherSections($conn, $teacherId) {
    $sections = [];
    if ($teacherId > 0) {
        $stmt = $conn->prepare("
            SELECT s.id, s.name as section_name 
            FROM sections s 
            INNER JOIN teacher_sections ts ON s.id = ts.section_id 
            WHERE ts.teacher_id = ? 
            ORDER BY s.name
        ");
        if ($stmt) {
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $sections[] = $row;
            }
            $stmt->close();
        }
    }
    return $sections;
}

// Get teacher's current sections for this session
$teacherSections = getTeacherSections($conn, $teacherId);

?>


