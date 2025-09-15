<?php
require_once __DIR__ . '/includes/admin_init.php';

header('Content-Type: application/json');

try {
    $section_id = $_GET['section_id'] ?? '';
    
    if (!validateInput($section_id, 'int')) {
        echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
        exit();
    }
    
    $section_id = (int)$section_id;
    
    // Get teachers for this section
    $stmt = $conn->prepare("
        SELECT t.name, t.username, t.email 
        FROM teachers t 
        INNER JOIN teacher_sections ts ON t.id = ts.teacher_id 
        WHERE ts.section_id = ? 
        ORDER BY t.name
    ");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    
    echo json_encode(['success' => true, 'teachers' => $teachers]);
    
} catch (Exception $e) {
    logError('Error fetching section teachers', ['error' => $e->getMessage(), 'section_id' => $section_id]);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
