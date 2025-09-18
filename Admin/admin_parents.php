<?php
require_once __DIR__ . '/includes/admin_init.php';

$page_title = 'Parents Management';

// Handle automatic parent registration flow
$autoRegister = isset($_GET['auto_register']) && $_GET['auto_register'] === 'true';
$pendingRegistration = $_SESSION['pending_parent_registration'] ?? null;

// If auto-register is requested but no pending registration, redirect back to students
if ($autoRegister && !$pendingRegistration) {
    header("Location: admin_students.php?error=no_pending_registration");
    exit();
}

// Handle AJAX request for user data
if (isset($_GET['action']) && $_GET['action'] == 'get_user') {
    header('Content-Type: application/json');
    
    $user_type = $_GET['user_type'];
    $user_id = $_GET['user_id'];
    
    if ($user_type === 'parent') {
        $sql = "SELECT * FROM parents WHERE id = ?";
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
        case 'add_parent':
            // Validate and sanitize input
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $middle_initial = sanitizeInput($_POST['middle_initial'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $student_id_fk = $_POST['student_id'] ?? '';
            $username = sanitizeInput($_POST['username'] ?? '');
            $plain_password = $_POST['password'] ?? '';
            $auto = isset($_POST['auto_generate']) && $_POST['auto_generate'] === '1';
            
            // Validate required fields
            if (!validateInput($first_name, 'string', 50) || !validateInput($last_name, 'string', 50) || 
                !validateInput($email, 'email', 100) || !validateInput($student_id_fk, 'int') || 
                (!$auto && (!validateInput($username, 'string', 50) || empty($plain_password) || strlen($plain_password) < 6))) {
                logError('Parent add failed - validation error', ['admin_id' => $adminId]);
                header("Location: admin_parents.php?error=validation_failed");
                exit();
            }
            
            // Construct the full name consistently BEFORE checking for duplicates
            $first_name = trim($first_name);
            $middle_initial = trim($middle_initial);
            $last_name = trim($last_name);
            
            $middle_formatted = '';
            if (!empty($middle_initial)) {
                $middle_formatted = ' ' . strtoupper($middle_initial) . '.';
            }
            $name = $first_name . $middle_formatted . ' ' . $last_name;
            
            // Check if username, email, or full name already exists (if not auto-generating)
            if (!$auto) {
                $checkStmt = $conn->prepare("SELECT id FROM parents WHERE username = ? OR email = ? OR name = ?");
                $checkStmt->bind_param("sss", $username, $email, $name);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                if ($result->num_rows > 0) {
                    $checkStmt->close();
                    
                    // Check which field is duplicate - check each field separately
                    $checkUsername = $conn->prepare("SELECT id FROM parents WHERE username = ?");
                    $checkUsername->bind_param("s", $username);
                    $checkUsername->execute();
                    $usernameResult = $checkUsername->get_result();
                    $checkUsername->close();
                    
                    $checkEmail = $conn->prepare("SELECT id FROM parents WHERE email = ?");
                    $checkEmail->bind_param("s", $email);
                    $checkEmail->execute();
                    $emailResult = $checkEmail->get_result();
                    $checkEmail->close();
                    
                    $checkName = $conn->prepare("SELECT id FROM parents WHERE name = ?");
                    $checkName->bind_param("s", $name);
                    $checkName->execute();
                    $nameResult = $checkName->get_result();
                    $checkName->close();
                    
                    if ($usernameResult->num_rows > 0) {
                        logError('Parent add failed - duplicate username', ['username' => $username]);
                        header("Location: admin_parents.php?error=duplicate_username");
                    } elseif ($emailResult->num_rows > 0) {
                        logError('Parent add failed - duplicate email', ['email' => $email]);
                        header("Location: admin_parents.php?error=duplicate_email");
                    } else {
                        logError('Parent add failed - duplicate name', ['name' => $name]);
                        header("Location: admin_parents.php?error=duplicate_name");
                    }
                    exit();
                }
                $checkStmt->close();
            }
            
            // Always check for duplicate email and name even with auto-generation
            $checkEmailStmt = $conn->prepare("SELECT id FROM parents WHERE email = ?");
            $checkEmailStmt->bind_param("s", $email);
            $checkEmailStmt->execute();
            if ($checkEmailStmt->get_result()->num_rows > 0) {
                logError('Parent add failed - duplicate email', ['email' => $email]);
                header("Location: admin_parents.php?error=duplicate_email");
                $checkEmailStmt->close();
                exit();
            }
            $checkEmailStmt->close();
            
            $checkNameStmt = $conn->prepare("SELECT id FROM parents WHERE name = ?");
            $checkNameStmt->bind_param("s", $name);
            $checkNameStmt->execute();
            if ($checkNameStmt->get_result()->num_rows > 0) {
                logError('Parent add failed - duplicate name', ['name' => $name]);
                header("Location: admin_parents.php?error=duplicate_name");
                $checkNameStmt->close();
                exit();
            }
            $checkNameStmt->close();
            
            // Check if the selected student is already linked to another parent
            $checkStudentStmt = $conn->prepare("SELECT id, name FROM parents WHERE student_id = ?");
            $checkStudentStmt->bind_param("i", $student_id_fk);
            $checkStudentStmt->execute();
            $studentResult = $checkStudentStmt->get_result();
            if ($studentResult->num_rows > 0) {
                $existingParent = $studentResult->fetch_assoc();
                logError('Parent add failed - student already linked', ['student_id' => $student_id_fk, 'existing_parent' => $existingParent['name']]);
                header("Location: admin_parents.php?error=student_already_linked");
                $checkStudentStmt->close();
                exit();
            }
            $checkStudentStmt->close();

            if ($auto || $username === '' || $plain_password === '') {
                // Generate username based on student id or token
                $username_base_token = (string)$student_id_fk;
                $proposed_username_base = 'parent_' . $username_base_token;
                $proposed_username = $proposed_username_base; 
                $suffix = 1; 
                $check_stmt = $conn->prepare("SELECT id FROM parents WHERE username = ? LIMIT 1");
                while (true) { 
                    $check_stmt->bind_param("s", $proposed_username); 
                    $check_stmt->execute(); 
                    $check_stmt->store_result(); 
                    if ($check_stmt->num_rows === 0) { break; } 
                    $proposed_username = $proposed_username_base . $suffix; 
                    $suffix++; 
                }
                $check_stmt->close();
                $username = $proposed_username;
                $pwd_chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
                $plain_password = '';
                for ($i = 0; $i < 10; $i++) { 
                    $plain_password .= $pwd_chars[random_int(0, strlen($pwd_chars) - 1)]; 
                }
            }
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO parents (name, username, email, student_id, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $name, $username, $email, $student_id_fk, $hashed_password);
            
            if (!$stmt->execute()) {
                logError('Parent add failed - database error', ['error' => $stmt->error, 'admin_id' => $adminId]);
                header("Location: admin_parents.php?error=database_error");
                exit();
            }
            
            // Store generated credentials for display after redirect
            $_SESSION['new_parent_credentials'] = [ 
                'parent_id' => $conn->insert_id, 
                'username' => $username, 
                'password' => $plain_password 
            ];
            
            // Clear pending registration if this was part of the two-step process
            if (isset($_SESSION['pending_parent_registration'])) {
                unset($_SESSION['pending_parent_registration']);
            }
            
            header("Location: admin_parents.php?success=parent_added");
            exit();
            break;
            
        case 'edit_parent':
            $first_name = $_POST['first_name'] ?? '';
            $middle_initial = $_POST['middle_initial'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $name = trim($first_name . ' ' . $middle_initial . ' ' . $last_name);
            // Admin cannot change user passwords - only update other information
            $stmt = $conn->prepare("UPDATE parents SET name = ?, username = ?, email = ?, student_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $_POST['username'], $_POST['email'], $_POST['student_id'], $_POST['user_id']);
            $stmt->execute();
            
            if (isset($_SESSION['new_parent_credentials']) && isset($_SESSION['new_parent_credentials']['parent_id']) && (int)$_SESSION['new_parent_credentials']['parent_id'] === (int)$_POST['user_id']) {
                $creds = $_SESSION['new_parent_credentials'];
                $toEmail = $_POST['email'] ?? '';
                if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) && strpos($toEmail, '@noemail.local') === false) {
                    try {
                        $mail = new PHPMailer(true);
                        if (getenv('SMTP_HOST')) {
                            $mail->isSMTP();
                            $mail->Host = getenv('SMTP_HOST');
                            $mail->SMTPAuth = getenv('SMTP_AUTH') ? true : false;
                            if ($mail->SMTPAuth) { 
                                $mail->Username = getenv('SMTP_USER') ?: ''; 
                                $mail->Password = getenv('SMTP_PASS') ?: ''; 
                            }
                            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = getenv('SMTP_PORT') ?: 587;
                        } else { 
                            $mail->isMail(); 
                        }
                        $mail->setFrom('no-reply@comprelearn.local', 'Compre Learn');
                        $mail->addAddress($toEmail);
                        $mail->Subject = 'Your Parent Account Credentials';
                        $mail->isHTML(true);
                        $mail->Body = '<p>Your parent account has been created.</p>' .
                                      '<p><strong>Username:</strong> ' . htmlspecialchars($creds['username']) . '<br>' .
                                      '<strong>Password:</strong> ' . htmlspecialchars($creds['password']) . '</p>' .
                                      '<p>Please log in and change your password immediately.</p>';
                        $mail->AltBody = "Username: {$creds['username']}\nPassword: {$creds['password']}";
                        $mail->send();
                    } catch (Exception $e) { }
                }
                unset($_SESSION['new_parent_credentials']);
            }
            header("Location: admin_parents.php?success=parent_updated");
            exit();
            break;
            
        case 'delete_user':
            $user_id = $_POST['user_id'];
            $sql = "DELETE FROM parents WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            header("Location: admin_parents.php?success=parent_deleted");
            exit();
            break;
    }
}

// Get data for display
$search_term = $_GET['search'] ?? '';
$search_query = "%" . $search_term . "%";
$students = $conn->query("SELECT * FROM students WHERE id NOT IN (SELECT student_id FROM parents WHERE student_id IS NOT NULL) ORDER BY name");

$sql_parents = "SELECT p.*, s.name as student_name FROM parents p LEFT JOIN students s ON p.student_id = s.id WHERE p.name LIKE ? OR p.username LIKE ? ORDER BY p.created_at DESC";
$stmt_parents = $conn->prepare($sql_parents);
$stmt_parents->bind_param("ss", $search_query, $search_query);
$stmt_parents->execute();
$parents = $stmt_parents->get_result();

// Start output buffering
ob_start();
?>

<style>
    .management-area {
        background: var(--gmail-white);
        color: var(--gmail-text);
        padding: 2rem;
        border-radius: 14px;
        box-shadow: var(--gmail-shadow);
        animation: fadeIn .7s;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--gmail-primary);
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
        border: 2px solid var(--gmail-primary);
        background: var(--gmail-white);
        color: var(--gmail-text);
        font-size: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        transition: border-color .2s;
    }
    
    .search-bar:focus { 
        border-color: var(--gmail-secondary); 
        outline: none; 
    }
    
    .search-btn {
        position: absolute; 
        top: 0; 
        right: 0; 
        height: 100%; 
        width: 80px;
        background: var(--gmail-primary);
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
        background: var(--gmail-secondary); 
    }
    
    .clear-btn {
        position: absolute; 
        top: 50%; 
        right: 90px;
        transform: translateY(-50%);
        background: none; 
        border: none;
        color: var(--gmail-text-secondary);
        font-size: 1.7rem; 
        cursor: pointer;
        display: none; 
        line-height: 1;
    }
    
    .add-btn {
        background: var(--gmail-secondary);
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
        background: #d33b2c; 
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
        background: var(--gmail-white);
        box-shadow: var(--gmail-shadow);
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
        box-shadow: 0 10px 32px rgba(234,67,53,0.08), var(--gmail-shadow);
        transform: translateY(-2px) scale(1.02);
    }
    
    .user-card .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--gmail-primary);
        margin-bottom: 8px;
    }
    
    .user-card .card-field {
        font-size: 1rem;
        color: var(--gmail-text-secondary);
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
        align-items: center;
        gap: 8px;
        margin-top: 8px;
    }
    
    .checkbox-group input[type="checkbox"] {
        margin: 0;
        width: auto;
    }
    
    .checkbox-group label {
        margin: 0;
        font-size: 14px;
        color: #666;
        cursor: pointer;
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
    
    .credentials-display {
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 8px;
        padding: 12px;
        margin-top: 12px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .credentials-display strong {
        color: #856404;
        font-weight: bold;
    }
    
    .credentials-display em {
        color: #856404;
        font-style: italic;
        font-size: 12px;
    }
    
    .success-message::before {
        content: "\f00c";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 1.2rem;
    }
    
    .error-message {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .error-message::before {
        content: "\f071";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
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
    
    .info-message {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        animation: slideIn 0.3s ease-out;
    }
    
    .info-message::before {
        content: "ℹ";
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .info-message .message-close {
        color: #0c5460;
    }
    
    .info-message .message-close:hover {
        background: #0c5460;
        color: #d1ecf1;
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
                case 'parent_added':
                    echo 'Parent added successfully!';
                    // Display generated credentials if they exist
                    if (isset($_SESSION['new_parent_credentials'])) {
                        $creds = $_SESSION['new_parent_credentials'];
                        echo '<div class="credentials-display">';
                        echo '<strong>Generated Credentials:</strong><br>';
                        echo '<strong>Username:</strong> <span id="generated-username">' . h($creds['username']) . '</span><br>';
                        echo '<strong>Password:</strong> <span id="generated-password">' . h($creds['password']) . '</span><br>';
                        echo '<button type="button" onclick="copyCredentials()" style="margin-top: 8px; padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">📋 Copy Credentials</button><br>';
                        echo '<em>Please save these credentials securely. They will not be shown again.</em>';
                        echo '</div>';
                        // Clear the credentials from session after displaying
                        unset($_SESSION['new_parent_credentials']);
                    }
                    break;
                case 'parent_updated':
                    echo 'Parent updated successfully!';
                    break;
                case 'parent_deleted':
                    echo 'Parent deleted successfully!';
                    break;
                case 'student_and_parent_added':
                    echo 'Student and parent added successfully!';
                    break;
            }
            ?>
            <button class="message-close" onclick="closeMessage('success-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($autoRegister && $pendingRegistration): ?>
        <div class="info-message" id="info-message">
            <strong>Step 2: Register Parent for <?php echo h($pendingRegistration['student_name']); ?></strong><br>
            Please fill in the parent information below. Username and password will be generated automatically.
            <button class="message-close" onclick="closeMessage('info-message')">&times;</button>
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
                case 'duplicate_username':
                    echo 'Username already exists. Please choose a different username.';
                    break;
                case 'duplicate_email':
                    echo 'Email address already exists. Please use a different email.';
                    break;
                case 'duplicate_name':
                    echo 'A parent with this full name already exists. Please use different credentials.';
                    break;
                case 'student_already_linked':
                    echo 'This student is already linked to another parent. Please select a different student.';
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
        <form id="search-form" method="GET" action="admin_parents.php" class="search-container">
            <input type="text" name="search" id="search-input" class="search-bar" placeholder="Search parents by name or username" value="<?php echo h($search_term); ?>">
            <span id="clear-search-btn" class="clear-btn">&times;</span>
            <button type="submit" class="search-btn">Search</button>
        </form>
        <button id="toggle-form-btn" class="add-btn">Add Parent</button>
    </div>

    <!-- Inline Add/Edit Form -->
    <div id="parent-form-container" class="form-container" style="display: none;">
        <div class="form-header">
            <h3 id="form-title">Add New Parent</h3>
            <button id="close-form-btn" class="close-form-btn">&times;</button>
        </div>
        <form id="parent-form" method="POST">
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" id="parent-action" value="add_parent">
            <input type="hidden" name="user_id" id="parent-user-id" value="">
            <div class="form-row">
                <div class="form-group"><label>First Name:</label><input type="text" id="parent-first-name" name="first_name" required></div>
                <div class="form-group"><label>Middle Initial:</label><input type="text" id="parent-middle-initial" name="middle_initial" maxlength="1" placeholder="M"></div>
                <div class="form-group"><label>Last Name:</label><input type="text" id="parent-last-name" name="last_name" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Username:</label><input type="text" id="parent-username" name="username" required></div>
                <div class="form-group"><label>Email:</label><input type="email" id="parent-email" name="email" required></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Linked Student:</label>
                    <select id="parent-student-id" name="student_id">
                        <option value="">Select Student</option>
                        <?php 
                        $students_for_parents = $conn->query("SELECT id, name FROM students WHERE id NOT IN (SELECT student_id FROM parents WHERE student_id IS NOT NULL) ORDER BY name");
                        $available_students = 0;
                        while($student = $students_for_parents->fetch_assoc()): 
                            $available_students++;
                        ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo ($pendingRegistration && $pendingRegistration['student_id'] == $student['id']) ? 'selected' : ''; ?>><?php echo h($student['name']); ?></option>
                        <?php endwhile; ?>
                        <?php if ($available_students == 0): ?>
                        <option value="" disabled>No available students (all students already have parents)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="parent-password" name="password" required>
                        <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('parent-password')"></i>
                    </div>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="auto-generate-credentials" name="auto_generate" value="1" checked>
                    <label for="auto-generate-credentials">Auto-generate username and password</label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-actions">
                    <button type="submit" id="parent-submit-btn" class="submit-btn">Add Parent</button>
                    <button type="button" id="cancel-form-btn" class="cancel-btn">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card-table-container">
        <?php while($parent = $parents->fetch_assoc()): ?>
        <div class="user-card">
            <div>
                <div class="card-title"><?php echo h($parent['name']); ?></div>
                <div class="card-field"><strong>Username:</strong> <?php echo h($parent['username'] ?? ''); ?></div>
                <div class="card-field"><strong>Email:</strong> <?php echo h($parent['email'] ?? ''); ?></div>
                <div class="card-field"><strong>Linked Student:</strong> <?php echo h($parent['student_name'] ?? 'N/A'); ?></div>
                <div class="card-field"><strong>Registered:</strong> <?php echo date('F j, Y', strtotime($parent['created_at'])); ?></div>
            </div>
            <div class="card-actions">
                <button class="action-btn edit-btn" onclick="editParent(<?php echo $parent['id']; ?>)">Edit</button>
                <button class="action-btn delete-btn" onclick="deleteParent(<?php echo $parent['id']; ?>)">Delete</button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>


<script>
    function showForm() {
        document.getElementById('parent-form-container').style.display = 'block';
        document.getElementById('toggle-form-btn').textContent = 'Hide Form';
    }
    
    function hideForm() {
        document.getElementById('parent-form-container').style.display = 'none';
        document.getElementById('toggle-form-btn').textContent = 'Add Parent';
        resetForm();
    }
    
    function resetForm() {
        document.getElementById('form-title').textContent = 'Add New Parent';
        document.getElementById('parent-action').value = 'add_parent';
        document.getElementById('parent-user-id').value = '';
        document.getElementById('parent-first-name').value = '';
        document.getElementById('parent-middle-initial').value = '';
        document.getElementById('parent-last-name').value = '';
        document.getElementById('parent-username').value = '';
        document.getElementById('parent-email').value = '';
        document.getElementById('parent-student-id').value = '';
        const pwd = document.getElementById('parent-password');
        if (pwd) { pwd.required = true; pwd.value = ''; }
        
        // Show password field for new parent
        const pwdContainer = document.querySelector('.password-input-container');
        if (pwdContainer) { pwdContainer.style.display = 'block'; }
        const submit = document.getElementById('parent-submit-btn');
        if (submit) submit.textContent = 'Add Parent';
    }
    
    function openAddParentForm() {
        resetForm();
        showForm();
    }
    
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.parentElement.querySelector('.password-toggle-icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash password-toggle-icon';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye password-toggle-icon';
        }
    }
    
    async function editParent(id) {
        try {
            const res = await fetch(`admin_parents.php?action=get_user&user_type=parent&user_id=${id}`);
            if (!res.ok) return;
            const user = await res.json();
            
            document.getElementById('form-title').textContent = 'Edit Parent';
            document.getElementById('parent-action').value = 'edit_parent';
            document.getElementById('parent-user-id').value = user.id || '';
            
            // Split the name into parts
            const nameParts = (user.name || '').split(' ').filter(Boolean);
            document.getElementById('parent-first-name').value = nameParts[0] || '';
            document.getElementById('parent-middle-initial').value = nameParts.length > 2 ? nameParts[1] : '';
            document.getElementById('parent-last-name').value = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
            
            document.getElementById('parent-username').value = user.username || '';
            document.getElementById('parent-email').value = user.email || '';
            document.getElementById('parent-student-id').value = user.student_id || '';
            
            // Hide password field for edit (admin cannot change passwords)
            const pwdContainer = document.querySelector('.password-input-container');
            if (pwdContainer) { pwdContainer.style.display = 'none'; }
            const submit = document.getElementById('parent-submit-btn');
            if (submit) submit.textContent = 'Save Changes';
            
            showForm();
        } catch(e) {
            console.error('Error fetching parent data:', e);
        }
    }
    
    function deleteParent(id) {
        // Enhanced confirmation dialog
        const confirmed = confirm('⚠️ WARNING: This action cannot be undone!\n\nAre you sure you want to delete this parent?\n\nThis will permanently remove:\n• Parent account\n• All associated data\n• Student connections');
        
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
    
    function copyCredentials() {
        const username = document.getElementById('generated-username');
        const password = document.getElementById('generated-password');
        
        if (username && password) {
            const credentials = `Username: ${username.textContent}\nPassword: ${password.textContent}`;
            
            // Try to use the modern clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(credentials).then(() => {
                    showCopyFeedback('Credentials copied to clipboard!');
                }).catch(() => {
                    fallbackCopyTextToClipboard(credentials);
                });
            } else {
                fallbackCopyTextToClipboard(credentials);
            }
        }
    }
    
    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyFeedback('Credentials copied to clipboard!');
            } else {
                showCopyFeedback('Failed to copy. Please copy manually.');
            }
        } catch (err) {
            showCopyFeedback('Failed to copy. Please copy manually.');
        }
        
        document.body.removeChild(textArea);
    }
    
    function showCopyFeedback(message) {
        // Create a temporary feedback element
        const feedback = document.createElement('div');
        feedback.textContent = message;
        feedback.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 10000;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        `;
        
        document.body.appendChild(feedback);
        
        // Remove after 3 seconds
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.parentNode.removeChild(feedback);
            }
        }, 3000);
    }
    
    function autoDismissMessages() {
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        const infoMessage = document.getElementById('info-message');
        
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
        
        if (infoMessage) {
            setTimeout(() => {
                closeMessage('info-message');
            }, 10000); // Auto-dismiss after 10 seconds (info messages stay longer)
        }
    }
    
    function handleAutoRegistration() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_register') === 'true') {
            // Automatically show the form
            showForm();
            
            // Auto-generate credentials
            const autoGenerateCheckbox = document.getElementById('auto-generate-credentials');
            if (autoGenerateCheckbox) {
                autoGenerateCheckbox.checked = true;
                toggleAutoGenerate();
            }
        }
    }
    
    function toggleAutoGenerate() {
        const autoGenerate = document.getElementById('auto-generate-credentials');
        const usernameField = document.getElementById('parent-username');
        const passwordField = document.getElementById('parent-password');
        
        if (autoGenerate && usernameField && passwordField) {
            if (autoGenerate.checked) {
                usernameField.disabled = true;
                passwordField.disabled = true;
                usernameField.placeholder = 'Will be generated automatically';
                passwordField.placeholder = 'Will be generated automatically';
            } else {
                usernameField.disabled = false;
                passwordField.disabled = false;
                usernameField.placeholder = '';
                passwordField.placeholder = '';
            }
        }
    }
    
    // Search functionality and form controls
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const clearSearchBtn = document.getElementById('clear-search-btn');
        const toggleBtn = document.getElementById('toggle-form-btn');
        const closeFormBtn = document.getElementById('close-form-btn');
        const cancelFormBtn = document.getElementById('cancel-form-btn');
        const formContainer = document.getElementById('parent-form-container');
        
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
                    openAddParentForm();
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
        
        // Handle auto-generation checkbox
        const autoGenerateCheckbox = document.getElementById('auto-generate-credentials');
        if (autoGenerateCheckbox) {
            autoGenerateCheckbox.addEventListener('change', toggleAutoGenerate);
            // Initialize the state
            toggleAutoGenerate();
        }
        
        // Handle auto-registration flow
        handleAutoRegistration();
        
        // Auto-dismiss messages
        autoDismissMessages();
    });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/includes/admin_layout.php';
?>
