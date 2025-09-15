<?php
// Utility functions for input validation and sanitization
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

function logError($message, $context = []) {
    $logFile = __DIR__ . '/logs/admin_errors.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Process login form submission
if (isset($_POST['login'])) {
    // Start session for login processing
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $username = sanitizeInput($_POST['Username'] ?? '');
    $password = $_POST['Password'] ?? '';
    
    // Validate input
    if (!validateInput($username, 'string', 50) || empty($password)) {
        $error_message = 'Please enter valid credentials.';
    } else {
        try {
            // Database connection
            $conn = new mysqli("localhost", "root", "", "compre_learn");
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
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
            
            // Check admin credentials from database
            $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, is_active FROM admins WHERE username = ? AND is_active = 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($admin = $result->fetch_assoc()) {
                if (password_verify($password, $admin['password_hash'])) {
                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $admin['id']);
                    $updateStmt->execute();
                    
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    
                    // Log successful login
                    logError('Admin login successful', ['admin_id' => $admin['id'], 'username' => $username]);
                    
                    // Redirect to dashboard
                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    logError('Admin login failed - invalid password', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
                    $error_message = 'Invalid credentials.';
                }
            } else {
                logError('Admin login failed - user not found', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
                $error_message = 'Invalid credentials.';
            }
        } catch (Exception $e) {
            logError('Admin login error', ['error' => $e->getMessage(), 'username' => $username]);
            $error_message = 'Login error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Compre Learn</title>
    <style>
        /* General Body Styles */
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background-color: #f0f2f5;
        }

        /* Panel Styles */
        .left-panel {
            width: 55%;
            height: 100%;
            background: url('../assets/images/login.jpg') no-repeat center center;
            background-size: cover;
        }

        .right-panel {
            width: 45%;
            height: 100%;
            background: linear-gradient(to bottom right,rgb(7, 7, 7),rgb(0, 0, 0));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            box-sizing: border-box;
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 350px;
        }

        /* Logo and Title */
        .logo {
            margin-bottom: 2rem;
            text-align: center;
        }

        .logo-image {
            max-width: 120px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .login-container h2 {
            font-size: 1.2rem;
            font-weight: normal;
            margin-bottom: 1rem;
            color: #f1f1f1;
        }

        /* Buttons */
        .login-buttons button, .login-form input[type="submit"], .back-btn {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            font-size: 1em;
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s, border-color 0.3s, transform 0.3s;
            font-weight: 500;
        }

        .login-buttons button:hover, .login-form input[type="submit"]:hover, .back-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
            transform: translateY(-2px);
        }

        /* Login Form Specifics */
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f1f1f1;
            color: #333;
        }

        .login-form input[type="text"]::placeholder,
        .login-form input[type="password"]::placeholder {
            color: #888;
        }

        .back-btn {
            background-color: rgba(0, 0, 0, 0.2);
            margin-top: 1rem;
        }

        .back-btn:hover {
            background-color: rgba(0, 0, 0, 0.4);
        }

        /* Page Transition Animations */
        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom right, rgb(0, 0, 0), rgb(0, 0, 0));
            z-index: 9999;
            transform: translateX(100%);
            transition: transform 0.6s ease-in-out;
        }

        .page-transition.active {
            transform: translateX(0);
        }

        /* Button click animation */
        .button-click {
            animation: buttonPulse 0.3s ease-out;
        }

        @keyframes buttonPulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }

        /* Fade in animation for page content */
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="login-container fade-in">
            <div class="logo">
                <img src="../assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Admin Login</h2>
            <?php if (isset($error_message)): ?>
                <div style="color: #ff6b6b; margin-bottom: 1rem; padding: 0.5rem; background: rgba(255, 107, 107, 0.1); border-radius: 4px; font-size: 0.9rem;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="" class="login-form">
                <input type="text" name="Username" placeholder="Username" required>
                <input type="password" name="Password" placeholder="Password" required>
                <input type="submit" name="login" value="Login">
            </form>
            <button class="back-btn" onclick="navigateWithAnimation('../login_as.php')">Back</button>
        </div>
    </div>

    <!-- Page Transition Overlay -->
    <div class="page-transition" id="pageTransition"></div>

    <script>
        function navigateWithAnimation(url) {
            event.target.classList.add('button-click');
            const transition = document.getElementById('pageTransition');
            transition.classList.add('active');
            setTimeout(() => { window.location.href = url; }, 300);
        }
        document.addEventListener('animationend', function(e) {
            if (e.animationName === 'buttonPulse') {
                e.target.classList.remove('button-click');
            }
        });
    </script>

</body>
</html>
