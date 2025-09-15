<?php
// Helper function to create student notifications

function createStudentNotification($conn, $teacherId, $type, $title, $message, $relatedId = null, $sectionId = null, $studentId = null) {
    try {
        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'student_notifications'");
        if ($tableCheck->num_rows == 0) {
            error_log("Student notifications table does not exist");
            return false;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO student_notifications 
            (teacher_id, type, title, message, related_id, section_id, student_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssiii', $teacherId, $type, $title, $message, $relatedId, $sectionId, $studentId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error creating student notification: " . $e->getMessage());
        return false;
    }
}

function createNotificationForSection($conn, $teacherId, $sectionId, $type, $title, $message, $relatedId = null) {
    return createStudentNotification($conn, $teacherId, $type, $title, $message, $relatedId, $sectionId, null);
}

function createNotificationForStudent($conn, $teacherId, $studentId, $type, $title, $message, $relatedId = null) {
    return createStudentNotification($conn, $teacherId, $type, $title, $message, $relatedId, null, $studentId);
}

function createNotificationForAllStudents($conn, $teacherId, $type, $title, $message, $relatedId = null) {
    // Get all sections for this teacher
    $sections = $conn->query("SELECT id FROM sections WHERE teacher_id = $teacherId");
    
    $success = true;
    while ($section = $sections->fetch_assoc()) {
        if (!createNotificationForSection($conn, $teacherId, $section['id'], $type, $title, $message, $relatedId)) {
            $success = false;
        }
    }
    
    return $success;
}
?>
