<?php
// Function to generate initials from a full name
function generateInitials($name) {
    if (empty($name)) {
        return 'AD'; // Default for Admin
    }
    
    // Split the name into words and filter out empty strings
    $words = array_filter(explode(' ', trim($name)));
    
    if (count($words) >= 2) {
        // Take first letter of first and last word
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    } elseif (count($words) == 1) {
        // If only one word, take first two letters
        $word = $words[0];
        if (strlen($word) >= 2) {
            return strtoupper(substr($word, 0, 2));
        } else {
            return strtoupper($word . 'A'); // Add 'A' for Admin
        }
    } else {
        return 'AD'; // Default for Admin
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Admin Panel - Compre Learn</title>
    <style>
        :root {
            --gmail-bg: #f8f9fa;
            --gmail-white: #ffffff;
            --gmail-surface: #ffffff;
            --gmail-primary: #1a73e8;
            --gmail-primary-hover: #1557b0;
            --gmail-secondary: #ea4335;
            --gmail-text: #202124;
            --gmail-text-secondary: #5f6368;
            --gmail-border: #dadce0;
            --gmail-hover: #f1f3f4;
            --gmail-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            --gmail-shadow-hover: 0 1px 3px 0 rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
            
            /* Legacy variables for compatibility */
            --light-bg: #f8f9fa;
            --light-bg-secondary: #ffffff;
            --light-surface: #ffffff;
            --primary-accent: #1a73e8;
            --secondary-accent: #ea4335;
            --light-text: #202124;
            --grey-text: #5f6368;
            --card-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Google Sans', 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        body { 
            background: var(--gmail-bg); 
            color: var(--gmail-text); 
            min-height: 100vh; 
            overflow-x: hidden; 
            font-size: 14px;
            line-height: 1.4;
        }
        
        .dashboard-header {
            background: var(--gmail-white);
            border-bottom: 1px solid var(--gmail-border);
            color: var(--gmail-text);
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1001;
            gap: 16px;
            box-shadow: var(--gmail-shadow);
            transition: margin-left .3s cubic-bezier(.4,0,.2,1);
            margin-left: 240px;
            height: 64px;
            min-height: 64px;
        }
        
        body.sidebar-hidden .dashboard-header {
            margin-left: 0 !important;
        }
        
        .logo-container { 
            display: flex; 
            align-items: center; 
            gap: 12px;
        }
        
        .logo-image { 
            height: 32px; 
            margin-right: 12px; 
            max-width: 100%; 
        }
        
        .dashboard-header h1 { 
            font-size: 22px; 
            color: var(--gmail-text);
            font-weight: 400;
            font-family: 'Google Sans', 'Roboto', sans-serif;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-icon {
            width: 32px;
            height: 32px;
            background: #1a73e8;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Google Sans', 'Roboto', sans-serif;
            letter-spacing: 0.5px;
        }
        
        .admin-name {
            font-weight: 500;
            color: var(--gmail-text);
            font-size: 14px;
            margin-right: 8px;
        }
        
        .logout-btn {
            background: var(--gmail-white);
            color: var(--gmail-text);
            border: 1px solid var(--gmail-border);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all .2s ease;
            font-weight: 500;
            font-size: 14px;
            height: 36px;
            display: flex;
            align-items: center;
        }
        
        .logout-btn:hover {
            background: var(--gmail-hover);
            box-shadow: var(--gmail-shadow-hover);
        }
        
        .container {
            max-width: 1600px;
            margin: 24px auto;
            padding: 0 24px;
            transition: margin-left .3s cubic-bezier(.4,0,.2,1);
            margin-left: 240px;
            background: var(--gmail-white);
            color: var(--gmail-text);
            border-radius: 8px;
            box-shadow: var(--gmail-shadow);
        }
        
        body.sidebar-hidden .container {
            margin-left: 0 !important;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            height: 100vh;
            background: var(--gmail-white);
            color: var(--gmail-text);
            border-right: 1px solid var(--gmail-border);
            z-index: 1100;
            display: flex;
            flex-direction: column;
            padding: 0;
            transition: transform .3s cubic-bezier(.4,0,.2,1);
        }
        
        .sidebar.hide {
            transform: translateX(-240px);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid var(--gmail-border);
            background: var(--gmail-white);
        }
        
        .sidebar-logo img {
            height: 24px;
            margin-right: 12px;
        }
        
        .sidebar-logo span {
            font-size: 16px;
            font-weight: 500;
            color: var(--gmail-text);
            font-family: 'Google Sans', 'Roboto', sans-serif;
        }
        
        .sidebar-menu {
            list-style: none;
            width: 100%;
            padding: 8px 0;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-menu a {
            width: 100%;
            background: none;
            border: none;
            color: var(--gmail-text-secondary);
            font-size: 14px;
            padding: 12px 24px;
            cursor: pointer;
            transition: all .2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            position: relative;
            border-radius: 0 25px 25px 0;
            margin-right: 8px;
        }
        
        .sidebar-menu a:hover {
            background: var(--gmail-hover);
            color: var(--gmail-text);
        }
        
        .sidebar-menu a.active {
            background: #e8f0fe;
            color: var(--gmail-primary);
            font-weight: 500;
        }
        
        .sidebar-menu a i {
            margin-right: 16px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-toggle-btn {
            background: var(--gmail-white);
            color: var(--gmail-text);
            border: 1px solid var(--gmail-border);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            transition: all .2s ease;
            height: 40px;
            width: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-toggle-btn:hover {
            background: var(--gmail-hover);
            box-shadow: var(--gmail-shadow-hover);
        }
        
        /* Responsive Design */
        
        /* Tablet and smaller desktop */
        @media (max-width: 1200px) {
            .container {
                margin: 1.5rem auto;
                padding: 0 1.5rem;
            }
            
            .dashboard-header {
                padding: 1rem 1.5rem;
            }
            
            .dashboard-header h1 {
                font-size: 1.8rem;
            }
        }
        
        /* Mobile landscape and small tablets */
        @media (max-width: 900px) {
            .sidebar {
                width: 200px;
            }
            
            .dashboard-header, .container {
                margin-left: 200px;
            }
            
            .sidebar-logo span {
                font-size: 14px;
            }
            
            .sidebar-menu a {
                font-size: 13px;
                padding: 10px 20px;
            }
        }
        
        /* Mobile portrait */
        @media (max-width: 768px) {
            .sidebar { 
                position: fixed;
                top: 0;
                left: -240px;
                width: 240px; 
                height: 100vh;
                flex-direction: column;
                transition: left .3s ease;
                z-index: 1100;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .dashboard-header, .container { 
                margin-left: 0;
                padding: 16px;
            }
            
            .dashboard-header {
                height: 56px;
                min-height: 56px;
                padding: 8px 16px;
                flex-direction: column;
                gap: 8px;
            }
            
            .logo-container {
                width: 100%;
                justify-content: space-between;
                order: 1;
            }
            
            .dashboard-header h1 {
                font-size: 18px;
                text-align: center;
                flex: 1;
            }
            
            .admin-profile {
                width: 100%;
                justify-content: center;
                gap: 8px;
                order: 2;
            }
            
            .admin-name {
                font-size: 13px;
            }
            
            .logout-btn {
                padding: 6px 12px;
                font-size: 13px;
                height: 32px;
            }
            
            .container {
                margin: 16px auto;
                padding: 0 16px;
                border-radius: 8px;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 480px) {
            .dashboard-header {
                padding: 0.8rem;
            }
            
            .dashboard-header h1 {
                font-size: 1.3rem;
            }
            
            .admin-profile {
                flex-direction: column;
                gap: 8px;
            }
            
            .profile-icon {
                font-size: 14px;
                width: 36px;
                height: 36px;
            }
            
            .logout-btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            .container {
                margin: 0.8rem auto;
                padding: 0 0.8rem;
            }
            
            .sidebar-toggle-btn {
                padding: 8px 12px;
                font-size: 1.1rem;
            }
        }
        
        .flash-credentials {
            background: #e8f0fe;
            color: var(--gmail-text);
            border: 1px solid var(--gmail-primary);
            padding: 16px;
            border-radius: 8px;
            margin: 0 0 16px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--gmail-shadow);
            animation: fadeIn .7s;
        }
        
        .flash-success {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #34a853;
            padding: 16px;
            border-radius: 8px;
            margin: 0 0 16px;
            box-shadow: var(--gmail-shadow);
            animation: fadeIn .7s;
        }
        
        @keyframes fadeIn { 
            from { 
                opacity: 0; 
                transform: translateY(-10px);
            } 
            to { 
                opacity: 1; 
                transform: none; 
            } 
        }
        
        .copy-btn {
            background: var(--gmail-primary);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all .2s ease;
            font-size: 12px;
            font-weight: 500;
            box-shadow: var(--gmail-shadow);
        }
        
        .copy-btn:hover { 
            background: var(--gmail-primary-hover);
            box-shadow: var(--gmail-shadow-hover);
        }
    </style>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/comprelogo.png" alt="Compre Learn Logo">
            <span>Compre Learn</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" title="Dashboard" <?php echo $current_page === 'admin_dashboard' ? 'class="active"' : ''; ?>><i class="fas fa-chart-line"></i>Dashboard</a></li>
            <li><a href="admin_teachers.php" title="Teachers" <?php echo $current_page === 'admin_teachers' ? 'class="active"' : ''; ?>><i class="fas fa-chalkboard-teacher"></i>Teachers</a></li>
            <li><a href="admin_students.php" title="Students" <?php echo $current_page === 'admin_students' ? 'class="active"' : ''; ?>><i class="fas fa-user-graduate"></i>Students</a></li>
            <li><a href="admin_parents.php" title="Parents" <?php echo $current_page === 'admin_parents' ? 'class="active"' : ''; ?>><i class="fas fa-users"></i>Parents</a></li>
            <li><a href="admin_sections.php" title="Sections" <?php echo $current_page === 'admin_sections' ? 'class="active"' : ''; ?>><i class="fas fa-school"></i>Sections</a></li>
            <li><a href="admin_admins.php" title="Admin Management" <?php echo $current_page === 'admin_admins' ? 'class="active"' : ''; ?>><i class="fas fa-user-shield"></i>Admin Management</a></li>
        </ul>
    </nav>

    <!-- Dashboard Header -->
    <header class="dashboard-header" id="dashboard-header">
        <div class="logo-container">
            <button id="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">&#9776;</button>
            <img src="../assets/images/comprelogo.png" alt="Logo" class="logo-image">
            <h1><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?></h1>
        </div>
        <div class="admin-profile">
            <div class="profile-icon"><?php echo generateInitials($adminName); ?></div>
            <span class="admin-name"><?php echo h($adminName); ?></span>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </header>

    <div class="container">
        <?php if (isset($parentCredentialsFlash) && $parentCredentialsFlash): ?>
        <div class="flash-credentials">
            <div><strong>Parent credentials created:</strong></div>
            <div>Username: <code id="flash-username"><?php echo h($parentCredentialsFlash['username'] ?? ''); ?></code>
                <button class="copy-btn" onclick="copyText(document.getElementById('flash-username').innerText)">Copy</button>
            </div>
            <div>Password: <code id="flash-password"><?php echo h($parentCredentialsFlash['password'] ?? ''); ?></code>
                <button class="copy-btn" onclick="copyText(document.getElementById('flash-password').innerText)">Copy</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($success_message) && $success_message): ?>
        <div class="flash-success">
            <div><?php echo h($success_message); ?></div>
        </div>
        <?php endif; ?>

        <?php echo $content; ?>
    </div>

    <script>
        function copyText(text) {
            if (!text) return;
            if (navigator.clipboard) { 
                navigator.clipboard.writeText(text); 
            } else {
                const ta = document.createElement('textarea'); 
                ta.value = text; 
                document.body.appendChild(ta); 
                ta.select(); 
                try { 
                    document.execCommand('copy'); 
                } catch(e) {} 
                document.body.removeChild(ta);
            }
        }
        
        function logout() { 
            if (confirm('Are you sure you want to logout?')) { 
                window.location.href = '../login.php'; 
            } 
        }
        
        // Sidebar toggle logic
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    // Check if we're on mobile
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('show');
                    } else {
                        sidebar.classList.toggle('hide');
                        document.body.classList.toggle('sidebar-hidden');
                    }
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && 
                    sidebar.classList.contains('show') && 
                    !sidebar.contains(e.target) && 
                    !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>

