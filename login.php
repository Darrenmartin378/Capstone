<?php
session_start();

// Handle login processing
if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=empty');
        exit;
    }
    
    // Connect to database for real authentication
    $conn = new mysqli('localhost', 'root', '', 'compre_learn');
    if ($conn->connect_error) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=database');
        exit;
    }
    
    // Check if user exists in any of the user tables
    $user = null;
    $user_type = null;
    
    // Check admins table
    $stmt = $conn->prepare("SELECT id, password_hash FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_type = 'admin';
    }
    
    // Check teachers table if not found in admins
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, password FROM teachers WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_type = 'teacher';
        }
    }
    
    // Check students table if not found in teachers
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, password, name FROM students WHERE student_number = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_type = 'student';
        }
    }
    
    // Check parents table if not found in students
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, password FROM parents WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_type = 'parent';
        }
    }
    
    if ($user) {
        // Verify password (assuming it's hashed in database)
        // For admins, use password_hash; for others, use password
        $password_column = ($user_type === 'admin') ? 'password_hash' : 'password';
        if (password_verify($password, $user[$password_column])) {
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_id'] = $user['id'];
            
            // Set specific session variables for each user type
            switch ($user_type) {
                case 'admin':
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $username;
                    break;
                case 'teacher':
                    $_SESSION['teacher_logged_in'] = true;
                    $_SESSION['teacher_id'] = $user['id'];
                    $_SESSION['teacher_name'] = $username;
                    break;
                case 'student':
                    $_SESSION['student_logged_in'] = true;
                    $_SESSION['student_id'] = $user['id'];
                    $_SESSION['student_name'] = $user['name'];
                    break;
                case 'parent':
                    $_SESSION['parent_logged_in'] = true;
                    $_SESSION['parent_id'] = $user['id'];
                    $_SESSION['parent_name'] = $username;
                    break;
            }
        
            // Redirect based on user type
            switch ($user_type) {
                case 'admin':
                    header('Location: Admin/admin_dashboard.php?login=success');
                    break;
                case 'teacher':
                    header('Location: Teacher/teacher_dashboard.php?login=success');
                    break;
                case 'student':
                    header('Location: Student/student_dashboard.php?login=success');
                    break;
                case 'parent':
                    header('Location: parent_dashboard.php?login=success');
                    break;
                default:
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?error=invalid');
            }
            exit;
        } else {
            // Password doesn't match
            header('Location: ' . $_SERVER['PHP_SELF'] . '?error=invalid');
            exit;
        }
    } else {
        // User not found
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=invalid');
        exit;
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CompreLearn - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Left Section - Illustration */
        .illustration-section {
            flex: 1.5;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .login-illustration {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        /* Right Section - Login Form */
        .login-section {
            flex: 1;
            background: linear-gradient(135deg, #8B5CF6,rgb(98, 93, 107));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            min-width: 400px;
        }

        .login-container {
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-image {
            max-width: 200px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .welcome-text {
            color: white;
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .welcome-subtitle {
            font-size: 14px;
            opacity: 0.8;
        }

        .error-message {
            background: rgba(220, 38, 38, 0.2);
            color: #FCA5A5;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            backdrop-filter: blur(5px);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            pointer-events: none;
        }

        .username-icon {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23ffffff' viewBox='0 0 24 24'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E");
            background-size: 18px 18px;
            background-repeat: no-repeat;
            background-position: center;
        }

        .password-icon {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23ffffff' viewBox='0 0 24 24'%3E%3Cpath d='M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z'/%3E%3C/svg%3E");
            background-size: 18px 18px;
            background-repeat: no-repeat;
            background-position: center;
        }

        .form-input {
            position: relative;
            z-index: 1;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 10;
            background-image: url('assets/images/hide.png');
            background-size: 18px 18px;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .password-toggle.show {
            background-image: url('assets/images/show.png');
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 30px;
        }

        .forgot-password a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
        }

        .forgot-password a:hover {
            opacity: 1;
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background: #4C1D95;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .login-button:hover {
            background: #5B21B6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .illustration-section {
                flex: 0.5;
            }
            
            .login-section {
                flex: 0.5;
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
            }
        }
    </style>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.add('show');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('show');
            }
        }
    </script>
</head>
<body>
    <!-- Left Section - Illustration -->
    <div class="illustration-section">
        <img src="assets/images/login.jpg" alt="CompreLearn Illustration" class="login-illustration">
    </div>

    <!-- Right Section - Login Form -->
    <div class="login-section">
        <div class="login-container">
            <div class="logo">
                <img src="assets/images/comprelogo2.png" alt="CompreLearn Logo" class="logo-image">
            </div>
            
            <div class="welcome-text">
                <div class="welcome-title">Welcome Back</div>
                <div class="welcome-subtitle">Sign in to access your account</div>
            </div>
            
            <?php
            // Display error messages
            if (isset($_GET['error'])) {
                $error_message = '';
                switch ($_GET['error']) {
                    case 'empty':
                        $error_message = 'Please fill in all fields.';
                        break;
                    case 'invalid':
                        $error_message = 'Invalid username or password.';
                        break;
                    case 'database':
                        $error_message = 'Database connection error. Please try again.';
                        break;
                    default:
                        $error_message = 'An error occurred. Please try again.';
                }
                echo '<div class="error-message">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <span class="input-icon">
                        <!-- User SVG Icon -->
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </span>
                    <input type="text" name="username" class="form-input" placeholder="Username or Student No." required>
                </div>
                
                <div class="form-group">
                    <span class="input-icon">
                        <!-- Lock SVG Icon -->
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </span>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                    <span class="password-toggle" onclick="togglePassword()" id="toggleIcon"></span>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" class="login-button">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
