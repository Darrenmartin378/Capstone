<?php
session_start();

// Handle error messages
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_message = 'Invalid credentials. Please check your username and password.';
            break;
        case '2':
            $error_message = 'Login error. Please try again.';
            break;
        case '3':
            $error_message = 'Please enter valid credentials.';
            break;
        default:
            $error_message = 'An error occurred. Please try again.';
    }
}

// Process login form submission
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        header('Location: simple_working_login.php?error=3');
        exit();
    }
    
    // Simple admin check first
    if ($username === 'admin' && $password === 'admin123') {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = 'admin';
        $_SESSION['admin_name'] = 'System Administrator';
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Redirect to admin dashboard
        header('Location: Admin/admin_dashboard.php');
        exit();
    }
    
    // If not admin, try database check
    try {
        $conn = new mysqli("localhost", "root", "", "compre_learn");
        if ($conn->connect_error) {
            header('Location: simple_working_login.php?error=2');
            exit();
        }
        
        // Check Admin credentials
        $stmt = $conn->prepare("SELECT id, username, password_hash, full_name FROM admins WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($admin = $result->fetch_assoc()) {
            if (password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                session_regenerate_id(true);
                header('Location: Admin/admin_dashboard.php');
                exit();
            }
        }
        
        // Check Teacher credentials
        $stmt = $conn->prepare("SELECT id, name, password FROM teachers WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $teacher = $result->fetch_assoc();
            if (password_verify($password, $teacher['password'])) {
                $_SESSION['teacher_logged_in'] = true;
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
                session_regenerate_id(true);
                header("Location: Teacher/teacher_dashboard.php");
                exit();
            }
        }
        
        // Check Student credentials
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_number = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['student_logged_in'] = true;
                $_SESSION['student_id'] = $row['id'];
                $_SESSION['student_name'] = $row['name'];
                session_regenerate_id(true);
                header('Location: Student/student_dashboard.php');
                exit();
            }
        }
        
        // Check Parent credentials
        $stmt = $conn->prepare("SELECT * FROM parents WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['parent_logged_in'] = true;
                $_SESSION['parent_id'] = $row['id'];
                $_SESSION['parent_name'] = $row['name'];
                $_SESSION['parent_username'] = $row['username'];
                session_regenerate_id(true);
                header('Location: parent_dashboard.php');
                exit();
            }
        }
        
        // If authentication failed for all user types
        header('Location: simple_working_login.php?error=1');
        exit();
        
    } catch (Exception $e) {
        header('Location: simple_working_login.php?error=2');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compre Learn - Simple Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h1 {
            color: #2d3748;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .login-header p {
            color: #718096;
            font-size: 1.1rem;
        }

        .login-form {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #a0aec0;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #e53e3e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .test-info {
            background: rgba(56, 161, 105, 0.1);
            border: 1px solid rgba(56, 161, 105, 0.3);
            color: #2d855a;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 40px 30px;
            }

            .login-header h1 {
                font-size: 2rem;
            }

            .form-group input {
                padding: 14px 16px;
                font-size: 16px;
            }

            .login-btn {
                padding: 14px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Simple Login</h1>
            <p>Compre Learn System</p>
        </div>
        
        <div class="test-info">
            <strong>Test Credentials:</strong><br>
            Admin: admin / admin123
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username or Student Number" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Sign In</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="comprehensive_login_debug.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Debug Login Issues</a>
        </div>
    </div>
</body>
</html>
