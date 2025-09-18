<?php
// parent_forgot_password.php
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        $error = 'Please enter your email.';
    } else {
        $conn = new mysqli("localhost", "root", "", "compre_learn");
        if ($conn->connect_error) {
            $error = 'Database connection failed.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM parents WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                $user_type = 'parent';
                $stmt2 = $conn->prepare("INSERT INTO password_resets (email, user_type, token, expires_at) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("ssss", $email, $user_type, $token, $expires);
                $stmt2->execute();
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token&type=parent";
                $subject = "Password Reset Request";
                $message = "Click the following link to reset your password: $reset_link\nThis link will expire in 1 hour.";
                $headers = "From: no-reply@comprelearn.local";
                mail($email, $subject, $message, $headers);
            }
            $success = 'If this email is registered, a password reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Forgot Password</title>
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
            background: linear-gradient(to bottom right,rgb(255, 193, 7),rgb(40, 167, 69));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            box-sizing: border-box;
        }
        .forgot-password-container {
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
        .forgot-password-container input[type="email"],
        .forgot-password-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f1f1f1;
            color: #333;
        }
        .forgot-password-container input[type="submit"] {
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
        .forgot-password-container input[type="submit"]:hover {
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
    </style>
</head>
<body>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="forgot-password-container fade-in">
            <div class="logo">
                <img src="assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Parent Forgot Password</h2>
            <?php if ($error): ?><div class="error-message"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Enter your email" required>
                <input type="submit" value="Send Reset Link">
            </form>
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html> 