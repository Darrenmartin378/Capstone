<?php
// Compute notification count (announcements + new question sets for the student's section)
$notificationCount = 0;
$studentSectionId = (int)($_SESSION['section_id'] ?? ($_SESSION['student_section_id'] ?? 0));
try {
    $resA = $conn->query("SELECT COUNT(*) AS c FROM announcements");
    if ($resA && ($r = $resA->fetch_assoc())) { $notificationCount += (int)$r['c']; }
} catch (Throwable $e) { /* ignore */ }
try {
    if ($studentSectionId > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM question_sets WHERE section_id = ?");
        if ($stmt) { $stmt->bind_param('i', $studentSectionId); $stmt->execute(); $rc = $stmt->get_result()->fetch_assoc(); $notificationCount += (int)($rc['c'] ?? 0); }
    }
} catch (Throwable $e) { /* ignore */ }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Cache bust: 2024-01-15 - Header positioning fix */
        :root {
            /* Clean White Design Color Palette */
            --primary: #2563eb; /* Professional Blue */
            --primary-dark: #1d4ed8;
            --secondary: #64748b; /* Neutral Gray */
            --accent: #0ea5e9; /* Light Blue */
            --success: #10b981; /* Success Green */
            --warning: #f59e0b; /* Warning Orange */
            --error: #ef4444; /* Error Red */
            --info: #06b6d4; /* Info Cyan */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #e1e5f2;
            background: #0a0a0f;
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            position: relative;
            overflow-x: hidden;
        }

        /* Galaxy Video Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('../assets/images/3194277-hd_1920_1080_30fps.mp4') center/cover;
            z-index: -2;
            pointer-events: none;
        }

        .galaxy-video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
            pointer-events: none;
        }

        /* Galaxy overlay */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(ellipse at top, rgba(139, 92, 246, 0.15) 0%, rgba(0, 0, 0, 0.8) 70%),
                        radial-gradient(ellipse at bottom right, rgba(34, 211, 238, 0.1) 0%, transparent 50%),
                        radial-gradient(ellipse at bottom left, rgba(168, 85, 247, 0.08) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        /* Sidebar - Galaxy Theme */
        .sidebar {
            width: 256px;
            background: rgba(15, 23, 42, 0.95);
            color: #e1e5f2;
            padding: 0;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(20px);
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
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(15, 23, 42, 0.9);
            z-index: 1002;
            margin-top: 64px;
            min-height: 64px;
            backdrop-filter: blur(12px);
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
            color: #f1f5f9;
            z-index: 1003;
            position: relative;
        }

        .sidebar-logo img {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .sidebar-logo-text {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #f1f5f9;
            text-shadow: 0 0 10px rgba(139, 92, 246, 0.5);
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
            color: rgba(241, 245, 249, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin: 4px 16px;
            font-weight: 500;
            font-size: 15px;
            min-height: 48px;
            position: relative;
            border: 1px solid transparent;
        }


        .sidebar-nav a:hover {
            background: rgba(139, 92, 246, 0.15);
            color: #f1f5f9;
            transform: translateX(4px);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            border-color: rgba(139, 92, 246, 0.4);
        }

        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
            border-color: rgba(139, 92, 246, 0.6);
        }

        .sidebar-nav .icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
            color: rgba(241, 245, 249, 0.6);
        }

        .sidebar-nav a.active .icon {
            color: #ffffff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .sidebar-nav a:hover .icon {
            color: #f1f5f9;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            background: transparent;
            margin-left: 256px;
        }

        .main-content.sidebar-collapsed {
            margin-left: 72px;
        }

        /* Header - Galaxy Theme */
        .header {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(139, 92, 246, 0.3);
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            color: #e1e5f2;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
            height: 70px;
            backdrop-filter: blur(20px);
        }

        /* Force header elements to be positioned right after menu button */
        .header > .menu-button {
            order: 1;
        }

        .header > .header-center {
            order: 2;
        }

        .header > .header-actions {
            order: 3;
        }

        .header-center {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: none;
            justify-content: flex-start;
            margin-left: 0 !important;
            padding-left: 0 !important;
        }

        /* Clean Menu Button */
        .menu-button {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #e1e5f2;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 20px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
            backdrop-filter: blur(10px);
        }

        .menu-button:hover {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
        }

        .menu-button:active {
            background: var(--primary-dark);
            transform: scale(0.95);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #f1f5f9;
            white-space: nowrap;
            text-shadow: 0 0 15px rgba(139, 92, 246, 0.5);
        }

        .section-info {
            display: flex;
            align-items: center;
        }

        .section-badge {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(139, 92, 246, 0.5);
            white-space: nowrap;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
            margin-left: 4px;
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
            background: rgba(15, 23, 42, 0.8);
            color: #e1e5f2;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(139, 92, 246, 0.3);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
            backdrop-filter: blur(10px);
        }

        .notification-icon {
            position: relative;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #e1e5f2;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 20px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
            backdrop-filter: blur(10px);
        }

        .notification-icon:hover {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
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
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid rgba(239, 68, 68, 0.5);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
        }

        .logout:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            background: transparent;
            margin-top: 80px;
        }

        /* Clean Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
            border: 1px solid var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
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

            .header-center {
                margin-left: 0;
                gap: 8px;
            }

            .header-center h1 {
                font-size: 18px;
                gap: 8px;
            }

            .section-badge {
                font-size: 11px;
                padding: 3px 8px;
                margin-left: 4px;
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
    <!-- Galaxy Video Background -->
    <video class="galaxy-video" autoplay muted loop>
        <source src="../assets/images/3194277-hd_1920_1080_30fps.mp4" type="video/mp4">
    </video>

    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="student_dashboard.php" class="sidebar-logo">
                <img src="../assets/images/comprelogo2.png" alt="CompreLearn Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div style="display: none; align-items: center; gap: 8px;">
                    <i class="fas fa-book icon"></i>
                    <span class="sidebar-logo-text">CompreLearn</span>
                </div>
            </a>
        </div>
        <ul class="sidebar-nav">
            <li><a href="student_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                <i class="fas fa-home icon"></i> <span class="nav-text">Dashboard</span>
            </a></li>
            <li><a href="student_materials.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_materials.php' ? 'active' : ''; ?>" data-tooltip="Materials">
                <i class="fas fa-book icon"></i> <span class="nav-text">Materials</span>
            </a></li>
            
            <li><a href="clean_question_viewer.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clean_question_viewer.php' ? 'active' : ''; ?>" data-tooltip="Questions">
                <i class="fas fa-question-circle icon"></i> <span class="nav-text">Questions</span>
            </a></li>
            <li><a href="student_analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_analytics.php' ? 'active' : ''; ?>" data-tooltip="Analytics">
                <i class="fas fa-chart-line icon"></i> <span class="nav-text">Analytics</span>
            </a></li>
            <li><a href="student_practice.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_practice.php' ? 'active' : ''; ?>" data-tooltip="Practice">
                <i class="fas fa-dumbbell icon"></i> <span class="nav-text">Practice</span>
            </a></li>
            <li><a href="student_announcements.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_announcements.php' ? 'active' : ''; ?>" data-tooltip="Announcements">
                <i class="fas fa-bullhorn icon"></i> <span class="nav-text">Announcements</span>
            </a></li>
            
            
        </ul>
    </nav>


    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <button class="menu-button" id="menuButton" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-center">
                <h1><i class="fas fa-graduation-cap"></i> Student Portal</h1>
                <div class="section-info">
                    <span class="section-badge"><?php echo h($sectionName); ?></span>
                </div>
            </div>
            
            <div class="header-actions">
                <span class="chip">Hi, <?php echo h($studentName); ?>!</span>
                <button class="notification-icon" onclick="openNotifications()" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
                <a class="logout" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

    <!-- Notifications Modal -->
    <div id="notificationsModal" style="position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:flex-start; justify-content:center; z-index:2000; padding:80px 16px 24px;">
        <div style="width:min(900px,95vw); background:#fff; border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,.2); overflow:hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
                <strong><i class="fas fa-bell"></i> Notifications</strong>
                <button onclick="closeNotifications()" style="border:none; background:#ef4444; color:#fff; width:28px; height:28px; border-radius:9999px; font-weight:700; cursor:pointer">×</button>
            </div>
            <div id="notificationsBody" style="max-height:70vh; overflow:auto; padding:16px;">
                <div style="color:#64748b;">Loading...</div>
            </div>
        </div>
    </div>

    <script>
    function openNotifications(){
        const modal = document.getElementById('notificationsModal');
        const body = document.getElementById('notificationsBody');
        modal.style.display = 'flex';
        body.innerHTML = '<div style="color:#64748b;">Loading...</div>';
        fetch('../Student/notifications_feed.php',{ credentials: 'same-origin' })
            .then(r=>r.text())
            .then(html=>{ body.innerHTML = html; })
            .catch(()=>{ body.innerHTML = '<div style="color:#ef4444;">Failed to load notifications.</div>'; });
    }
    function closeNotifications(){ document.getElementById('notificationsModal').style.display='none'; }
    </script>
</body>
</html>
