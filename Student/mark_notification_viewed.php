<?php
require_once __DIR__ . '/includes/student_init.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - No student_id in session']);
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$notificationType = $_POST['type'] ?? $_GET['type'] ?? '';
$notificationId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

// Log the received parameters
error_log("Received parameters - studentId: $studentId, type: $notificationType, id: $notificationId");

// Validate input
if (empty($notificationType) || !in_array($notificationType, ['announcement', 'question_set', 'material']) || $notificationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters - type: ' . $notificationType . ', id: ' . $notificationId]);
    exit;
}

try {
    // Check if table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'viewed_notifications'");
    if ($tableCheck->num_rows == 0) {
        error_log("Creating viewed_notifications table");
        // Create the table if it doesn't exist
        $createTable = "
            CREATE TABLE `viewed_notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `student_id` int(11) NOT NULL,
              `notification_type` enum('announcement','question_set','material') NOT NULL,
              `notification_id` int(11) NOT NULL,
              `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_view` (`student_id`, `notification_type`, `notification_id`),
              KEY `student_id` (`student_id`),
              KEY `notification_type` (`notification_type`),
              KEY `notification_id` (`notification_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        $createResult = $conn->query($createTable);
        if (!$createResult) {
            error_log("Failed to create table: " . $conn->error);
        }
    }
    
    // Insert or update the viewed notification (using INSERT IGNORE to handle duplicates)
    $stmt = $conn->prepare("
        INSERT IGNORE INTO viewed_notifications (student_id, notification_type, notification_id) 
        VALUES (?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('isi', $studentId, $notificationType, $notificationId);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to execute statement: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    error_log("Insert result - affected rows: $affectedRows");
    
    if ($affectedRows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as viewed', 'affected_rows' => $affectedRows]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Notification already marked as viewed', 'affected_rows' => $affectedRows]);
    }
    
} catch (Exception $e) {
    error_log("Error marking notification as viewed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
