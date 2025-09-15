<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Admin Panel - Compre Learn</title>
    <style>
        :root {
            --light-bg: #f6f8fa;
            --light-bg-secondary: #ffffff;
            --light-surface: #e9ecef;
            --primary-accent: #0f3460;
            --secondary-accent: #e94560;
            --light-text: #222;
            --grey-text: #555;
            --card-shadow: 0 6px 24px rgba(0,0,0,0.08), 0 1.5px 4px rgba(0,0,0,0.06);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        body { 
            background: var(--light-bg-secondary); 
            color: var(--light-text); 
            min-height: 100vh; 
            overflow-x: hidden; 
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid rgba(15,52,96,0.1);
            color: var(--light-text);
            padding: 1.2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1001;
            gap: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            transition: margin-left .3s cubic-bezier(.4,0,.2,1);
            margin-left: 80px;
            backdrop-filter: blur(10px);
        }
        
        body.sidebar-hidden .dashboard-header {
            margin-left: 0 !important;
        }
        
        .logo-container { 
            display: flex; 
            align-items: center; 
            gap: 16px;
        }
        
        .logo-image { 
            height: 44px; 
            margin-right: 16px; 
            max-width: 100%; 
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); 
        }
        
        .dashboard-header h1 { 
            font-size: 2.2rem; 
            letter-spacing: 1.2px; 
            color: var(--primary-accent);
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        
        .profile-icon {
            font-size: 2.2rem;
            background: linear-gradient(135deg, var(--primary-accent), #1a4b73);
            color: #fff;
            border-radius: 50%;
            padding: 8px 12px;
            box-shadow: 0 4px 12px rgba(15,52,96,0.2);
            border: 2px solid rgba(255,255,255,0.1);
        }
        
        .admin-name {
            font-weight: 600;
            color: var(--light-text);
            font-size: 1.1rem;
            margin-right: 8px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, var(--secondary-accent), #ff4757);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            cursor: pointer;
            transition: all .3s cubic-bezier(.4,0,.2,1);
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(233,69,96,0.3);
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #e94560, #ff3742);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(233,69,96,0.4);
        }
        
        .container {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 0 2rem;
            transition: margin-left .3s cubic-bezier(.4,0,.2,1);
            margin-left: 80px;
            background: var(--light-bg-secondary);
            color: var(--light-text);
            border-radius: 16px;
        }
        
        body.sidebar-hidden .container {
            margin-left: 0 !important;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px;
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-accent) 0%, #0a2a4a 100%);
            color: #fff;
            box-shadow: 0 0 32px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.05);
            z-index: 1100;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            border-radius: 0 20px 20px 0;
            transition: transform .3s cubic-bezier(.4,0,.2,1), box-shadow .3s, width .3s;
            backdrop-filter: blur(10px);
        }
        
        .sidebar.hide {
            transform: translateX(-80px);
            box-shadow: none;
        }
        
        .sidebar-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
            padding: 12px 8px;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo img {
            height: 36px;
            margin-bottom: 6px;
            filter: brightness(1.1) contrast(1.1);
        }
        
        .sidebar-logo span {
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu {
            list-style: none;
            width: 100%;
            padding: 0 8px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .sidebar-menu a {
            width: 100%;
            background: none;
            border: none;
            color: rgba(255,255,255,0.8);
            font-size: 1.4rem;
            padding: 14px 0;
            text-align: center;
            cursor: pointer;
            border-radius: 12px;
            transition: all .3s cubic-bezier(.4,0,.2,1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--secondary-accent), #ff6b7a);
            opacity: 0;
            transition: opacity .3s;
            border-radius: 12px;
        }
        
        .sidebar-menu a:hover::before,
        .sidebar-menu a.active::before {
            opacity: 1;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233,69,96,0.3);
        }
        
        .sidebar-menu a span {
            position: relative;
            z-index: 1;
        }
        
        .sidebar-toggle-btn {
            background: linear-gradient(135deg, var(--primary-accent), #1a4b73);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(15,52,96,0.2);
            transition: all .3s cubic-bezier(.4,0,.2,1);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-toggle-btn:hover {
            background: linear-gradient(135deg, var(--secondary-accent), #ff4757);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233,69,96,0.3);
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
                width: 70px;
            }
            
            .dashboard-header, .container {
                margin-left: 70px;
            }
            
            .sidebar-logo span {
                display: none;
            }
            
            .sidebar-menu a {
                font-size: 1.2rem;
                padding: 12px 0;
            }
        }
        
        /* Mobile portrait */
        @media (max-width: 768px) {
            .sidebar { 
                position: fixed;
                top: 0;
                left: -80px;
                width: 80px; 
                height: 100vh;
                flex-direction: column;
                padding: 20px 0;
                transition: left .3s ease;
                z-index: 1100;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .dashboard-header, .container { 
                margin-left: 0;
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .logo-container {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
                text-align: center;
                flex: 1;
            }
            
            .admin-profile {
                width: 100%;
                justify-content: center;
                gap: 12px;
            }
            
            .admin-name {
                font-size: 1rem;
            }
            
            .logout-btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .container {
                margin: 1rem auto;
                padding: 0 1rem;
                border-radius: 12px;
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
                font-size: 1.8rem;
                padding: 6px 10px;
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
            background: var(--light-surface);
            color: var(--light-text);
            border: 1px solid var(--secondary-accent);
            padding: 12px 16px;
            border-radius: 8px;
            margin: 0 0 1rem;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--card-shadow);
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
            background: #3498db;
            color: #fff;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: background .2s;
            font-size: .95rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        
        .copy-btn:hover { 
            background: #217dbb; 
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/comprelogo.png" alt="Compre Learn Logo">
            <span>Compre Learn</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" title="Dashboard" <?php echo $current_page === 'admin_dashboard' ? 'class="active"' : ''; ?>><span>📊</span></a></li>
            <li><a href="admin_teachers.php" title="Teachers" <?php echo $current_page === 'admin_teachers' ? 'class="active"' : ''; ?>><span>👨‍🏫</span></a></li>
            <li><a href="admin_students.php" title="Students" <?php echo $current_page === 'admin_students' ? 'class="active"' : ''; ?>><span>🎓</span></a></li>
            <li><a href="admin_parents.php" title="Parents" <?php echo $current_page === 'admin_parents' ? 'class="active"' : ''; ?>><span>👨‍👩‍👧‍👦</span></a></li>
            <li><a href="admin_sections.php" title="Sections" <?php echo $current_page === 'admin_sections' ? 'class="active"' : ''; ?>><span>🏫</span></a></li>
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
            <span class="profile-icon">👤</span>
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
                window.location.href = 'admin_login.php'; 
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

