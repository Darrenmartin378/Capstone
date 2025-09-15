<?php
require_once __DIR__ . '/includes/admin_init.php';

$page_title = 'Teachers Management';

// Handle AJAX request for user data
if (isset($_GET['action']) && $_GET['action'] == 'get_user') {
    header('Content-Type: application/json');
    
    $user_type = $_GET['user_type'];
    $user_id = $_GET['user_id'];
    
    if ($user_type === 'teacher') {
        $sql = "SELECT t.*, GROUP_CONCAT(s.id) as section_ids, GROUP_CONCAT(s.name) as section_names 
                FROM teachers t 
                LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id 
                LEFT JOIN sections s ON ts.section_id = s.id 
                WHERE t.id = ? 
                GROUP BY t.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            echo json_encode($user);
            exit();
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'User not found or invalid type']);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_teacher':
            // Validate and sanitize input
            $first = sanitizeInput($_POST['first_name'] ?? '');
            $middle = sanitizeInput($_POST['middle_initial'] ?? '');
            $last = sanitizeInput($_POST['last_name'] ?? '');
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate required fields
            if (!validateInput($first, 'string', 50) || !validateInput($last, 'string', 50) || 
                !validateInput($username, 'string', 50) || !validateInput($email, 'email', 100) || 
                empty($password) || strlen($password) < 6) {
                logError('Teacher add failed - validation error', ['admin_id' => $adminId]);
                header("Location: admin_teachers.php?error=validation_failed");
                exit();
            }
            
            // Check if username or email already exists
            $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE username = ? OR email = ?");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                logError('Teacher add failed - duplicate username/email', ['username' => $username, 'email' => $email]);
                header("Location: admin_teachers.php?error=duplicate_credentials");
                exit();
            }
            
            $name = trim($first . ($middle !== '' ? ' ' . strtoupper(substr($middle,0,1)) . '.' : '') . ' ' . $last);
            $stmt = $conn->prepare("INSERT INTO teachers (name, username, email, password) VALUES (?, ?, ?, ?)");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $name, $username, $email, $hashed_password);
            
            if (!$stmt->execute()) {
                logError('Teacher add failed - database error', ['error' => $stmt->error, 'admin_id' => $adminId]);
                header("Location: admin_teachers.php?error=database_error");
                exit();
            }
            
            $teacher_id = $conn->insert_id;
            
            // Handle multiple section assignments
            if (!empty($_POST['section_ids']) && is_array($_POST['section_ids'])) {
                $stmt = $conn->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
                foreach ($_POST['section_ids'] as $section_id) {
                    if (validateInput($section_id, 'int')) {
                        $stmt->bind_param("ii", $teacher_id, $section_id);
                        $stmt->execute();
                    }
                }
            }
            header("Location: admin_teachers.php?success=teacher_added");
            exit();
            break;
            
        case 'edit_teacher':
            $teacher_id = $_POST['user_id'];
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_initial'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $name = trim($first . ($middle !== '' ? ' ' . strtoupper(substr($middle,0,1)) . '.' : '') . ' ' . $last);
            // Admin cannot change user passwords - only update other information
            $stmt = $conn->prepare("UPDATE teachers SET name = ?, username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $_POST['username'], $_POST['email'], $teacher_id);
            $stmt->execute();
            
            // Update sections - delete existing assignments first
            $stmt = $conn->prepare("DELETE FROM teacher_sections WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            
            // Add new section assignments
            if (!empty($_POST['section_ids']) && is_array($_POST['section_ids'])) {
                $stmt = $conn->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
                foreach ($_POST['section_ids'] as $section_id) {
                    if (validateInput($section_id, 'int')) {
                        $stmt->bind_param("ii", $teacher_id, $section_id);
                        $stmt->execute();
                    }
                }
            }
            header("Location: admin_teachers.php?success=teacher_updated");
            exit();
            break;
            
        case 'delete_user':
            $user_id = $_POST['user_id'];
            $sql = "DELETE FROM teachers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            header("Location: admin_teachers.php?success=teacher_deleted");
            exit();
            break;
    }
}

// Get data for display
$search_term = $_GET['search'] ?? '';
$search_query = "%" . $search_term . "%";
$sections = $conn->query("SELECT * FROM sections ORDER BY name");

