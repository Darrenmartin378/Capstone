<?php
// Student Module Initialization
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clean up any teacher session data to avoid conflicts
if (isset($_SESSION['teacher_logged_in'])) {
    unset($_SESSION['teacher_logged_in']);
    unset($_SESSION['teacher_id']);
    unset($_SESSION['teacher_name']);
    unset($_SESSION['csrf_token']);
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'compre_learn');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Authentication check for student pages
if (!isset($_SESSION['student_logged_in']) || !$_SESSION['student_logged_in']) {
    header('Location: ../login.php');
    exit();
}

// Get student information
$studentId = (int)($_SESSION['student_id'] ?? 0);
$studentName = $_SESSION['student_name'] ?? 'Student';


// Fetch student details (for section)
$studentRes = $conn->query("SELECT * FROM students WHERE id = $studentId");
if (!$studentRes) {
    error_log("Database error: " . $conn->error);
    $student = null;
} else {
    $student = $studentRes->fetch_assoc();
}
$studentSectionId = (int)($student['section_id'] ?? 0);

// Always update section_id in session from database
if ($studentSectionId > 0) {
    $_SESSION['section_id'] = $studentSectionId;
} else {
    // If no section assigned, set to null
    $_SESSION['section_id'] = null;
}


// Fetch section name
$sectionName = 'No Section';
if ($studentSectionId > 0) {
    $sectionRes = $conn->query("SELECT name FROM sections WHERE id = $studentSectionId");
    if ($sectionRes && $sectionRes->num_rows > 0) {
        $sectionName = $sectionRes->fetch_assoc()['name'];
    }
}

// Helper function
function h($v) { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}
?>
