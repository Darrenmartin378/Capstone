<?php
// Get notification count
$notificationCount = 0;
$announcements = $conn->query("SELECT COUNT(*) as count FROM announcements");
if ($announcements && $row = $announcements->fetch_assoc()) {
    $notificationCount = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Student Dashboard'; ?> - CompreLearn</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Grade 6 Student-Friendly Color Palette */
            --primary: #4CAF50; /* Vibrant Green - Nature & Growth */
            --primary-dark: #45a049;
            --secondary: #FF9800; /* Energetic Orange - Creativity & Fun */
            --accent: #E91E63; /* Playful Pink - Energy & Excitement */
            --teal: #00BCD4; /* Bright Cyan - Technology & Learning */
            --success: #8BC34A; /* Fresh Green - Success & Achievement */
            --warning: #FFC107; /* Sunny Yellow - Attention & Joy */
            --error: #F44336; /* Alert Red - Important Notices */
            --purple: #9C27B0; /* Creative Purple - Imagination */
            --blue: #2196F3; /* Trust Blue - Knowledge & Trust */
            --bg-primary: #F8F9FA;
            --bg-secondary: #E3F2FD;
            --bg-card: #ffffff;
            --text-primary: #2E3440;
            --text-secondary: #4A5568;
            --text-muted: #718096;
            --border: #E1E8ED;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.18);
        }
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Sidebar - Grade 6 Student Style */
        .sidebar {
            width: 256px;
            background: linear-gradient(180deg, #4CAF50 0%, #45a049 100%);
            color: #ffffff;
            padding: 0;
            box-shadow: var(--shadow-lg);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 3px solid var(--secondary);
        }

        /* Sidebar collapsed state - icon only */
        .sidebar.collapsed {
            width: 72px;
            transform: none;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 16px 8px;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-logo-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-nav a {
            padding: 8px 12px;
            justify-content: center;
            gap: 0;
        }

        .sidebar.collapsed .sidebar-nav a .nav-text {
            display: none;
        }

        .sidebar-header {
            padding: 16px 24px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1002;
            margin-top: 64px;
            min-height: 64px;
            backdrop-filter: blur(10px);
        }


        /* Tooltip for collapsed navigation */
        .sidebar.collapsed .sidebar-nav a::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #3c4043;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
            margin-left: 8px;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15);
        }

        .sidebar.collapsed .sidebar-nav a::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 4px solid transparent;
            border-right-color: #3c4043;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
            margin-left: 4px;
        }

        .sidebar.collapsed .sidebar-nav a:hover::after,
        .sidebar.collapsed .sidebar-nav a:hover::before {
            opacity: 1;
            visibility: visible;
        }


        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
            z-index: 1003;
            position: relative;
        }

        .sidebar-logo img {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
        }

        .sidebar-logo-text {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .sidebar-logo .icon {
            font-size: 24px;
        }

        /* Hide logo text when collapsed */
        .sidebar.collapsed .sidebar-logo-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-logo {
            justify-content: center;
        }



        /* Sidebar backdrop for mobile */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,.5);
            z-index: 999;
            display: none;
        }

        .sidebar-backdrop.active {
            display: block;
        }


        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1001;
        }

        .sidebar-nav li {
            margin: 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0 25px 25px 0;
            margin-right: 8px;
            font-weight: 500;
            font-size: 15px;
            min-height: 48px;
            position: relative;
        }


        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }

        .sidebar-nav a.active {
            background: var(--secondary);
            color: #ffffff;
            font-weight: 600;
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--accent);
        }

        .sidebar-nav .icon {
            font-size: 22px;
            width: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-nav a.active .icon {
            color: #ffffff;
        }

        .sidebar-nav a:hover .icon {
            color: #ffffff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #E3F2FD 0%, #F3E5F5 100%);
            margin-left: 256px;
        }

        .main-content.sidebar-collapsed {
            margin-left: 72px;
        }

        /* Header - Grade 6 Student Style */
        .header {
            background: linear-gradient(135deg, var(--blue) 0%, var(--teal) 100%);
            border-bottom: 3px solid var(--primary);
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            color: #ffffff;
            box-shadow: var(--shadow-lg);
            height: 64px;
        }

        .header-center {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            max-width: 600px;
        }

        /* Grade 6 Student Style Menu Button */
        .menu-button {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 20px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            box-shadow: var(--shadow-md);
        }

        .menu-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .menu-button:active {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(0.95);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .section-info {
            display: flex;
            align-items: center;
        }

        .section-badge {
            background: var(--warning);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.3);
            white-space: nowrap;
            box-shadow: var(--shadow-md);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .header h1 .emoji {
            font-size: 22px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .chip {
            background: var(--accent);
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .notification-icon {
            position: relative;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 20px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        .notification-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #ea4335;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 500;
            border: 2px solid #ffffff;
        }

        .logout {
            text-decoration: none;
            padding: 10px 18px;
            background: var(--error);
            color: #ffffff;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .logout:hover {
            background: #d32f2f;
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            background: #ffffff;
            margin-top: 80px;
        }

        /* Grade 6 Student Style Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 5px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--secondary) 0%, var(--accent) 100%);
        }

        /* Smooth animations for all interactive elements */
        * {
            transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }


            .sidebar-header {
                padding: 15px 20px;
            }

            .sidebar-logo img {
                width: 35px;
                height: 35px;
            }

            .sidebar-logo-text {
                font-size: 16px;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .main-content.sidebar-collapsed {
                margin-left: 0 !important;
            }

            .content {
                margin-top: 64px;
            }

            .header {
                padding: 8px 12px;
                height: 56px;
            }

            .header-center h1 {
                font-size: 18px;
            }

            .section-badge {
                font-size: 11px;
                padding: 3px 8px;
            }

            .chip {
                font-size: 12px;
                padding: 6px 10px;
            }


            .sidebar-header {
                flex-direction: column;
                gap: 10px;
                align-items: center;
                margin-top: 0;
                z-index: 1002;
            }

            .content {
                padding: 16px;
                margin-top: 8px;
            }
        }

    </style>
</head>
<body>
    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="student_dashboard.php" class="sidebar-logo">
                <img src="../assets/images/comprelogo.png" alt="CompreLearn Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div style="display: none; align-items: center; gap: 8px;">
                    <span class="icon">📚</span>
                    <span class="sidebar-logo-text">CompreLearn</span>
                </div>
            </a>
        </div>
        <ul class="sidebar-nav">
            <li><a href="student_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <span class="icon">🏠</span> <span class="nav-text">Dashboard</span>
            </a></li>
            <li><a href="student_materials.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_materials.php' ? 'active' : ''; ?>" data-tooltip="Materials">
                <span class="icon">📗</span> <span class="nav-text">Materials</span>
            </a></li>
            <li><a href="student_tests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_tests.php' ? 'active' : ''; ?>" data-tooltip="My Tests">
                <span class="icon">📝</span> <span class="nav-text">My Tests</span>
            </a></li>
            <li><a href="student_questions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_questions.php' ? 'active' : ''; ?>" data-tooltip="Questions">
                <span class="icon">❓</span> <span class="nav-text">Questions</span>
            </a></li>
            <li><a href="student_progress.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_progress.php' ? 'active' : ''; ?>" data-tooltip="My Progress">
                <span class="icon">📊</span> <span class="nav-text">My Progress</span>
            </a></li>
            <li><a href="student_practice.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_practice.php' ? 'active' : ''; ?>" data-tooltip="Practice">
                <span class="icon">🎯</span> <span class="nav-text">Practice</span>
            </a></li>
            <li><a href="student_reading.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_reading.php' ? 'active' : ''; ?>" data-tooltip="Reading Lists">
                <span class="icon">📚</span> <span class="nav-text">Reading Lists</span>
            </a></li>
            <li><a href="student_alerts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_alerts.php' ? 'active' : ''; ?>" data-tooltip="Performance Alerts">
                <span class="icon">⚠️</span> <span class="nav-text">Performance Alerts</span>
            </a></li>
            <li><a href="student_notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_notifications.php' ? 'active' : ''; ?>" data-tooltip="Notifications">
                <span class="icon">🔔</span> <span class="nav-text">Notifications</span>
            </a></li>
        </ul>
    </nav>


    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <button class="menu-button" id="menuButton" onclick="toggleSidebar()" title="Toggle Sidebar">
                ☰
            </button>
            <div class="header-center">
                <h1><span class="emoji">📚</span> Student Portal</h1>
                <div class="section-info">
                    <span class="section-badge"><?php echo h($sectionName); ?></span>
                </div>
            </div>
            
            <div class="header-actions">
                <span class="chip">Hi, <?php echo h($studentName); ?>! ✨</span>
                <button class="notification-icon" onclick="window.location.href='student_notifications.php'" title="Notifications">
                    🔔
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
                <a class="logout" href="?logout=1">Logout</a>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content">
            <?php if (isset($content)): ?>
                <?php echo $content; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (window.innerWidth <= 768) {
                // Mobile behavior
                if (sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    backdrop.classList.remove('active');
                } else {
                    sidebar.classList.add('open');
                    backdrop.classList.add('active');
                }
            } else {
                // Desktop behavior - toggle collapsed state
                if (sidebar.classList.contains('collapsed')) {
                    // Expand sidebar
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    // Collapse sidebar
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
            }
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const menuButton = document.getElementById('menuButton');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !menuButton.contains(e.target)) {
                sidebar.classList.remove('open');
                backdrop.classList.remove('active');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (window.innerWidth > 768) {
                // Switch to desktop mode
                sidebar.classList.remove('open');
                backdrop.classList.remove('active');
                // Restore saved sidebar state on desktop
                restoreSidebarState();
            } else {
                // Switch to mobile mode
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                sidebar.classList.remove('open');
                backdrop.classList.remove('active');
            }
        });

        // Restore sidebar state on page load
        function restoreSidebarState() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('sidebar-collapsed');
                }
            }
        }

        // Handle navigation links - close sidebar on mobile after navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Restore sidebar state on page load
            restoreSidebarState();
            
            const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        // Mobile: close sidebar after navigation
                        const sidebar = document.getElementById('sidebar');
                        const backdrop = document.getElementById('sidebarBackdrop');
                        sidebar.classList.remove('open');
                        backdrop.classList.remove('active');
                    }
                });
            });
        });
    </script>
</body>
</html>
