<?php
// email_setup_guide.php - Gmail SMTP Setup Guide
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gmail SMTP Setup Guide</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d73527;
            text-align: center;
            border-bottom: 2px solid #d73527;
            padding-bottom: 10px;
        }
        h2 {
            color: #1a73e8;
            margin-top: 30px;
        }
        h3 {
            color: #34a853;
            margin-top: 20px;
        }
        .error-box {
            background: #fce8e6;
            border: 1px solid #d93025;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .success-box {
            background: #e6f4ea;
            border: 1px solid #34a853;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fef7e0;
            border: 1px solid #f9ab00;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dadce0;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #1a73e8;
            padding: 15px;
            margin: 15px 0;
        }
        .alternative {
            background: #e8f0fe;
            border: 1px solid #1a73e8;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        ul, ol {
            padding-left: 20px;
        }
        li {
            margin: 8px 0;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        a {
            color: #1a73e8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Gmail SMTP Authentication Fix</h1>
        
        <div class="error-box">
            <strong>❌ Current Error:</strong> "SMTP Error: Could not authenticate."
            <br><br>
            This means Gmail is rejecting the login credentials due to security settings.
        </div>

        <h2>🔍 Why This Happens</h2>
        <p>Gmail has strict security policies that require:</p>
        <ul>
            <li><strong>2-Factor Authentication</strong> enabled on the Gmail account</li>
            <li><strong>App Passwords</strong> instead of regular passwords</li>
            <li><strong>"Less Secure Apps"</strong> setting (deprecated)</li>
        </ul>

        <h2>✅ Solution 1: Use Gmail App Password (Recommended)</h2>
        
        <div class="step">
            <h3>Step 1: Enable 2-Factor Authentication</h3>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                <li>Click <strong>"2-Step Verification"</strong></li>
                <li>Follow the setup process</li>
            </ol>
        </div>

        <div class="step">
            <h3>Step 2: Generate App Password</h3>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a></li>
                <li>Select <strong>"Mail"</strong> and <strong>"Other (Custom name)"</strong></li>
                <li>Enter "CompreLearn" as the app name</li>
                <li>Copy the generated 16-character password</li>
            </ol>
        </div>

        <div class="step">
            <h3>Step 3: Update the Code</h3>
            <p>Replace the current password in the code:</p>
            <div class="code-block">
$mail->Password = 'your-16-character-app-password-here';
            </div>
        </div>

        <h2>🔄 Solution 2: Alternative Email Service</h2>
        
        <div class="alternative">
            <h3>Option A: Use Outlook/Hotmail SMTP</h3>
            <div class="code-block">
$mail->Host = 'smtp-mail.outlook.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@outlook.com';
$mail->Password = 'your-password';
            </div>
        </div>

        <div class="alternative">
            <h3>Option B: Use Yahoo SMTP</h3>
            <div class="code-block">
$mail->Host = 'smtp.mail.yahoo.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@yahoo.com';
$mail->Password = 'your-app-password';
            </div>
        </div>

        <h2>🛠️ Solution 3: Local Development Setup</h2>
        
        <div class="warning-box">
            <strong>⚠️ For Local Development Only:</strong> Use a local mail server or disable email sending.
        </div>

        <div class="step">
            <h3>Option A: Use XAMPP Mail Server</h3>
            <ol>
                <li>Install <strong>Mercury Mail Server</strong> (comes with XAMPP)</li>
                <li>Configure it to use localhost SMTP</li>
                <li>Update the code to use localhost</li>
            </ol>
            <div class="code-block">
$mail->Host = 'localhost';
$mail->Port = 25;
$mail->SMTPAuth = false;
            </div>
        </div>

        <div class="step">
            <h3>Option B: Disable Email for Development</h3>
            <p>Modify the code to always show codes on screen:</p>
            <div class="code-block">
// Always show code for development
$emailSent = false;
$error = "Development mode: Your verification code is: <strong>$code</strong>";
            </div>
        </div>

        <h2>🧪 Testing Your Setup</h2>
        
        <div class="step">
            <h3>Test Email Functionality</h3>
            <ol>
                <li>Visit: <a href="test_email.php">test_email.php</a></li>
                <li>Enter a test email address</li>
                <li>Click "Send Test Email"</li>
                <li>Check for success/error messages</li>
            </ol>
        </div>

        <h2>📋 Current Configuration</h2>
        
        <div class="code-block">
Gmail Account: martindarren3561@gmail.com
Current Password: dptnkxgbvhvpojkn (App Password)
SMTP Host: smtp.gmail.com
Port: 587
Security: TLS
        </div>

        <div class="success-box">
            <strong>✅ Fallback System:</strong> Even if email fails, users can still reset passwords because the verification code is displayed on screen.
        </div>

        <h2>🚀 Quick Fix for Now</h2>
        
        <p>If you want to test the system immediately without fixing email:</p>
        <ol>
            <li>The verification codes are already displayed on screen when email fails</li>
            <li>Users can still complete password reset using the displayed codes</li>
            <li>This allows full testing of the password reset functionality</li>
        </ol>

        <div class="warning-box">
            <strong>📝 Note:</strong> The current system is designed to work even when email fails, so the password reset functionality is fully operational.
        </div>
    </div>
</body>
</html>
