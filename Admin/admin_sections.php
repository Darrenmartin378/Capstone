<?php
require_once __DIR__ . '/includes/admin_init.php';

$page_title = 'Sections Management';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_section':
            $raw_name = $_POST['name'] ?? '';
            if (!validateInput($raw_name, 'string', 100)) {
                header("Location: admin_sections.php?error=invalid_name");
                exit();
            }
            $section_name = sanitizeInput($raw_name);
            
            // Check if section name already exists
            $checkStmt = $conn->prepare("SELECT id FROM sections WHERE name = ?");
            $checkStmt->bind_param("s", $section_name);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                header("Location: admin_sections.php?error=duplicate_name");
                exit();
            }
            
            $stmt = $conn->prepare("INSERT INTO sections (name) VALUES (?)");
            $stmt->bind_param("s", $section_name);
            $stmt->execute();
            header("Location: admin_sections.php?success=section_added");
            exit();
            break;
            
        case 'edit_section':
            $raw_id = $_POST['section_id'] ?? '';
            $raw_name = $_POST['name'] ?? '';
            
            if (!validateInput($raw_id, 'int') || !validateInput($raw_name, 'string', 100)) {
                header("Location: admin_sections.php?error=invalid_data");
                exit();
            }
            
            $section_id = (int)$raw_id;
            $section_name = sanitizeInput($raw_name);
            
            // Check if section name already exists (excluding current section)
            $checkStmt = $conn->prepare("SELECT id FROM sections WHERE name = ? AND id != ?");
            $checkStmt->bind_param("si", $section_name, $section_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                header("Location: admin_sections.php?error=duplicate_name");
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE sections SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $section_name, $section_id);
            $stmt->execute();
            header("Location: admin_sections.php?success=section_updated");
            exit();
            break;
            
        case 'delete_section':
            $raw_id = $_POST['section_id'] ?? '';
            if (!validateInput($raw_id, 'int')) {
                header("Location: admin_sections.php?error=invalid_id");
                exit();
            }
            $section_id = (int)$raw_id;
            
            // Check if section has students or teachers assigned
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE section_id = ?");
            $checkStmt->bind_param("i", $section_id);
            $checkStmt->execute();
            $studentCount = $checkStmt->get_result()->fetch_assoc()['count'];
            
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_sections WHERE section_id = ?");
            $checkStmt->bind_param("i", $section_id);
            $checkStmt->execute();
            $teacherCount = $checkStmt->get_result()->fetch_assoc()['count'];
            
            if ($studentCount > 0 || $teacherCount > 0) {
                header("Location: admin_sections.php?error=section_in_use");
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            header("Location: admin_sections.php?success=section_deleted");
            exit();
            break;
    }
}

// Get data for display
$sql_sections = "
    SELECT 
        s.*,
        COUNT(DISTINCT st.id) as student_count,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') as teachers
    FROM sections s
    LEFT JOIN students st ON s.id = st.section_id
    LEFT JOIN teacher_sections ts ON s.id = ts.section_id
    LEFT JOIN teachers t ON ts.teacher_id = t.id
    GROUP BY s.id, s.name, s.created_at
    ORDER BY s.created_at ASC, s.name
";
$stmt_sections = $conn->prepare($sql_sections);
$stmt_sections->execute();
$sections = $stmt_sections->get_result();

// Get all sections for dropdown
$all_sections = [];
$sections->data_seek(0);
while ($section = $sections->fetch_assoc()) {
    $all_sections[] = $section;
}

// Get selected section (from URL parameter or default to first section)
$selected_section_id = $_GET['selected_section'] ?? '';
$selected_section = null;
$selected_section_students = [];
$selected_section_teachers = [];

if (!empty($selected_section_id) && validateInput($selected_section_id, 'int')) {
    // Find the selected section
    foreach ($all_sections as $section) {
        if ($section['id'] == $selected_section_id) {
            $selected_section = $section;
            break;
        }
    }
} elseif (!empty($all_sections)) {
    // Default to first section if no selection
    $selected_section = $all_sections[0];
}

