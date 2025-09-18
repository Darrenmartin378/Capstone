<?php
// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Logout support
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Auth guard: pages under Admin/ require auth
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: ../login.php");
    exit();
}

// Set error reporting after potential redirects
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// Database connection
$conn = new mysqli("localhost", "root", "", "compre_learn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate CSRF token
generateCSRFToken();

// Helper for escaping output
function h($v) { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}

// Helper for generating CSRF token input
function csrf_token() {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCSRFToken()) . '">';
}

// Verify CSRF token
function verify_csrf_token() {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        logError('CSRF token verification failed', ['ip' => $_SERVER['REMOTE_ADDR']]);
        die('Security error: Invalid request. Please try again.');
    }
}

// Get current page name for navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Lightweight schema guard: ensure students.gender exists (male/female)
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN gender ENUM('male','female') NULL AFTER email");
}

// Ensure admin table exists with proper structure
$adminTableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
if ($adminTableCheck && $adminTableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default admin if no admins exist
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, email, password_hash, full_name) VALUES ('admin', 'admin@comprelearn.com', '$defaultPassword', 'System Administrator')");
}

// Enhanced input validation and sanitization
function validateInput($input, $type = 'string', $maxLength = 255) {
    if (empty($input)) return false;
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) && strlen($input) <= $maxLength;
        case 'int':
            return is_numeric($input) && (int)$input > 0;
        case 'string':
        default:
            return is_string($input) && strlen(trim($input)) > 0 && strlen($input) <= $maxLength;
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Enhanced error logging
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/admin_errors.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Enhanced CSRF protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time']) || (time() - $_SESSION['csrf_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check if token is expired (1 hour)
    if (empty($_SESSION['csrf_time']) || (time() - $_SESSION['csrf_time']) > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

?>

