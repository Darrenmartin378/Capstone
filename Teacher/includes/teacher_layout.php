<?php
function render_teacher_header(string $active, string $teacherName, string $pageTitle = 'Teacher Dashboard'): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Modern Teacher Color Palette */
            --primary: #1a73e8; /* Google Blue */
            --primary-dark: #1557b0;
            --secondary: #34a853; /* Google Green */
            --accent: #ea4335; /* Google Red */
            --warning: #fbbc04; /* Google Yellow */
            --purple: #9c27b0; /* Creative Purple */
            --teal: #00bcd4; /* Professional Teal */
            --success: #4caf50; /* Success Green */
            --error: #f44336; /* Error Red */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e8f0fe;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --text-muted: #9aa0a6;
            --border: #dadce0;
            --shadow-sm: 0 1px 2px 0 rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15);
            --shadow-md: 0 1px 2px 0 rgba(60,64,67,.3), 0 2px 6px 2px rgba(60,64,67,.15);
            --shadow-lg: 0 2px 4px 0 rgba(60,64,67,.3), 0 4px 8px 3px rgba(60,64,67,.15);
            --shadow-xl: 0 4px 8px 0 rgba(60,64,67,.3), 0 8px 16px 4px rgba(60,64,67,.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            margin: 0;
            color: var(--text-primary);
            line-height: 1.5;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            min-height: 64px;
            height: 64px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .menu-button {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 20px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .menu-button:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .menu-button:active {
            background: var(--bg-tertiary);
            transform: scale(0.95);
        }
        .teacher-name {
            font-weight: 500;
            font-size: 22px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .teacher-badge {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            font-size: 13px;
        }
        
        .section-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .section-badge {
            background: linear-gradient(135deg, #34a853, #2e7d32);
            color: white;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(52, 168, 83, 0.3);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--bg-primary);
        }
        .username {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
            white-space: nowrap;
        }
        .notification-icon {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 20px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .notification-icon:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-primary);
        }
        .logout-btn {
            background: var(--error);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
        }
        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .main-container {
            display: flex;
            min-height: 100vh;
            margin-top: 64px;
        }
        .sidebar {
            width: 256px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 0;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 64px);
            position: fixed;
            top: 64px;
            left: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar.collapsed {
            width: 72px;
        }
        .sidebar-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 64px;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
            text-decoration: none;
        }
        .sidebar-logo img {
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }
        .sidebar-logo-text {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .sidebar.collapsed .sidebar-logo-text {
            display: none;
        }
        .sidebar-nav {
            padding: 8px 0;
            flex: 1;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 16px;
            font-weight: 400;
            color: var(--text-secondary);
            padding: 8px 24px;
            border-radius: 0 25px 25px 0;
            margin-right: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 14px;
            min-height: 40px;
        }
        .sidebar-nav a:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .sidebar-nav a.active {
            background: var(--bg-tertiary);
            color: var(--primary);
            font-weight: 500;
        }
        .sidebar-nav a .icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }
        .sidebar-nav a.active .icon {
            color: var(--primary);
        }
        .sidebar.collapsed .sidebar-nav a .nav-text {
            display: none;
        }
        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            margin-right: 0;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            margin: 4px auto;
        }
        .content {
            flex: 1;
            padding: 24px;
            margin-left: 256px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--bg-secondary);
            min-height: calc(100vh - 64px);
        }
        .sidebar.collapsed ~ .content {
            margin-left: 72px;
        }
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
            z-index: 1000;
            margin-left: 8px;
            box-shadow: var(--shadow-md);
        }
        .sidebar.collapsed .sidebar-nav a:hover::after {
            opacity: 1;
            visibility: visible;
        }
        .sidebar.collapsed .sidebar-nav a {
            position: relative;
        }

        /* Universal Flash Message Styles */
        .flash {
            background: #fde68a;
            color: #b45309;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 18px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(251,191,36,0.08);
            position: relative;
            transition: all 0.3s ease;
        }
        .flash-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .flash-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .flash-close {
            position: absolute;
            right: 14px;
            top: 10px;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: inherit;
            cursor: pointer;
            line-height: 1;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .flash-close:hover {
            opacity: 1;
        }

        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                margin-top: 64px;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid var(--border);
                flex-direction: row;
                overflow-x: auto;
                padding: 0;
            }
            .sidebar-header {
                display: none;
            }
            .sidebar-nav {
                display: flex;
                flex-direction: row;
                padding: 8px;
                gap: 4px;
            }
            .sidebar-nav a {
                flex-shrink: 0;
                border-radius: 20px;
                padding: 8px 16px;
                margin: 0;
                min-height: 36px;
                font-size: 13px;
            }
            .sidebar-nav a .nav-text {
                display: block;
            }
            .sidebar-nav a .icon {
                font-size: 16px;
                width: 20px;
            }
            .content {
                margin-left: 0;
                padding: 16px;
            }
            .header-bar {
                padding: 8px 12px;
            }
            .teacher-name {
                font-size: 18px;
            }
            .user-info {
                gap: 8px;
            }
            .username {
                display: none;
            }
        }
    </style>
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('teacher-sidebar');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            localStorage.setItem('teacherSidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            var sidebar = document.getElementById('teacher-sidebar');
            var isCollapsed = localStorage.getItem('teacherSidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            var sidebar = document.getElementById('teacher-sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
            }
        });
    </script>