$sql_teachers = "SELECT t.*, GROUP_CONCAT(s.name SEPARATOR ', ') as section_names 
                FROM teachers t 
                LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id 
                LEFT JOIN sections s ON ts.section_id = s.id 
                WHERE t.name LIKE ? OR t.username LIKE ? 
                GROUP BY t.id 
                ORDER BY t.created_at DESC";
$stmt_teachers = $conn->prepare($sql_teachers);
$stmt_teachers->bind_param("ss", $search_query, $search_query);
$stmt_teachers->execute();
$teachers = $stmt_teachers->get_result();

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
    
    .search-container { 
        position: relative; 
        flex-grow: 1; 
        min-width: 0; 
    }
    
    .search-bar {
        width: 100%;
        padding: 12px 16px;
        border-radius: 25px;
        border: 2px solid var(--primary-accent);
        background: var(--light-bg-secondary);
        color: var(--light-text);
        font-size: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        transition: border-color .2s;
    }
    
    .search-bar:focus { 
        border-color: var(--secondary-accent); 
        outline: none; 
    }
    
    .search-btn {
        position: absolute; 
        top: 0; 
        right: 0; 
        height: 100%; 
        width: 80px;
        background: var(--primary-accent);
        border: none;
        color: #fff;
        border-radius: 0 25px 25px 0;
        cursor: pointer;
        transition: background-color .3s;
        font-size: 1rem; 
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    
    .search-btn:hover { 
        background: var(--secondary-accent); 
    }
    
    .clear-btn {
        position: absolute; 
        top: 50%; 
        right: 90px;
        transform: translateY(-50%);
        background: none; 
        border: none;
        color: var(--grey-text);
        font-size: 1.7rem; 
        cursor: pointer;
        display: none; 
        line-height: 1;
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
    
    .card-table-container {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        justify-content: flex-start;
        padding: 12px 0;
    }

    .user-card {
        background: var(--light-surface);
        box-shadow: var(--card-shadow);
        border-radius: 14px;
        width: 280px;
        min-height: 180px;
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 20px 18px 16px 18px;
        position: relative;
        transition: box-shadow .2s, transform .2s;
    }
    
    .user-card:hover {
        box-shadow: 0 10px 32px rgba(233,69,96,0.08), var(--card-shadow);
        transform: translateY(-2px) scale(1.02);
    }
    
    .user-card .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--primary-accent);
        margin-bottom: 8px;
    }
    
    .user-card .card-field {
        font-size: 1rem;
        color: var(--grey-text);
        margin-bottom: 4px;
    }
    
    .user-card .card-actions {
        margin-top: 12px;
        display: flex;
        gap: 10px;
    }
    
    .user-card .action-btn {
        padding: 8px 14px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: .97rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        color: #fff;
        transition: background .2s;
    }
    
    .user-card .edit-btn { 
        background: #3498db; 
    }
    
    .user-card .edit-btn:hover { 
        background: #217dbb; 
    }
    
    .user-card .delete-btn { 
        background: #e74c3c; 
    }
    
    .user-card .delete-btn:hover { 
        background: #c0392b; 
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
    
    .form-group input, .form-group select {
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
    
    .form-group input:focus, .form-group select:focus {
        border-color: var(--secondary-accent);
        box-shadow: 0 0 0 2px rgba(233,69,96,0.10);
        outline: none;
    }
    
    .password-input-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .password-input-container input {
        padding-right: 45px;
        flex: 1;
    }
    
    .password-toggle-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        font-size: 16px;
        user-select: none;
        color: #666;
        transition: color 0.2s;
        z-index: 10;
    }
    
    .password-toggle-icon:hover {
        color: #333;
    }
    
    /* Responsive Design */
    
    /* Tablet and smaller desktop */
    @media (max-width: 1200px) {
        .card-table-container {
            gap: 20px;
        }
        
        .user-card {
            width: 260px;
        }
    }
    
    /* Mobile landscape and small tablets */
    @media (max-width: 900px) {
        .search-container {
            flex-direction: column;
            gap: 12px;
        }
        
        .search-bar {
            width: 100%;
            margin-bottom: 8px;
        }
        
        .search-btn {
            position: static;
            width: 100%;
            border-radius: 25px;
            height: 48px;
        }
        
        .add-btn {
            width: 100%;
            margin-top: 8px;
        }
        
        .card-table-container {
            gap: 16px;
            justify-content: center;
        }
        
        .user-card {
            width: 240px;
            padding: 18px 16px 14px 16px;
        }
        
        .checkbox-group {
            flex-direction: column;
            gap: 8px;
        }
    }
    
    /* Mobile portrait */
    @media (max-width: 768px) {
        .search-container {
            padding: 1rem;
        }
        
        .search-bar {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .card-table-container {
            padding: 8px 0;
            gap: 12px;
        }
        
        .user-card {
            width: 100%;
            max-width: 320px;
            margin: 0 auto 12px auto;
            padding: 16px 14px 12px 14px;
        }
        
        .user-card .card-title {
            font-size: 1.1rem;
        }
        
        .user-card .card-field {
            font-size: 0.95rem;
        }
        
        .user-card .card-actions {
            margin-top: 10px;
            gap: 8px;
        }
        
        .user-card .action-btn {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .modal {
            padding: 1rem;
        }
        
        .modal-content {
            width: 95%;
            max-width: 500px;
            margin: 2rem auto;
        }
        
        .form-row {
            flex-direction: column;
            gap: 12px;
        }
        
        .form-row .form-group {
            min-width: auto;
            margin-bottom: 0;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .submit-btn, .cancel-btn {
            width: 100%;
            padding: 12px;
        }
        
        .checkbox-group {
            flex-direction: column;
            gap: 6px;
        }
    }
    
    /* Small mobile devices */
    @media (max-width: 480px) {
        .search-container {
            padding: 0.8rem;
        }
        
        .search-bar {
            padding: 12px 16px;
            font-size: 16px;
        }
        
        .search-btn, .add-btn {
            height: 44px;
            font-size: 0.9rem;
        }
        
        .user-card {
            padding: 14px 12px 10px 12px;
            min-height: 180px;
        }
        
        .user-card .card-title {
            font-size: 1rem;
            margin-bottom: 6px;
        }
        
        .user-card .card-field {
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .modal-content {
            width: 98%;
            margin: 1rem auto;
            padding: 1.5rem 1rem;
        }
        
        .form-group input, .form-group select {
            padding: 10px 12px;
            font-size: 16px;
        }
        
        .password-input-container input {
            padding-right: 40px;
        }
        
        .password-toggle-icon {
            right: 10px;
            font-size: 14px;
        }
        
        .checkbox-group {
            gap: 4px;
        }
        
        .checkbox-group label {
            font-size: 13px;
        }
    }
    
    .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 8px;
        max-height: 120px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 12px;
        background: #f9f9f9;
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background-color 0.2s;
        margin: 0;
        font-weight: normal;
    }
    
    .checkbox-item:hover {
        background-color: #e3f2fd;
    }
    
    .checkbox-item input[type="checkbox"] {
        margin: 0;
        cursor: pointer;
    }
    
    .checkbox-label {
        font-size: 0.95rem;
        color: var(--light-text);
        cursor: pointer;
    }
    
    .checkbox-item:has(input:checked) {
        background-color: var(--primary-accent);
        color: white;
    }
    
    .checkbox-item:has(input:checked) .checkbox-label {
        color: white;
    }
    
    .checkbox-item input[type="checkbox"] {
        accent-color: var(--secondary-accent);
        width: 18px; 
        height: 18px;
        border-radius: 4px;
        border: 2px solid var(--primary-accent);
        transition: accent-color .2s;
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
</style>

<div class="management-area">
    <?php if (isset($_GET['success'])): ?>
        <div class="success-message" id="success-message">
            <?php
            switch ($_GET['success']) {
                case 'teacher_added':
                    echo 'Teacher added successfully!';
                    break;
                case 'teacher_updated':
                    echo 'Teacher updated successfully!';
                    break;
                case 'teacher_deleted':
                    echo 'Teacher deleted successfully!';
                    break;
            }
            ?>
            <button class="message-close" onclick="closeMessage('success-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message" id="error-message">
            <?php
            $error = $_GET['error'];
            switch($error) {
                case 'validation_failed':
                    echo 'Please check all required fields and try again.';
                    break;
                case 'duplicate_credentials':
                    echo 'Username or email already exists. Please use different credentials.';
                    break;
                case 'database_error':
                    echo 'Database error occurred. Please try again.';
                    break;
                default:
                    echo 'An error occurred. Please try again.';
            }
            ?>
            <button class="message-close" onclick="closeMessage('error-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <div class="section-header">
        <form id="search-form" method="GET" action="admin_teachers.php" class="search-container">
            <input type="text" name="search" id="search-input" class="search-bar" placeholder="Search teachers by name or username" value="<?php echo h($search_term); ?>">
            <span id="clear-search-btn" class="clear-btn">&times;</span>
            <button type="submit" class="search-btn">Search</button>
        </form>
        <button id="toggle-form-btn" class="add-btn">Add Teacher</button>
    </div>

    <!-- Inline Add/Edit Form -->
    <div id="teacher-form-container" class="form-container" style="display: none;">
        <div class="form-header">
            <h3 id="form-title">Add New Teacher</h3>
            <button id="close-form-btn" class="close-form-btn">&times;</button>
        </div>
        <form id="teacher-form" method="POST">
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" id="teacher-action" value="add_teacher">
            <input type="hidden" name="user_id" id="teacher-user-id" value="">
            <div class="form-row">
                <div class="form-group"><label>First Name:</label><input type="text" id="teacher-first-name" name="first_name" required></div>
                <div class="form-group"><label>Middle Initial:</label><input type="text" id="teacher-middle-initial" name="middle_initial" maxlength="1" placeholder="M"></div>
                <div class="form-group"><label>Last Name:</label><input type="text" id="teacher-last-name" name="last_name" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Username:</label><input type="text" id="teacher-username" name="username" required></div>
                <div class="form-group"><label>Email:</label><input type="email" id="teacher-email" name="email" required></div>
                <div class="form-group">
                    <label>Sections:</label>
                    <div class="checkbox-group">
                        <?php $sections->data_seek(0); while($section = $sections->fetch_assoc()): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="section_ids[]" value="<?php echo $section['id']; ?>" class="section-checkbox">
                            <span class="checkbox-label"><?php echo h($section['name']); ?></span>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="teacher-password" name="password" required>
                        <span class="password-toggle-icon" onclick="togglePasswordVisibility('teacher-password')">👁</span>
                    </div>
                </div>
                <div class="form-group form-actions">
                    <button type="submit" id="teacher-submit-btn" class="submit-btn">Add Teacher</button>
                    <button type="button" id="cancel-form-btn" class="cancel-btn">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card-table-container">
        <?php while($teacher = $teachers->fetch_assoc()): ?>
        <div class="user-card">
            <div>
                <div class="card-title"><?php echo h($teacher['name']); ?></div>
                <div class="card-field"><strong>Username:</strong> <?php echo h($teacher['username'] ?? ''); ?></div>
                <div class="card-field"><strong>Email:</strong> <?php echo h($teacher['email'] ?? ''); ?></div>
                <div class="card-field"><strong>Sections:</strong> <?php echo h($teacher['section_names'] ?? 'No sections assigned'); ?></div>
                <div class="card-field"><strong>Registered:</strong> <?php echo date('F j, Y', strtotime($teacher['created_at'])); ?></div>
            </div>
            <div class="card-actions">
                <button class="action-btn edit-btn" onclick="editTeacher(<?php echo $teacher['id']; ?>)">Edit</button>
                <button class="action-btn delete-btn" onclick="deleteTeacher(<?php echo $teacher['id']; ?>)">Delete</button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>


<script>
    function showForm() {
        document.getElementById('teacher-form-container').style.display = 'block';
        document.getElementById('toggle-form-btn').textContent = 'Hide Form';
    }
    
    function hideForm() {
        document.getElementById('teacher-form-container').style.display = 'none';
        document.getElementById('toggle-form-btn').textContent = 'Add Teacher';
        resetForm();
    }
    
    function resetForm() {
        document.getElementById('form-title').textContent = 'Add New Teacher';
        document.getElementById('teacher-action').value = 'add_teacher';
        document.getElementById('teacher-user-id').value = '';
        document.getElementById('teacher-first-name').value = '';
        document.getElementById('teacher-last-name').value = '';
        document.getElementById('teacher-middle-initial').value = '';
        document.getElementById('teacher-username').value = '';
        document.getElementById('teacher-email').value = '';
        
        // Clear all section checkboxes
        const checkboxes = document.querySelectorAll('input[name="section_ids[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        const pwd = document.getElementById('teacher-password');
        if (pwd) { pwd.required = true; pwd.value = ''; }
        
        // Show password field for new teacher
        const pwdContainer = document.querySelector('.password-input-container');
        if (pwdContainer) { pwdContainer.style.display = 'block'; }
        const submit = document.getElementById('teacher-submit-btn');
        if (submit) submit.textContent = 'Add Teacher';
    }
    
    function openAddTeacherForm() {
        resetForm();
        showForm();
    }
    
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.parentElement.querySelector('.password-toggle-icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = '🙈';
        } else {
            input.type = 'password';
            icon.textContent = '👁';
        }
    }
    
    async function editTeacher(id) {
        try {
            const res = await fetch(`admin_teachers.php?action=get_user&user_type=teacher&user_id=${id}`);
            if (!res.ok) return;
            const user = await res.json();
            
            document.getElementById('form-title').textContent = 'Edit Teacher';
            document.getElementById('teacher-action').value = 'edit_teacher';
            document.getElementById('teacher-user-id').value = user.id || '';
            
            // Split full name into first/middle/last heuristically
            const full = (user.name || '').trim();
            const parts = full.split(' ').filter(Boolean);
            document.getElementById('teacher-first-name').value = parts[0] || '';
            document.getElementById('teacher-middle-initial').value = parts.length > 2 ? parts[1] : '';
            document.getElementById('teacher-last-name').value = parts.length > 1 ? parts[parts.length - 1] : '';
            document.getElementById('teacher-username').value = user.username || '';
            document.getElementById('teacher-email').value = user.email || '';
            
            // Handle multiple sections - check all assigned sections
            const assignedSectionIds = (user.section_ids || '').split(',').filter(Boolean);
            const checkboxes = document.querySelectorAll('input[name="section_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = assignedSectionIds.includes(checkbox.value);
            });
            
            // Hide password field for edit (admin cannot change passwords)
            const pwdContainer = document.querySelector('.password-input-container');
            const pwdInput = document.getElementById('teacher-password');
            if (pwdContainer) { pwdContainer.style.display = 'none'; }
            if (pwdInput) { pwdInput.required = false; } // Remove required attribute for edit
            const submit = document.getElementById('teacher-submit-btn');
            if (submit) submit.textContent = 'Save Changes';
            
            showForm();
        } catch(e) {
            console.error('Error fetching teacher data:', e);
        }
    }
    
    function deleteTeacher(id) {
        // Enhanced confirmation dialog
        const confirmed = confirm('⚠️ WARNING: This action cannot be undone!\n\nAre you sure you want to delete this teacher?\n\nThis will permanently remove:\n• Teacher account\n• All associated data\n• Section assignments');
        
        if (!confirmed) return;
        
        // Show loading state
        const deleteBtn = event.target;
        const originalText = deleteBtn.textContent;
        deleteBtn.textContent = 'Deleting...';
        deleteBtn.disabled = true;
        
        const form = document.createElement('form'); 
        form.method = 'POST'; 
        form.style.display = 'none';
        form.innerHTML = `
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${id}">
        `;
        document.body.appendChild(form); 
        form.submit();
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
    
    // Search functionality and form controls
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const clearSearchBtn = document.getElementById('clear-search-btn');
        const toggleBtn = document.getElementById('toggle-form-btn');
        const closeFormBtn = document.getElementById('close-form-btn');
        const cancelFormBtn = document.getElementById('cancel-form-btn');
        const formContainer = document.getElementById('teacher-form-container');
        
        if (searchInput && clearSearchBtn) {
            const toggleClearButton = () => { 
                clearSearchBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none'; 
            };
            searchInput.addEventListener('input', toggleClearButton);
            clearSearchBtn.addEventListener('click', () => { 
                searchInput.value = ''; 
                document.getElementById('search-form').submit(); 
            });
            toggleClearButton();
        }
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (formContainer.style.display === 'none') {
                    openAddTeacherForm();
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
    });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/includes/admin_layout.php';
?>
