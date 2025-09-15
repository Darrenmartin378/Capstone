 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compre Learn - Welcome</title>
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
            background: url('assets/images/login.jpg') no-repeat center center;
            background-size: cover;
        }

        .right-panel {
            width: 45%;
            height: 100%;
            background: linear-gradient(to bottom right, #6a0dad, #a64dff);
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
            max-width: 180px;
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
        .login-buttons button, .login-form input[type="submit"], .back-btn {
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

        .login-buttons button:hover, .login-form input[type="submit"]:hover, .back-btn:hover {
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

        .back-btn {
            background-color: rgba(0, 0, 0, 0.2);
            margin-top: 1rem;
        }

        .back-btn:hover {
            background-color: rgba(0, 0, 0, 0.4);
        }

        /* Page Transition Animations */
        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom right, #6a0dad, #a64dff);
            z-index: 9999;
            transform: translateX(100%);
            transition: transform 0.6s ease-in-out;
        }

        .page-transition.active {
            transform: translateX(0);
        }

        /* Button click animation */
        .button-click {
            animation: buttonPulse 0.3s ease-out;
        }

        @keyframes buttonPulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
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
    <div class="left-panel">
        
    </div>
    <div class="right-panel">
        <div class="login-container fade-in">
            <div class="logo">
                <img src="assets/images/comprelogo.png" alt="Compre Learn Logo" class="logo-image">
            </div>
            <h2>Login as:</h2>
            <div class="login-buttons">
                <button onclick="navigateWithAnimation('Admin/admin_login.php')">ADMIN LOGIN</button>
                <button onclick="navigateWithAnimation('Teacher/teacher_login.php')">TEACHER LOGIN</button>
                <button onclick="navigateWithAnimation('Student/student_login.php')">STUDENT LOGIN</button>
                <button onclick="navigateWithAnimation('parent_login.php')">PARENT LOGIN</button>
            </div>
        </div>
    </div>

    <!-- Page Transition Overlay -->
    <div class="page-transition" id="pageTransition"></div>

    <script>
        function navigateWithAnimation(url) {
            // Add click animation to button
            event.target.classList.add('button-click');
            
            // Show page transition
            const transition = document.getElementById('pageTransition');
            transition.classList.add('active');
            
            // Navigate after animation
            setTimeout(() => {
                window.location.href = url;
            }, 300);
        }

        // Remove button animation class after animation completes
        document.addEventListener('animationend', function(e) {
            if (e.animationName === 'buttonPulse') {
                e.target.classList.remove('button-click');
            }
        });
    </script>
</body>
</html> 