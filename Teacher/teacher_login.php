<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$error = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION['teacher_logged_in']) && $_SESSION['teacher_logged_in'] === true) {
    header('Location: teacher_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $conn = @new mysqli("localhost", "root", "", "compre_learn");
    if ($conn->connect_error) {
        die("<div style='color:red;padding:10px;'>Connection failed: " . $conn->connect_error . "<br>
        <b>Make sure MySQL is running in XAMPP and the database exists.</b></div>");
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM teachers WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $teacher = $result->fetch_assoc();
        if (password_verify($password, $teacher['password'])) {
            // Password is correct, start session
            $_SESSION['teacher_logged_in'] = true;
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            
            header("Location: /capstone/Teacher/teacher_dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - Compre Learn</title>
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
            background: linear-gradient(to bottom right,rgb(58, 58, 58),rgb(255, 255, 255));
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
        .login-form input[type="submit"], .back-btn {
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

        .login-form input[type="submit"]:hover, .back-btn:hover {
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
        
        .error-message {
            color: #ffdddd;
            background-color: rgba(231, 76, 60, 0.7);
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
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
            <h2>Teacher Login</h2>
            <form method="POST" action="teacher_login.php" class="login-form">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="submit" name="login" value="Login">
                 <?php if(!empty($error)): ?>
                    <p class="error-message"><?php echo $error; ?></p>
                <?php endif; ?>
            </form>
            <a href="teacher_forgot_password.php" style="color:#007bff;display:block;margin:10px 0 0 0;">Forgot Password?</a>
            <button class="back-btn" onclick="window.location.href='../login_as.php'">Back</button>
        </div>
    </div>
</body>
</html>
