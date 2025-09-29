<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: login.php');
    exit;
}

$page_title = 'Parent Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CompreLearn - Parent Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #4C1D95;
        }

        .welcome-title {
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .card-title {
            color: white;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .card-button {
            background: #4C1D95;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .card-button:hover {
            background: #5B21B6;
        }

        .logout-section {
            text-align: center;
            margin-top: 40px;
        }

        .logout-button {
            background: rgba(220, 38, 38, 0.2);
            color: #FCA5A5;
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-button:hover {
            background: rgba(220, 38, 38, 0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="logo">👨‍👩‍👧‍👦</div>
            <h1 class="welcome-title">Parent Dashboard</h1>
            <p class="welcome-subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-icon">📊</div>
                <h3 class="card-title">Student Progress</h3>
                <p class="card-description">View your child's academic progress and performance</p>
                <button class="card-button" onclick="alert('Student Progress feature coming soon!')">View Progress</button>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">📚</div>
                <h3 class="card-title">Learning Materials</h3>
                <p class="card-description">Access educational resources and materials</p>
                <button class="card-button" onclick="alert('Learning Materials feature coming soon!')">View Materials</button>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">📝</div>
                <h3 class="card-title">Assignments</h3>
                <p class="card-description">Check your child's assignments and homework</p>
                <button class="card-button" onclick="alert('Assignments feature coming soon!')">View Assignments</button>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">📞</div>
                <h3 class="card-title">Communication</h3>
                <p class="card-description">Communicate with teachers and school</p>
                <button class="card-button" onclick="alert('Communication feature coming soon!')">Contact Teachers</button>
            </div>
        </div>

        <div class="logout-section">
            <button class="logout-button" onclick="window.location.href='login.php'">Logout</button>
        </div>
    </div>
</body>
</html>
