<?php
// student_forgot_password.php
$error = '';
$success = '';

require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $type = $_POST['type'] ?? '';
    $code = trim($_POST['code'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email.';
    } else {
        $conn = new mysqli("localhost", "root", "", "compre_learn");
        if ($conn->connect_error) {
            $error = 'Database connection failed.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                $user_type = 'student';
                $stmt2 = $conn->prepare("INSERT INTO password_resets (email, user_type, token, code, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("sssss", $email, $user_type, $token, $code, $expires);
                $stmt2->execute();
                // Send code via PHPMailer
                $subject = "Password Reset Verification Code";
                $message = "Your verification code is: <strong>$code</strong><br><br>This code will expire in 1 hour.<br><br>If you did not request this password reset, please ignore this email.";
                
                $emailSent = false;
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'martindarren3561@gmail.com';
                    $mail->Password = 'dptnkxgbvhvpojkn';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    $mail->setFrom('martindarren3561@gmail.com', 'CompreLearn');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $message;
                    $mail->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    // Log the error for debugging
                    error_log("Email sending failed: " . $e->getMessage());
                    $emailSent = false;
                }
                
                // If email fails, still proceed but show the code on screen for testing
                if (!$emailSent) {
                    // For development/testing purposes, we'll show the code
                    $error = "Email sending failed. For testing purposes, your verification code is: <strong>$code</strong><br><br>You can still proceed to verify your code.";
                } else {
                    $success = "Verification code sent to your email successfully!";
                }
                
                // Always redirect to verification page, regardless of email success
                header("Location: verify_code.php?email=" . urlencode($email) . "&type=student");
                exit();
            } else {
                $error = 'If this email is registered, a password reset code has been sent.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Forgot Password</title>
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
                <img src="../assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Student Forgot Password</h2>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Enter your email" required>
                <input type="submit" value="Send Reset Link">
            </form>
            <a href="../login.php">Back to Login</a>
        </div>
    </div>
</body>
</html> 