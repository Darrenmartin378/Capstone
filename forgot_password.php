<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CompreLearn - Forgot Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            position: relative;
        }

        .brain-icon {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: #4C1D95;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brain-icon::before {
            content: '🧠';
            font-size: 24px;
        }

        .book-icon {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 30px;
            background: #4C1D95;
            border-radius: 4px;
        }

        .book-icon::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 5px;
            background: white;
            border-radius: 2px;
        }

        .logo-text {
            color: #4C1D95;
            font-size: 28px;
            font-weight: bold;
            margin-top: 10px;
        }

        .title {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 30px;
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
        }

        .submit-button {
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
            margin-bottom: 20px;
        }

        .submit-button:hover {
            background: #5B21B6;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
        }

        .back-link:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <div class="logo-icon">
                <div class="brain-icon"></div>
                <div class="book-icon"></div>
            </div>
            <div class="logo-text">CompreLearn</div>
        </div>
        
        <div class="title">Forgot Password?</div>
        <div class="subtitle">Enter your email to reset your password</div>
        
        <form action="reset_password.php" method="POST">
            <div class="form-group">
                <span class="input-icon">📧</span>
                <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
            </div>
            
            <button type="submit" class="submit-button">Send Reset Link</button>
        </form>
        
        <a href="login.php" class="back-link">← Back to Login</a>
    </div>
</body>
</html>
