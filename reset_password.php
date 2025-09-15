<?php
// reset_password.php
$error = '';
$success = '';
$show_form = false;

$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? '';

if ($token && $type && in_array($type, ['teacher', 'student', 'parent'])) {
    $conn = new mysqli("localhost", "root", "", "compre_learn");
    if ($conn->connect_error) {
        $error = 'Database connection failed.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND user_type = ? AND expires_at > NOW() AND used = 0");
        $stmt->bind_param("ss", $token, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $show_form = true;
            $email = $row['email'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                $new_password = $_POST['password'];
                if (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    if ($type === 'teacher') {
                        $stmt2 = $conn->prepare("UPDATE teachers SET password = ? WHERE email = ?");
                    } elseif ($type === 'student') {
                        $stmt2 = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
                    } else {
                        $stmt2 = $conn->prepare("UPDATE parents SET password = ? WHERE email = ?");
                    }
                    $stmt2->bind_param("ss", $hashed, $email);
                    $stmt2->execute();
                    $stmt3 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                    $stmt3->bind_param("i", $row['id']);
                    $stmt3->execute();
                    $success = 'Your password has been reset. You may now log in.';
                    $show_form = false;
                }
            }
        } else {
            $error = 'Invalid or expired reset link.';
        }
    }
} else {
    $error = 'Invalid reset link.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background-color: #f0f2f5;
        }
        .left-panel {
            width: 55%;
            height: 100%;
            background: url('assets/images/login.jpg') no-repeat center center;
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
        .reset-password-container {
            width: 100%;
            max-width: 350px;
        }
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
        h2 {
            font-size: 1.2rem;
            font-weight: normal;
            margin-bottom: 1rem;
            color: #f1f1f1;
        }
        .reset-password-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f1f1f1;
            color: #333;
        }
        .reset-password-container input[type="submit"] {
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
        .reset-password-container input[type="submit"]:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
            transform: translateY(-2px);
        }
        .error-message {
            color: #ffdddd;
            background-color: rgba(231, 76, 60, 0.7);
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .success-message {
            color: #d4edda;
            background-color: rgba(40, 167, 69, 0.7);
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }
        a {
            color: #007bff;
            display: block;
            margin: 10px 0 0 0;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .password-info {
            color: #f1f1f1;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="reset-password-container fade-in">
            <div class="logo">
                <img src="assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Reset Password</h2>
            <div class="password-info">
                Please enter your new password (minimum 6 characters).
            </div>
            <?php if ($error): ?><div class="error-message"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>
            <?php if ($show_form): ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter new password" required>
                <input type="submit" value="Reset Password">
            </form>
            <?php endif; ?>
            <a href="login_as.php">Back to Login</a>
        </div>
    </div>
</body>
</html> 