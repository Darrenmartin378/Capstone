<?php
// Student Module Initialization
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
$student = $studentRes ? $studentRes->fetch_assoc() : null;
$studentSectionId = (int)($student['section_id'] ?? 0);

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
