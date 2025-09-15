<?php
// verify_code.php
$error = '';
$success = '';
$show_form = true;

$email = $_GET['email'] ?? '';
$type = $_GET['type'] ?? '';

// Check if there's a recent code for this email (in case email failed)
$recentCode = '';
if ($email && $type) {
    $conn = new mysqli("localhost", "root", "", "compre_learn");
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT code FROM password_resets WHERE email = ? AND user_type = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $recentCode = $row['code'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        $conn = new mysqli("localhost", "root", "", "compre_learn");
        if ($conn->connect_error) {
            $error = 'Database connection failed.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND user_type = ? AND code = ? AND expires_at > NOW() AND used = 0");
            $stmt->bind_param("sss", $email, $type, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Code is valid, redirect to reset password
                header("Location: reset_password.php?token=" . $row['token'] . "&type=" . $type);
                exit();
            } else {
                $error = 'Invalid or expired verification code.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Code</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            background: url('../assets/images/login.jpg') no-repeat center center;
            background-size: cover;
        }
        .right-panel {
            width: 45%;
            height: 100%;
            background: linear-gradient(to bottom right,rgb(5, 143, 255),rgb(64, 223, 143));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            box-sizing: border-box;
        }
        .verify-code-container {
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
        .verify-code-container input[type="text"],
        .verify-code-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f1f1f1;
            color: #333;
            text-align: center;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        .verify-code-container input[type="submit"] {
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
        .verify-code-container input[type="submit"]:hover {
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
        .code-info {
            color: #f1f1f1;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .code-display {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 1rem;
            color: #f1f1f1;
            font-size: 0.9rem;
        }
        .verification-code {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="verify-code-container fade-in">
            <div class="logo">
                <img src="../assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Verify Code</h2>
            <div class="code-info">
                Please enter the 6-digit verification code sent to your email.
            </div>
            <?php if ($recentCode): ?>
            <div class="code-display">
                <strong>If you didn't receive the email, your verification code is:</strong><br>
                <span class="verification-code"><?php echo $recentCode; ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($show_form): ?>
            <form method="POST">
                <input type="text" name="code" placeholder="Enter 6-digit code" maxlength="6" required>
                <input type="submit" value="Verify Code">
            </form>
            <?php endif; ?>
            <a href="student_forgot_password.php">Back to Forgot Password</a>
        </div>
    </div>
</body>
</html>