</head>
<body>
<div class="header-bar">
    <div class="header-left">
        <button class="menu-button" onclick="toggleSidebar()" title="Toggle Sidebar">
            ☰
        </button>
        <div class="teacher-name">
            Teacher Portal
            <span class="teacher-badge">Educator</span>
            <?php if (!empty($teacherSections)): ?>
                <div class="section-info">
                    <span class="section-label">Sections:</span>
                    <?php foreach ($teacherSections as $section): ?>
                        <span class="section-badge"><?= htmlspecialchars($section['section_name'] ?: $section['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="user-info">
        <button class="notification-icon" title="Notifications">
            🔔
            <span class="notification-badge">3</span>
        </button>
        <div class="avatar">
            <?= isset($_SESSION['teacher_name']) ? strtoupper(substr($_SESSION['teacher_name'],0,2)) : 'UI' ?>
        </div>
        <span class="username">
            <?= isset($_SESSION['teacher_name']) ? htmlspecialchars($_SESSION['teacher_name']) : 'Uchiha Itachi' ?>
        </span>
        <form method="post" action="teacher_logout.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to logout?');">
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
</div>
<div class="main-container">
    <nav class="sidebar" id="teacher-sidebar">
        <div class="sidebar-header">
            <a href="teacher_dashboard.php" class="sidebar-logo">
                <img src="../assets/images/comprelogo.png" alt="CompreLearn">
                <span class="sidebar-logo-text">CompreLearn</span>
            </a>
        </div>
        <div class="sidebar-nav">
            <a href="teacher_dashboard.php" class="<?= $active == 'teacher_dashboard.php' ? 'active' : '' ?>" data-tooltip="Dashboard">
                <span class="icon">🏠</span> <span class="nav-text">Dashboard</span>
            </a>
            <a href="teacher_content.php" class="<?= $active == 'teacher_content.php' ? 'active' : '' ?>" data-tooltip="Content">
                <span class="icon">📁</span> <span class="nav-text">Content Management</span>
            </a>
            <a href="clean_question_creator.php" class="<?= $active == 'clean_question_creator.php' ? 'active' : '' ?>" data-tooltip="Questions">
                <span class="icon">❓</span> <span class="nav-text">Questions Management</span>
            </a>
            <a href="teacher_practice_tests.php" class="<?= $active == 'teacher_practice_tests.php' ? 'active' : '' ?>" data-tooltip="Practice Tests">
                <span class="icon">🔥</span> <span class="nav-text">Practice Sets Management</span>
            </a>
            
            <a href="teacher_announcements.php" class="<?= $active == 'teacher_announcements.php' ? 'active' : '' ?>" data-tooltip="Announcements">
                <span class="icon">📣</span> <span class="nav-text">Announcements Management</span>
            </a>
            <a href="teacher_analytics.php" class="<?= $active == 'teacher_analytics.php' ? 'active' : '' ?>" data-tooltip="Analytics">
                <span class="icon">📊</span> <span class="nav-text">Analytics</span>
            </a>
            <a href="teacher_account.php" class="<?= $active == 'teacher_account.php' ? 'active' : '' ?>" data-tooltip="Account">
                <span class="icon">👤</span> <span class="nav-text">Account</span>
            </a>
        </div>
    </nav>
    <div class="content">
    <?php
}

function render_teacher_footer(): void {
    ?>
            </div>
        </main>
    </div>
</div> <!-- end .content -->
</div> <!-- end .main-container -->

<script>
// Universal flash message functionality
function closeFlashMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateX(100%)';
        message.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            message.style.display = 'none';
        }, 300);
    }
}

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            if (message.style.display !== 'none') {
                closeFlashMessage(message.id);
            }
        }, 5000); // Auto-dismiss after 5 seconds
    });
});

// Clear URL parameters to prevent messages from showing on refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    const paramsToRemove = ['graded', 'success', 'error', 'message', 'saved', 'deleted'];
    let hasChanges = false;
    
    paramsToRemove.forEach(param => {
        if (url.searchParams.has(param)) {
            url.searchParams.delete(param);
            hasChanges = true;
        }
    });
    
    if (hasChanges) {
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
}
</script>

<!-- SortableJS Library for drag and drop functionality -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

</body>
</html>
    <?php
}
?>


