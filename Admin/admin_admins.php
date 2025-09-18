<?php
require_once __DIR__ . '/includes/admin_init.php';

$page_title = 'Admin Management';

// Handle AJAX request for user data
if (isset($_GET['action']) && $_GET['action'] == 'get_user') {
    header('Content-Type: application/json');
    
    $user_type = $_GET['user_type'];
    $user_id = $_GET['user_id'];
    
    if ($user_type === 'admin') {
        $sql = "SELECT * FROM admins WHERE id = ?";
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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_admin':
            // Validate and sanitize input
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $middle_initial = sanitizeInput($_POST['middle_initial'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate required fields
            if (!validateInput($first_name, 'string', 50) || !validateInput($last_name, 'string', 50) || 
                !validateInput($username, 'string', 50) || !validateInput($email, 'email', 100) || 
                empty($password) || strlen($password) < 6) {
                logError('Admin add failed - validation error', ['admin_id' => $adminId]);
                header("Location: admin_admins.php?error=validation_failed");
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
            $full_name = $first_name . $middle_formatted . ' ' . $last_name;
            
            // Check if username, email, or full name already exists
            $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ? OR full_name = ?");
            $checkStmt->bind_param("sss", $username, $email, $full_name);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($result->num_rows > 0) {
                $checkStmt->close();
                
                // Check which field is duplicate - check each field separately
                $checkUsername = $conn->prepare("SELECT id FROM admins WHERE username = ?");
                $checkUsername->bind_param("s", $username);
                $checkUsername->execute();
                $usernameResult = $checkUsername->get_result();
                $checkUsername->close();
                
                $checkEmail = $conn->prepare("SELECT id FROM admins WHERE email = ?");
                $checkEmail->bind_param("s", $email);
                $checkEmail->execute();
                $emailResult = $checkEmail->get_result();
                $checkEmail->close();
                
                $checkName = $conn->prepare("SELECT id FROM admins WHERE full_name = ?");
                $checkName->bind_param("s", $full_name);
                $checkName->execute();
                $nameResult = $checkName->get_result();
                $checkName->close();
                
                if ($usernameResult->num_rows > 0) {
                    logError('Admin add failed - duplicate username', ['username' => $username]);
                    header("Location: admin_admins.php?error=duplicate_username");
                } elseif ($emailResult->num_rows > 0) {
                    logError('Admin add failed - duplicate email', ['email' => $email]);
                    header("Location: admin_admins.php?error=duplicate_email");
                } else {
                    logError('Admin add failed - duplicate name', ['name' => $full_name]);
                    header("Location: admin_admins.php?error=duplicate_name");
                }
                exit();
            }
            $checkStmt->close();
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (full_name, username, email, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $username, $email, $hashed_password);
            
            if (!$stmt->execute()) {
                logError('Admin add failed - database error', ['error' => $stmt->error, 'admin_id' => $adminId]);
                header("Location: admin_admins.php?error=database_error");
                exit();
            }
            
            header("Location: admin_admins.php?success=admin_added");
            exit();
            break;
            
        case 'edit_admin':
            $first_name = $_POST['first_name'] ?? '';
            $middle_initial = $_POST['middle_initial'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $full_name = trim($first_name . ' ' . $middle_initial . ' ' . $last_name);
            $user_id = $_POST['user_id'] ?? null;
            
            // Validate required fields
            if (empty($user_id) || !validateInput($first_name, 'string', 50) || !validateInput($last_name, 'string', 50) || 
                !validateInput($_POST['username'], 'string', 50) || !validateInput($_POST['email'], 'email', 100)) {
                logError('Admin edit failed - validation error', ['admin_id' => $adminId, 'user_id' => $user_id]);
                header("Location: admin_admins.php?error=validation_failed");
                exit();
            }
            
            // Admin cannot change passwords - only update other information
            $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $_POST['username'], $_POST['email'], $user_id);
            
            if (!$stmt->execute()) {
                logError('Admin edit failed - database error', ['error' => $stmt->error, 'admin_id' => $adminId, 'user_id' => $user_id]);
                header("Location: admin_admins.php?error=database_error");
                exit();
            }
            
            header("Location: admin_admins.php?success=admin_updated");
            exit();
            break;
            
        case 'delete_user':
            $user_id = $_POST['user_id'];
            
            // Prevent admin from deleting themselves
            if ($user_id == $adminId) {
                logError('Admin delete failed - cannot delete self', ['admin_id' => $adminId]);
                header("Location: admin_admins.php?error=cannot_delete_self");
                exit();
            }
            
            $sql = "DELETE FROM admins WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            header("Location: admin_admins.php?success=admin_deleted");
            exit();
            break;
    }
}

// Get data for display
$search_term = $_GET['search'] ?? '';
$search_query = "%" . $search_term . "%";

$sql_admins = "SELECT * FROM admins WHERE full_name LIKE ? OR username LIKE ? ORDER BY created_at DESC";
$stmt_admins = $conn->prepare($sql_admins);
$stmt_admins->bind_param("ss", $search_query, $search_query);
$stmt_admins->execute();
$admins = $stmt_admins->get_result();

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
        min-height: 200px;
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
    
    .user-card .delete-btn:disabled {
        background: #bdc3c7;
        cursor: not-allowed;
    }
    
    .current-admin-badge {
        background: #27ae60;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 8px;
        display: inline-block;
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
    
    .form-actions {
        display: flex;
        gap: 12px;
        align-items: end;
    }
    
    .form-actions .submit-btn {
        flex: 1;
        margin-top: 0;
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
    
    /* Responsive Design */
    @media (max-width: 768px) {
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
        
        .user-card {
            width: 100%;
            max-width: 320px;
            margin: 0 auto 12px auto;
        }
    }
</style>

<div class="management-area">
    <?php if (isset($_GET['success'])): ?>
        <div class="success-message" id="success-message">
            <?php
            switch ($_GET['success']) {
                case 'admin_added':
                    echo 'Admin added successfully!';
                    break;
                case 'admin_updated':
                    echo 'Admin updated successfully!';
                    break;
                case 'admin_deleted':
                    echo 'Admin deleted successfully!';
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
                case 'duplicate_username':
                    echo 'Username already exists. Please choose a different username.';
                    break;
                case 'duplicate_email':
                    echo 'Email address already exists. Please use a different email.';
                    break;
                case 'duplicate_name':
                    echo 'An admin with this full name already exists. Please use different credentials.';
                    break;
                case 'cannot_delete_self':
                    echo 'You cannot delete your own admin account.';
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
        <form id="search-form" method="GET" action="admin_admins.php" class="search-container">
            <input type="text" name="search" id="search-input" class="search-bar" placeholder="Search admins by name or username" value="<?php echo h($search_term); ?>">
            <span id="clear-search-btn" class="clear-btn">&times;</span>
            <button type="submit" class="search-btn">Search</button>
        </form>
        <button id="toggle-form-btn" class="add-btn">Add Admin</button>
    </div>

    <!-- Inline Add/Edit Form -->
    <div id="admin-form-container" class="form-container" style="display: none;">
        <div class="form-header">
            <h3 id="form-title">Add New Admin</h3>
            <button id="close-form-btn" class="close-form-btn">&times;</button>
        </div>
        <form id="admin-form" method="POST">
            <?php echo csrf_token(); ?>
            <input type="hidden" name="action" id="admin-action" value="add_admin">
            <input type="hidden" name="user_id" id="admin-user-id" value="">
            <div class="form-row">
                <div class="form-group"><label>First Name:</label><input type="text" id="admin-first-name" name="first_name" required></div>
                <div class="form-group"><label>Middle Initial:</label><input type="text" id="admin-middle-initial" name="middle_initial" maxlength="1" placeholder="M"></div>
                <div class="form-group"><label>Last Name:</label><input type="text" id="admin-last-name" name="last_name" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Username:</label><input type="text" id="admin-username" name="username" required></div>
                <div class="form-group"><label>Email:</label><input type="email" id="admin-email" name="email" required></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="admin-password" name="password" required>
                        <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('admin-password')"></i>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" id="admin-submit-btn" class="submit-btn">Add Admin</button>
                <button type="button" id="cancel-form-btn" class="cancel-btn">Cancel</button>
            </div>
        </form>
    </div>

    <div class="card-table-container">
        <?php while($admin = $admins->fetch_assoc()): ?>
        <div class="user-card">
            <div>
                <?php if ($admin['id'] == $adminId): ?>
                <div class="current-admin-badge">Current Admin</div>
                <?php endif; ?>
                <div class="card-title"><?php echo h($admin['full_name']); ?></div>
                <div class="card-field"><strong>Username:</strong> <?php echo h($admin['username'] ?? ''); ?></div>
                <div class="card-field"><strong>Email:</strong> <?php echo h($admin['email'] ?? ''); ?></div>
                <div class="card-field"><strong>Status:</strong> <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?></div>
                <div class="card-field"><strong>Last Login:</strong> <?php echo $admin['last_login'] ? date('F j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?></div>
                <div class="card-field"><strong>Created:</strong> <?php echo date('F j, Y', strtotime($admin['created_at'])); ?></div>
            </div>
            <div class="card-actions">
                <button class="action-btn edit-btn" onclick="editAdmin(<?php echo $admin['id']; ?>)">Edit</button>
                <button class="action-btn delete-btn" onclick="deleteAdmin(<?php echo $admin['id']; ?>)" <?php echo ($admin['id'] == $adminId) ? 'disabled' : ''; ?>>Delete</button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
    function showForm() {
        document.getElementById('admin-form-container').style.display = 'block';
        document.getElementById('toggle-form-btn').textContent = 'Hide Form';
    }
    
    function hideForm() {
        document.getElementById('admin-form-container').style.display = 'none';
        document.getElementById('toggle-form-btn').textContent = 'Add Admin';
        resetForm();
    }
    
    function resetForm() {
        document.getElementById('form-title').textContent = 'Add New Admin';
        document.getElementById('admin-action').value = 'add_admin';
        document.getElementById('admin-user-id').value = '';
        document.getElementById('admin-first-name').value = '';
        document.getElementById('admin-middle-initial').value = '';
        document.getElementById('admin-last-name').value = '';
        document.getElementById('admin-username').value = '';
        document.getElementById('admin-email').value = '';
        const pwd = document.getElementById('admin-password');
        if (pwd) { 
            pwd.required = true; 
            pwd.value = ''; 
            pwd.type = 'password';
        }
        
        // Show password field for new admin
        const pwdContainer = document.querySelector('.password-input-container');
        if (pwdContainer) { pwdContainer.style.display = 'block'; }
        const submit = document.getElementById('admin-submit-btn');
        if (submit) submit.textContent = 'Add Admin';
    }
    
    function openAddAdminForm() {
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
    
    async function editAdmin(id) {
        try {
            const res = await fetch(`admin_admins.php?action=get_user&user_type=admin&user_id=${id}`);
            if (!res.ok) return;
            const user = await res.json();
            
            document.getElementById('form-title').textContent = 'Edit Admin';
            document.getElementById('admin-action').value = 'edit_admin';
            document.getElementById('admin-user-id').value = user.id || '';
            
            // Split the name into parts
            const nameParts = (user.full_name || '').split(' ').filter(Boolean);
            document.getElementById('admin-first-name').value = nameParts[0] || '';
            document.getElementById('admin-middle-initial').value = nameParts.length > 2 ? nameParts[1] : '';
            document.getElementById('admin-last-name').value = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
            
            document.getElementById('admin-username').value = user.username || '';
            document.getElementById('admin-email').value = user.email || '';
            
            // Hide password field for edit (admin cannot change passwords)
            const pwdContainer = document.querySelector('.password-input-container');
            if (pwdContainer) { pwdContainer.style.display = 'none'; }
            const passwordInput = document.getElementById('admin-password');
            if (passwordInput) { 
                passwordInput.required = false;
                passwordInput.value = 'dummy_password';
            }
            const submit = document.getElementById('admin-submit-btn');
            if (submit) submit.textContent = 'Save Changes';
            
            showForm();
        } catch(e) {
            console.error('Error fetching admin data:', e);
        }
    }
    
    function deleteAdmin(id) {
        // Enhanced confirmation dialog
        const confirmed = confirm('⚠️ WARNING: This action cannot be undone!\n\nAre you sure you want to delete this admin?\n\nThis will permanently remove:\n• Admin account\n• All associated data\n• Access permissions');
        
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
            }, 5000);
        }
        
        if (errorMessage) {
            setTimeout(() => {
                closeMessage('error-message');
            }, 8000);
        }
    }
    
    // Search functionality and form controls
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const clearSearchBtn = document.getElementById('clear-search-btn');
        const toggleBtn = document.getElementById('toggle-form-btn');
        const closeFormBtn = document.getElementById('close-form-btn');
        const cancelFormBtn = document.getElementById('cancel-form-btn');
        const formContainer = document.getElementById('admin-form-container');
        
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
                    openAddAdminForm();
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
