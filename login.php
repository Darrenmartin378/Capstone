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
        header('Location: login.php?error=3');
        exit();
    }
    
    // Simple admin check first (guaranteed to work)
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
    
    try {
        // Database connection
        $conn = new mysqli("localhost", "root", "", "compre_learn");
        if ($conn->connect_error) {
            header('Location: login.php?error=2');
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
        header('Location: login.php?error=1');
        exit();
        
    } catch (Exception $e) {
        header('Location: login.php?error=2');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compre Learn - Smart Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 25%, #CD853F 50%, #DEB887 75%, #F5DEB3 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Books background pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                /* Book spines with different colors */
                linear-gradient(90deg, 
                    transparent 0%, transparent 2%, 
                    #654321 2%, #654321 4%, transparent 4%, transparent 6%, 
                    #8B4513 6%, #8B4513 8%, transparent 8%, transparent 10%, 
                    #A0522D 10%, #A0522D 12%, transparent 12%, transparent 14%, 
                    #CD853F 14%, #CD853F 16%, transparent 16%, transparent 18%, 
                    #8B4513 18%, #8B4513 20%, transparent 20%, transparent 22%, 
                    #654321 22%, #654321 24%, transparent 24%, transparent 26%, 
                    #A0522D 26%, #A0522D 28%, transparent 28%, transparent 30%, 
                    #CD853F 30%, #CD853F 32%, transparent 32%, transparent 34%, 
                    #8B4513 34%, #8B4513 36%, transparent 36%, transparent 38%, 
                    #654321 38%, #654321 40%, transparent 40%, transparent 42%, 
                    #A0522D 42%, #A0522D 44%, transparent 44%, transparent 46%, 
                    #CD853F 46%, #CD853F 48%, transparent 48%, transparent 50%, 
                    #8B4513 50%, #8B4513 52%, transparent 52%, transparent 54%, 
                    #654321 54%, #654321 56%, transparent 56%, transparent 58%, 
                    #A0522D 58%, #A0522D 60%, transparent 60%, transparent 62%, 
                    #CD853F 62%, #CD853F 64%, transparent 64%, transparent 66%, 
                    #8B4513 66%, #8B4513 68%, transparent 68%, transparent 70%, 
                    #654321 70%, #654321 72%, transparent 72%, transparent 74%, 
                    #A0522D 74%, #A0522D 76%, transparent 76%, transparent 78%, 
                    #CD853F 78%, #CD853F 80%, transparent 80%, transparent 82%, 
                    #8B4513 82%, #8B4513 84%, transparent 84%, transparent 86%, 
                    #654321 86%, #654321 88%, transparent 88%, transparent 90%, 
                    #A0522D 90%, #A0522D 92%, transparent 92%, transparent 94%, 
                    #CD853F 94%, #CD853F 96%, transparent 96%, transparent 98%, 
                    #8B4513 98%, #8B4513 100%, transparent 100%
                );
            background-size: 200px 100px;
            opacity: 0.4;
            z-index: 0;
        }

        /* Bookshelf lines */
        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                /* Horizontal shelf lines */
                linear-gradient(0deg, transparent 0%, transparent 48%, rgba(139, 69, 19, 0.3) 49%, rgba(139, 69, 19, 0.3) 51%, transparent 52%, transparent 100%),
                linear-gradient(0deg, transparent 0%, transparent 48%, rgba(139, 69, 19, 0.3) 49%, rgba(139, 69, 19, 0.3) 51%, transparent 52%, transparent 100%);
            background-size: 100% 100px;
            background-position: 0 0, 0 50px;
            z-index: 0;
        }

        /* Logo and header */
        .header {
            position: absolute;
            top: 30px;
            left: 30px;
            display: flex;
            align-items: center;
            z-index: 10;
        }

        .logo {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) saturate(100%) invert(20%) sepia(8%) saturate(2000%) hue-rotate(200deg) brightness(95%) contrast(90%);
        }

        /* Fallback if logo doesn't load */
        .logo::before {
            content: 'C';
            color: #2d3748;
            font-size: 24px;
            font-weight: bold;
            display: none;
        }

        .logo img:not([src]) + .logo::before,
        .logo:not(:has(img))::before {
            display: block;
        }

        .brand-name {
            color: #2d3748;
            font-size: 24px;
            font-weight: 600;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-icon {
            width: 50px;
            height: 50px;
            background: #2d3748;
            border-radius: 12px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .login-icon::before {
            content: '\f19d';
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            font-size: 20px;
        }

        .login-header h1 {
            color: #2d3748;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .login-header p {
            color: #718096;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
        }


        .login-form {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: #f7fafc;
            color: #2d3748;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input::placeholder {
            color: #a0aec0;
        }

        .form-group input:focus {
            border-color: #4299e1;
            background: white;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .form-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #a0aec0;
            font-size: 18px;
            transition: all 0.3s ease;
            padding: 5px;
        }

        .password-toggle:hover {
            color: #4299e1;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: #2d3748;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(45, 55, 72, 0.2);
        }

        .login-btn:hover {
            background: #1a202c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 55, 72, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(245, 101, 101, 0.1);
            border: 1px solid rgba(245, 101, 101, 0.2);
            color: #e53e3e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .forgot-password {
            text-align: right;
            margin-top: 15px;
        }

        .forgot-password a {
            color: #718096;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: #4299e1;
        }


        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .login-btn {
            background: #cbd5e0;
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                top: 20px;
                left: 20px;
            }

            .login-container {
                padding: 40px 30px;
                margin: 10px;
            }

            .login-header h1 {
                font-size: 1.6rem;
            }

            .form-group input {
                padding: 14px 18px 14px 45px;
                font-size: 16px;
            }

            .login-btn {
                padding: 14px;
                font-size: 16px;
            }

        }

        @media (max-width: 480px) {
            .header {
                top: 15px;
                left: 15px;
            }

            .logo {
                width: 40px;
                height: 40px;
            }

            .brand-name {
                font-size: 20px;
            }

            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 1.4rem;
            }

        }

        /* Success animation */
        .success-animation {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <!-- Header with Logo -->
    <div class="header">
        <div class="logo">
            <img src="assets/images/comprelogo.png" alt="CompreLearn Logo">
        </div>
        <div class="brand-name">CompreLearn</div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="login-icon"></div>
            <h1>Welcome to CompreLearn</h1>
            <p>Access your CompreLearn account to continue your educational journey</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form" id="loginForm">
            <div class="form-group">
                <i class="fas fa-envelope input-icon"></i>
                <input type="text" name="username" placeholder="Username or Student Number" required>
            </div>
            <div class="form-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" placeholder="Password" required id="passwordInput">
                <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
            </div>
            <div class="forgot-password">
                <a href="#" onclick="showForgotPassword()">Forgot password?</a>
            </div>
            <button type="submit" name="login" class="login-btn" id="loginBtn">
                <span id="btnText">Get Started</span>
            </button>
        </form>
    </div>

    <script>
        // Add loading state to form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const form = this;
            const submitBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            
            // Add loading state but don't disable the button
            form.classList.add('loading');
            btnText.textContent = 'Signing in...';
            
            // Add success animation after a short delay
            setTimeout(() => {
                form.classList.add('success-animation');
            }, 100);
        });
        
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleBtn = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleBtn.className = 'fas fa-eye';
            }
        }
        
        function showForgotPassword() {
            // Show a modal with options for different user types
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            `;
            
            modal.innerHTML = `
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                ">
                    <h3 style="margin: 0 0 20px 0; color: #2d3748; font-size: 18px;">Reset Password</h3>
                    <p style="margin: 0 0 20px 0; color: #718096; font-size: 14px;">
                        Choose your account type to reset your password:
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button onclick="resetPassword('admin')" style="
                            background: #1a73e8;
                            color: white;
                            border: none;
                            padding: 12px 20px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                        ">Admin Account</button>
                        <button onclick="resetPassword('teacher')" style="
                            background: #34a853;
                            color: white;
                            border: none;
                            padding: 12px 20px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                        ">Teacher Account</button>
                        <button onclick="resetPassword('student')" style="
                            background: #ea4335;
                            color: white;
                            border: none;
                            padding: 12px 20px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                        ">Student Account</button>
                        <button onclick="resetPassword('parent')" style="
                            background: #fbbc04;
                            color: white;
                            border: none;
                            padding: 12px 20px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                        ">Parent Account</button>
                    </div>
                    <button onclick="closeModal()" style="
                        background: #6c757d;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 8px;
                        cursor: pointer;
                        margin-top: 15px;
                        font-weight: 500;
                    ">Cancel</button>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            window.closeModal = function() {
                document.body.removeChild(modal);
            };
            
            window.resetPassword = function(type) {
                document.body.removeChild(modal);
                // For now, show contact info - in a real system, this would redirect to reset pages
                alert(`To reset your ${type} password, please contact your system administrator.\n\nFor immediate assistance, please reach out to your IT support team.`);
            };
        }
        
        // Add smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add staggered animation to form elements
            const formElements = document.querySelectorAll('.form-group, .login-btn, .user-types');
            formElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
                element.classList.add('slideUp');
            });
        });
        
        // Add focus effects to form inputs
        document.querySelectorAll('.form-group input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
        
        // Add hover effects to user type cards
        document.querySelectorAll('.user-type').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(255, 255, 255, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.background = 'rgba(255, 255, 255, 0.05)';
            });
        });
    </script>
</body>
</html>
