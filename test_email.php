<?php
// test_email.php - Simple email test page
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = $_POST['test_email'] ?? '';
    $testCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    if (empty($testEmail)) {
        $error = 'Please enter an email address.';
    } else {
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
            $mail->setFrom('martindarren3561@gmail.com', 'CompreLearn Test');
            $mail->addAddress($testEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Test Email - Verification Code';
            $mail->Body = "This is a test email.<br><br>Your test verification code is: <strong>$testCode</strong><br><br>If you received this email, the email system is working correctly.";
            
            $mail->send();
            $result = "✅ Test email sent successfully to $testEmail!<br>Test code: <strong>$testCode</strong>";
        } catch (Exception $e) {
            $error = "❌ Email sending failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email System Test</h1>
        
        <div class="info">
            <strong>Purpose:</strong> This page tests if the email system is working correctly for sending verification codes to students.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="test_email">Test Email Address:</label>
                <input type="email" id="test_email" name="test_email" placeholder="Enter email to test" required>
            </div>
            <button type="submit">Send Test Email</button>
        </form>
        
        <?php if ($result): ?>
            <div class="result success">
                <?php echo $result; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="result error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>Available test emails from database:</strong><br>
            • martindarren410@gmail.com<br>
            • darwin@gmail.com<br>
            • merlindaborboncomoro@gmail.com
        </div>
    </div>
</body>
</html>
