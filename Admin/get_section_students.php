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
    
    // Get students for this section, separated by gender
    $stmt = $conn->prepare("SELECT name, student_number, email, gender FROM students WHERE section_id = ? ORDER BY gender, name");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    $male_students = [];
    $female_students = [];
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
        if (strtolower($row['gender']) === 'male') {
            $male_students[] = $row;
        } elseif (strtolower($row['gender']) === 'female') {
            $female_students[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'students' => $students,
        'male_students' => $male_students,
        'female_students' => $female_students
    ]);
    
} catch (Exception $e) {
    logError('Error fetching section students', ['error' => $e->getMessage(), 'section_id' => $section_id]);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