if ($selected_section) {
    // Get students for the selected section, separated by gender
    $stmt_students = $conn->prepare("
        SELECT name, student_number, email, gender 
        FROM students 
        WHERE section_id = ? 
        ORDER BY gender, name
    ");
    $stmt_students->bind_param("i", $selected_section['id']);
    $stmt_students->execute();
    $students_result = $stmt_students->get_result();
    
    while ($student = $students_result->fetch_assoc()) {
        $selected_section_students[] = $student;
    }
    
    // Get teachers for the selected section
    $stmt_teachers = $conn->prepare("
        SELECT t.name, t.username, t.email 
        FROM teachers t 
        INNER JOIN teacher_sections ts ON t.id = ts.teacher_id 
        WHERE ts.section_id = ? 
        ORDER BY t.name
    ");
    $stmt_teachers->bind_param("i", $selected_section['id']);
    $stmt_teachers->execute();
    $teachers_result = $stmt_teachers->get_result();
    
    while ($teacher = $teachers_result->fetch_assoc()) {
        $selected_section_teachers[] = $teacher;
    }
}

// Reset the result pointer for the main table display
$sections->data_seek(0);

// Start output buffering
ob_start();
?>

<style>
    .management-area {
        background: var(--light-surface);
        color: var(--light-text);
        padding: 2rem;
        border-radius: 14px;
        box-shadow: var(--card-shadow);
        animation: fadeIn .7s;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--primary-accent);
        gap: 20px;
        flex-wrap: wrap;
    }
    
    
    .add-btn {
        background: var(--secondary-accent);
        color: #fff;
        border: none;
        padding: 12px 28px;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        transition: background .3s, transform .2s;
        min-width: 140px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .add-btn:hover { 
        background: #e94560cc; 
        transform: translateY(-2px); 
    }
    
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1002;
        inset: 0;
        background: rgba(246,248,250,0.85);
        backdrop-filter: blur(6px);
        animation: fadeIn .5s;
        transition: background .3s;
    }
    
    .modal-content {
        background: linear-gradient(135deg, #f6f8fa 80%, #e9ecef 100%);
        color: var(--light-text);
        margin: 5% auto;
        padding: 36px 32px;
        border-radius: 22px;
        width: 100%; 
        max-width: 480px;
        border: 2px solid var(--secondary-accent);
        box-shadow: 0 12px 40px rgba(0,0,0,0.22), 0 2px 8px rgba(0,0,0,0.12);
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        animation: fadeIn .7s;
    }
    
    .close {
        position: absolute; 
        right: 22px; 
        top: 18px;
        font-size: 32px; 
        font-weight: bold;
        cursor: pointer; 
        color: var(--grey-text);
        transition: color .2s, transform .2s;
    }
    
    .close:hover { 
        color: var(--secondary-accent); 
        transform: scale(1.2);
    }
    
    .modal-content h2 {
        font-size: 1.5rem;
        color: var(--secondary-accent);
        margin-bottom: 24px;
        letter-spacing: 1px;
        text-align: center;
    }
    
    .form-group { 
        margin-bottom: 22px; 
    }
    
    .form-group label { 
        display: block; 
        margin-bottom: 8px; 
        font-weight: 600; 
        color: var(--grey-text);
    }
    
    .form-group input {
        width: 100%;
        padding: 13px 14px;
        border: 2px solid var(--primary-accent);
        border-radius: 10px;
        font-size: 1rem;
        background: #fff;
        color: var(--light-text);
        transition: border-color .2s, box-shadow .2s;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    
    .form-group input:focus {
        border-color: var(--secondary-accent);
        box-shadow: 0 0 0 2px rgba(233,69,96,0.10);
        outline: none;
    }
    
    .submit-btn {
        background: var(--primary-accent);
        color: #fff;
        border: none;
        padding: 14px 0;
        border-radius: 25px;
        cursor: pointer;
        font-size: 1.08rem;
        width: 100%;
        transition: background .3s, transform .2s;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        margin-top: 10px;
        letter-spacing: .5px;
    }
    
    .submit-btn:hover, .submit-btn:focus {
        background: var(--secondary-accent);
        transform: translateY(-2px) scale(1.02);
    }
    
    .success-message, .error-message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        animation: slideIn 0.3s ease-out;
    }
    
    .success-message {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .success-message::before {
        content: "✓";
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .error-message {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .error-message::before {
        content: "⚠️";
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .message-close {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    
    .success-message .message-close {
        color: #155724;
    }
    
    .success-message .message-close:hover {
        background: #155724;
        color: #d4edda;
    }
    
    .error-message .message-close {
        color: #721c24;
    }
    
    .error-message .message-close:hover {
        background: #721c24;
        color: #f8d7da;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
    
    .form-container {
        background: #fff;
        border: 2px solid var(--primary-accent);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--primary-accent);
    }
    
    .form-header h3 {
        color: var(--secondary-accent);
        margin: 0;
        font-size: 1.3rem;
    }
    
    .close-form-btn {
        background: none;
        border: none;
        font-size: 24px;
        color: var(--grey-text);
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    
    .close-form-btn:hover {
        background: var(--secondary-accent);
        color: white;
    }
    
    .form-row {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    
    .form-row .form-group {
        flex: 1;
        min-width: 200px;
        margin-bottom: 0;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        align-items: end;
    }
    
    .form-actions .submit-btn {
        flex: 1;
        margin-top: 0;
    }
    
    .cancel-btn {
        background: #6c757d;
        color: #fff;
        border: none;
        padding: 14px 24px;
        border-radius: 25px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        transition: background .3s;
    }
    
    .cancel-btn:hover {
        background: #5a6268;
    }
    
    
    .details-modal {
        display: none;
        position: fixed;
        z-index: 1003;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    
    .details-modal-content {
        background: #fff;
        margin: 5% auto;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }
    
    .details-modal h2 {
        color: #333;
        margin-bottom: 20px;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }
    
    .details-list {
        list-style: none;
        padding: 0;
    }
    
    .details-list li {
        padding: 10px;
        margin-bottom: 8px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #007bff;
    }
    
    .details-list li:last-child {
        margin-bottom: 0;
    }
    
    .close-details {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        transition: color .2s;
    }
    
    .close-details:hover {
        color: #000;
    }
    
    .default-section-display {
        background: #f8f9fa;
        border: 2px solid #007bff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .default-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #007bff;
    }
    
    .default-section-title {
        color: #007bff;
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    
    .default-section-info {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .section-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .teachers-section, .students-section {
        background: #fff;
        border-radius: 8px;
        padding: 15px;
        border: 1px solid #dee2e6;
    }
    
    .section-subtitle {
        color: #495057;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .gender-group {
        margin-bottom: 15px;
    }
    
    .gender-header {
        color: #007bff;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 8px;
        padding: 5px 10px;
        background: #e3f2fd;
        border-radius: 4px;
        border-left: 4px solid #007bff;
    }
    
    .student-item, .teacher-item {
        padding: 8px 12px;
        margin-bottom: 6px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 3px solid #28a745;
        font-size: 0.9rem;
    }
    
    .teacher-item {
        border-left-color: #007bff;
    }
    
    .student-name, .teacher-name {
        font-weight: 600;
        color: #495057;
    }
    
    .student-details, .teacher-details {
        color: #6c757d;
        font-size: 0.85rem;
        margin-top: 2px;
    }
    
    .no-data {
        color: #6c757d;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }
    
    /* Responsive Design */
    
    /* Tablet and smaller desktop */
    @media (max-width: 1200px) {
        .section-selector {
            flex-direction: column;
            gap: 12px;
        }
        
        .section-dropdown {
            width: 100%;
        }
        
        .view-section-btn {
            width: 100%;
        }
    }
    
    /* Mobile landscape and small tablets */
    @media (max-width: 900px) {
        .section-details-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .default-section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .section-actions {
            flex-direction: column;
            gap: 8px;
        }
        
        .edit-btn, .delete-section-btn {
            width: 100%;
        }
    }
    
    /* Mobile portrait */
    @media (max-width: 768px) {
        .section-details-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .default-section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .section-selector {
            padding: 1rem;
        }
        
        .section-dropdown {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .view-section-btn {
            height: 48px;
            font-size: 0.9rem;
        }
        
        .section-actions {
            flex-direction: column;
            gap: 8px;
        }
        
        .edit-btn, .delete-section-btn {
            width: 100%;
            padding: 12px;
        }
        
        .modal {
            padding: 1rem;
        }
        
        .modal-content {
            width: 95%;
            max-width: 500px;
            margin: 2rem auto;
        }
        
        .edit-modal-content {
            width: 95%;
            max-width: 400px;
        }
        
        .edit-form-group input {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .edit-modal-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .save-btn, .cancel-edit-btn {
            width: 100%;
            padding: 12px;
        }
    }
    
    /* Small mobile devices */
    @media (max-width: 480px) {
        .section-selector {
            padding: 0.8rem;
        }
        
        .section-dropdown {
            padding: 12px 16px;
            font-size: 16px;
        }
        
        .view-section-btn {
            height: 44px;
            font-size: 0.85rem;
        }
        
        .default-section-header h2 {
            font-size: 1.3rem;
        }
        
        .section-actions {
            gap: 6px;
        }
        
        .edit-btn, .delete-section-btn {
            padding: 10px;
            font-size: 0.9rem;
        }
        
        .modal-content {
            width: 98%;
            margin: 1rem auto;
            padding: 1.5rem 1rem;
        }
        
        .edit-modal-content {
            width: 98%;
            padding: 1.5rem 1rem;
        }
        
        .edit-form-group input {
            padding: 10px 12px;
            font-size: 16px;
        }
        
        .save-btn, .cancel-edit-btn {
            padding: 10px;
            font-size: 0.9rem;
        }
        
        .gender-group {
            padding: 12px;
        }
        
        .gender-header {
            font-size: 1rem;
        }
        
        .student-item, .teacher-item {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }
    
    .section-selector {
        background: #fff;
        border: 2px solid #007bff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .section-selector-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .section-selector-title {
        color: #007bff;
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
    }
    
    .section-dropdown-container {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .section-dropdown {
        padding: 10px 15px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        color: #495057;
        font-size: 1rem;
        min-width: 200px;
        cursor: pointer;
        transition: border-color .3s, box-shadow .3s;
    }
    
    .section-dropdown:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        outline: none;
    }
    
    .section-dropdown:hover {
        border-color: #007bff;
    }
    
    .view-section-btn {
        background: #007bff;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s, transform .2s;
    }
    
    .view-section-btn:hover {
        background: #0056b3;
        transform: translateY(-1px);
    }
    
    .view-section-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
    }
    
    .section-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }
    
    .edit-btn {
        background: #28a745;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s, transform .2s;
    }
    
    .edit-btn:hover {
        background: #218838;
        transform: translateY(-1px);
    }
    
    .delete-section-btn {
        background: #dc3545;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s, transform .2s;
    }
    
    .delete-section-btn:hover {
        background: #c82333;
        transform: translateY(-1px);
    }
    
    .edit-modal {
        display: none;
        position: fixed;
        z-index: 1004;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    
    .edit-modal-content {
        background: #fff;
        margin: 10% auto;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        position: relative;
        box-shadow: 0 12px 40px rgba(0,0,0,0.22);
    }
    
    .edit-modal h2 {
        color: #333;
        margin-bottom: 20px;
        border-bottom: 2px solid #28a745;
        padding-bottom: 10px;
    }
    
    .edit-form-group {
        margin-bottom: 20px;
    }
    
    .edit-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
    }
    
    .edit-form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        font-size: 1rem;
        background: #fff;
        color: #495057;
        transition: border-color .3s, box-shadow .3s;
        box-sizing: border-box;
    }
    
    .edit-form-group input:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40,167,69,0.25);
        outline: none;
    }
    
    .edit-modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .save-btn {
        background: #28a745;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s;
    }
    
    .save-btn:hover {
        background: #218838;
    }
    
    .cancel-edit-btn {
        background: #6c757d;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s;
    }
    
    .cancel-edit-btn:hover {
        background: #5a6268;
    }
    
    .close-edit-modal {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        transition: color .2s;
    }
    
    .close-edit-modal:hover {
        color: #000;
    }
</style>

<div class="management-area">
    <?php if (isset($_GET['success'])): ?>
        <div class="success-message" id="success-message">
            <?php
            switch ($_GET['success']) {
                case 'section_added':
                    echo 'Section added successfully!';
                    break;
                case 'section_updated':
                    echo 'Section updated successfully!';
                    break;
                case 'section_deleted':
                    echo 'Section deleted successfully!';
                    break;
            }
            ?>
            <button class="message-close" onclick="closeMessage('success-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message" id="error-message">
            <?php
            switch ($_GET['error']) {
                case 'invalid_name':
                    echo 'Please enter a valid section name.';
                    break;
                case 'invalid_data':
                    echo 'Invalid data provided. Please check your input.';
                    break;
                case 'duplicate_name':
                    echo 'A section with this name already exists.';
                    break;
                case 'invalid_id':
                    echo 'Invalid section ID provided.';
                    break;
                case 'section_in_use':
                    echo 'Cannot delete section: it has students or teachers assigned to it.';
                    break;
                default:
                    echo 'An error occurred. Please try again.';
            }
            ?>
            <button class="message-close" onclick="closeMessage('error-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Section Selector -->
    <div class="section-selector">
        <div class="section-selector-header">
            <h3 class="section-selector-title">Select Section to View</h3>
        </div>
        <div class="section-dropdown-container">
            <select id="section-dropdown" class="section-dropdown">
                <option value="">Choose a section...</option>
                <?php foreach ($all_sections as $section): ?>
                    <option value="<?php echo $section['id']; ?>" 
                            <?php echo ($selected_section && $selected_section['id'] == $section['id']) ? 'selected' : ''; ?>>
                        <?php echo h($section['name']); ?> (<?php echo $section['student_count']; ?> students)
                    </option>
                <?php endforeach; ?>
            </select>
            <button id="view-section-btn" class="view-section-btn" 
                    <?php echo !$selected_section ? 'disabled' : ''; ?>>
                View Section Details
            </button>
        </div>
    </div>
    
    <?php if ($selected_section): ?>
    <!-- Selected Section Display -->
    <div class="default-section-display">
        <div class="default-section-header">
            <h2 class="default-section-title"><?php echo h($selected_section['name']); ?> Section</h2>
            <div class="default-section-info">
                Created: <?php echo date('F j, Y', strtotime($selected_section['created_at'])); ?>
            </div>
        </div>
        
        <div class="section-details-grid">
            <!-- Teachers Section -->
            <div class="teachers-section">
                <h3 class="section-subtitle">Teachers</h3>
                <?php if (!empty($selected_section_teachers)): ?>
                    <?php foreach ($selected_section_teachers as $teacher): ?>
                        <div class="teacher-item">
                            <div class="teacher-name"><?php echo h($teacher['name']); ?></div>
                            <div class="teacher-details">
                                Username: <?php echo h($teacher['username']); ?><br>
                                Email: <?php echo h($teacher['email']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No teacher assigned to this section</div>
                <?php endif; ?>
            </div>
            
            <!-- Students Section -->
            <div class="students-section">
                <h3 class="section-subtitle">Students</h3>
                <?php if (!empty($selected_section_students)): ?>
                    <?php
                    // Separate students by gender
                    $male_students = array_filter($selected_section_students, function($student) {
                        return strtolower($student['gender']) === 'male';
                    });
                    $female_students = array_filter($selected_section_students, function($student) {
                        return strtolower($student['gender']) === 'female';
                    });
                    ?>
                    
                    <?php if (!empty($male_students)): ?>
                        <div class="gender-group">
                            <div class="gender-header">Male Students (<?php echo count($male_students); ?>)</div>
                            <?php foreach ($male_students as $student): ?>
                                <div class="student-item">
                                    <div class="student-name"><?php echo h($student['name']); ?></div>
                                    <div class="student-details">
                                        Student Number: <?php echo h($student['student_number']); ?><br>
                                        Email: <?php echo h($student['email']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($female_students)): ?>
                        <div class="gender-group">
                            <div class="gender-header">Female Students (<?php echo count($female_students); ?>)</div>
                            <?php foreach ($female_students as $student): ?>
                                <div class="student-item">
                                    <div class="student-name"><?php echo h($student['name']); ?></div>
                                    <div class="student-details">
                                        Student Number: <?php echo h($student['student_number']); ?><br>
                                        Email: <?php echo h($student['email']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($male_students) && empty($female_students)): ?>
                        <div class="no-data">No students registered in this section</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-data">No students registered in this section</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section Actions -->
        <div class="section-actions">
            <button class="edit-btn" onclick="editSection(<?php echo $selected_section['id']; ?>, '<?php echo h($selected_section['name']); ?>')">
                Edit Section Name
            </button>
            <button class="delete-section-btn" onclick="deleteSectionFromDetails(<?php echo $selected_section['id']; ?>, '<?php echo h($selected_section['name']); ?>')">
                Delete Section
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="section-header">
        <button id="toggle-form-btn" class="add-btn">Add Section</button>
    </div>

    <!-- Inline Add Form -->
    <div id="section-form-container" class="form-container" style="display: none;">
        <div class="form-header">
            <h3 id="form-title">Add New Section</h3>
            <button id="close-form-btn" class="close-form-btn">&times;</button>
        </div>
        <form id="section-form" method="POST">
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" value="add_section">
            <div class="form-row">
                <div class="form-group"><label>Section Name:</label><input type="text" id="section-name" name="name" required></div>
                <div class="form-group form-actions">
                    <button type="submit" id="section-submit-btn" class="submit-btn">Add Section</button>
                    <button type="button" id="cancel-form-btn" class="cancel-btn">Cancel</button>
                </div>
            </div>
        </form>
    </div>

</div>

<!-- Details Modal -->
<div id="details-modal" class="details-modal">
    <div class="details-modal-content">
        <span class="close-details" onclick="closeDetailsModal()">&times;</span>
        <h2 id="modal-title"></h2>
        <div id="modal-content"></div>
    </div>
</div>

<!-- Edit Section Modal -->
<div id="edit-modal" class="edit-modal">
    <div class="edit-modal-content">
        <span class="close-edit-modal" onclick="closeEditModal()">&times;</span>
        <h2>Edit Section Name</h2>
        <form id="edit-section-form" method="POST">
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" value="edit_section">
            <input type="hidden" name="section_id" id="edit-section-id">
            <div class="edit-form-group">
                <label for="edit-section-name">Section Name:</label>
                <input type="text" id="edit-section-name" name="name" required>
            </div>
            <div class="edit-modal-actions">
                <button type="button" class="cancel-edit-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showForm() {
        document.getElementById('section-form-container').style.display = 'block';
        document.getElementById('toggle-form-btn').textContent = 'Hide Form';
    }
    
    function hideForm() {
        document.getElementById('section-form-container').style.display = 'none';
        document.getElementById('toggle-form-btn').textContent = 'Add Section';
        resetForm();
    }
    
    function resetForm() {
        document.getElementById('section-name').value = '';
    }
    
    function openAddSectionForm() {
        resetForm();
        showForm();
    }
    
    
    function closeMessage(messageId) {
        const message = document.getElementById(messageId);
        if (message) {
            message.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(() => {
                message.remove();
            }, 300);
        }
    }
    
    
    function showDetailsModal(title, items, type) {
        document.getElementById('modal-title').textContent = title;
        
        let content = '';
        if (type === 'student') {
            content = '<div class="section-details-grid">';
            content += '<div class="students-section">';
            
            if (items.male_students && items.male_students.length > 0) {
                content += `<div class="gender-group">
                    <div class="gender-header">Male Students (${items.male_students.length})</div>`;
                items.male_students.forEach(student => {
                    content += `<div class="student-item">
                        <div class="student-name">${student.name}</div>
                        <div class="student-details">
                            Student Number: ${student.student_number}<br>
                            Email: ${student.email}
                        </div>
                    </div>`;
                });
                content += '</div>';
            }
            
            if (items.female_students && items.female_students.length > 0) {
                content += `<div class="gender-group">
                    <div class="gender-header">Female Students (${items.female_students.length})</div>`;
                items.female_students.forEach(student => {
                    content += `<div class="student-item">
                        <div class="student-name">${student.name}</div>
                        <div class="student-details">
                            Student Number: ${student.student_number}<br>
                            Email: ${student.email}
                        </div>
                    </div>`;
                });
                content += '</div>';
            }
            
            if ((!items.male_students || items.male_students.length === 0) && 
                (!items.female_students || items.female_students.length === 0)) {
                content += '<div class="no-data">No students found</div>';
            }
            
            content += '</div></div>';
        } else if (type === 'teacher') {
            content = '<div class="teachers-section">';
            if (items.length === 0) {
                content += '<div class="no-data">No teachers found</div>';
            } else {
                items.forEach(teacher => {
                    content += `<div class="teacher-item">
                        <div class="teacher-name">${teacher.name}</div>
                        <div class="teacher-details">
                            Username: ${teacher.username}<br>
                            Email: ${teacher.email}
                        </div>
                    </div>`;
                });
            }
            content += '</div>';
        }
        
        document.getElementById('modal-content').innerHTML = content;
        document.getElementById('details-modal').style.display = 'block';
    }
    
    function closeDetailsModal() {
        document.getElementById('details-modal').style.display = 'none';
    }
    
    function editSection(sectionId, sectionName) {
        document.getElementById('edit-section-id').value = sectionId;
        document.getElementById('edit-section-name').value = sectionName;
        document.getElementById('edit-modal').style.display = 'block';
    }
    
    function closeEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
        document.getElementById('edit-section-form').reset();
    }
    
    function deleteSectionFromDetails(sectionId, sectionName) {
        const message = `Are you sure you want to delete the section "${sectionName}"?\n\nThis action cannot be undone and will fail if the section has students or teachers assigned to it.`;
        if (!confirm(message)) return;
        
        const form = document.createElement('form'); 
        form.method = 'POST'; 
        form.style.display = 'none';
        form.innerHTML = `
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="section_id" value="${sectionId}">
        `;
        document.body.appendChild(form); 
        form.submit();
    }
    
    function autoDismissMessages() {
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        
        if (successMessage) {
            setTimeout(() => {
                closeMessage('success-message');
            }, 5000); // Auto-dismiss after 5 seconds
        }
        
        if (errorMessage) {
            setTimeout(() => {
                closeMessage('error-message');
            }, 8000); // Auto-dismiss after 8 seconds (errors stay longer)
        }
    }
    
    // Form controls
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('toggle-form-btn');
        const closeFormBtn = document.getElementById('close-form-btn');
        const cancelFormBtn = document.getElementById('cancel-form-btn');
        const formContainer = document.getElementById('section-form-container');
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (formContainer.style.display === 'none') {
                    openAddSectionForm();
                } else {
                    hideForm();
                }
            });
        }
        
        if (closeFormBtn) {
            closeFormBtn.addEventListener('click', hideForm);
        }
        
        if (cancelFormBtn) {
            cancelFormBtn.addEventListener('click', hideForm);
        }
        
        // Auto-dismiss messages
        autoDismissMessages();
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const detailsModal = document.getElementById('details-modal');
            const editModal = document.getElementById('edit-modal');
            
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        });
        
        // Section dropdown functionality
        const sectionDropdown = document.getElementById('section-dropdown');
        const viewSectionBtn = document.getElementById('view-section-btn');
        
        if (sectionDropdown && viewSectionBtn) {
            // Enable/disable view button based on selection
            sectionDropdown.addEventListener('change', function() {
                viewSectionBtn.disabled = !this.value;
            });
            
            // Handle view section button click
            viewSectionBtn.addEventListener('click', function() {
                const selectedSectionId = sectionDropdown.value;
                if (selectedSectionId) {
                    // Redirect to the same page with selected section parameter
                    const currentUrl = new URL(window.location);
                    currentUrl.searchParams.set('selected_section', selectedSectionId);
                    window.location.href = currentUrl.toString();
                }
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/includes/admin_layout.php';
?>
