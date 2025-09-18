<?php
session_start();
// Simple authentication check (customize as needed)
if (!isset($_SESSION['parent_logged_in']) || !$_SESSION['parent_logged_in']) {
    header('Location: login.php');
    exit;
}

// Handle success message from login
$success_message = '';
if (isset($_GET['login']) && $_GET['login'] == 'success') {
    $success_message = 'Welcome back! You have successfully logged in.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-container { max-width: 900px; margin: 2rem auto; padding: 2rem; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .dashboard-header h1 { color: #4a69bd; }
        .analytics-section { margin-bottom: 2rem; }
        .analytics-section h3 { color: #6a89cc; margin-bottom: 0.5rem; }
        .logout-btn { background: #e55039; color: #fff; border: none; padding: 0.5rem 1.2rem; border-radius: 5px; cursor: pointer; }
        .logout-btn:hover { background: #c0392b; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if ($success_message): ?>
        <div class="success-message" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 16px; border-radius: 8px; margin: 0 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); animation: fadeIn 0.7s;">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <h1>Welcome, Parent</h1>
            <form method="post" style="margin:0;"><button class="logout-btn" name="logout">Logout</button></form>
        </div>
        <h2>Performance Analytics and Monitoring</h2>
        <div class="analytics-section">
            <h3>Analyze Skill Trends</h3>
            <p>Placeholder for skill trends analysis. (Coming soon)</p>
        </div>
        <div class="analytics-section">
            <h3>View Individual Progress Report</h3>
            <p>Placeholder for individual progress report. (Coming soon)</p>
        </div>
        <div class="analytics-section">
            <h3>View Growth Graphs</h3>
            <p>Placeholder for growth graphs. (Coming soon)</p>
        </div>
    </div>
    <?php
    if (isset($_POST['logout'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    ?>
</body>
</html> 