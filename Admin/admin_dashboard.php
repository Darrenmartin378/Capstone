<?php
require_once __DIR__ . '/includes/admin_init.php';

$page_title = 'Dashboard';

// Get statistics
$teacher_count = $conn->query("SELECT COUNT(*) AS cnt FROM teachers")->fetch_assoc()['cnt'];
$student_count = $conn->query("SELECT COUNT(*) AS cnt FROM students")->fetch_assoc()['cnt'];
$parent_count = $conn->query("SELECT COUNT(*) AS cnt FROM parents")->fetch_assoc()['cnt'];
$section_count = $conn->query("SELECT COUNT(*) AS cnt FROM sections")->fetch_assoc()['cnt'];

// Get recent activity (last 5 users of each type)
$recent_teachers = $conn->query("SELECT name, created_at FROM teachers ORDER BY created_at DESC LIMIT 5");
$recent_students = $conn->query("SELECT name, created_at FROM students ORDER BY created_at DESC LIMIT 5");
$recent_parents = $conn->query("SELECT name, created_at FROM parents ORDER BY created_at DESC LIMIT 5");

// Get additional statistics for better insights
$recent_activity = $conn->query("
    SELECT 'teacher' as type, name, created_at FROM teachers 
    UNION ALL 
    SELECT 'student' as type, name, created_at FROM students 
    UNION ALL 
    SELECT 'parent' as type, name, created_at FROM parents 
    ORDER BY created_at DESC LIMIT 10
");

// Get section statistics
$sections_with_counts = $conn->query("
    SELECT s.name, COUNT(st.id) as student_count 
    FROM sections s 
    LEFT JOIN students st ON s.id = st.section_id 
    GROUP BY s.id, s.name 
    ORDER BY student_count DESC
");

// Start output buffering
ob_start();
?>

<style>
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--light-surface);
        color: var(--light-text);
        border: 2px solid transparent;
        box-shadow: var(--card-shadow);
        transition: border-color .3s, box-shadow .3s, transform .2s;
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
    }
    
    .stat-card:hover {
        border-color: var(--secondary-accent);
        box-shadow: 0 10px 32px rgba(233,69,96,0.08), var(--card-shadow);
        transform: translateY(-2px) scale(1.02);
    }
    
    .stat-card::before {
        content: "";
        position: absolute; 
        right: -30px; 
        top: -30px;
        width: 60px; 
        height: 60px;
        background: var(--secondary-accent);
        opacity: .08;
        border-radius: 50%;
        z-index: 0;
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
        display: block;
    }
    
    .stat-number { 
        font-size: 2.5rem; 
        font-weight: 700;
        margin-bottom: 8px; 
        color: var(--primary-accent);
    }
    
    .stat-label { 
        color: var(--grey-text); 
        font-size: 1.1rem; 
        font-weight: 500;
    }
    
    .dashboard-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .activity-section {
        background: var(--light-surface);
        color: var(--light-text);
        padding: 24px;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        animation: fadeIn .7s;
    }
    
    .activity-section h3 {
        color: var(--primary-accent);
        margin-bottom: 20px;
        font-size: 1.3rem;
        border-bottom: 2px solid var(--primary-accent);
        padding-bottom: 10px;
    }
    
    .activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .activity-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-name {
        font-weight: 500;
        color: var(--light-text);
    }
    
    .activity-date {
        color: var(--grey-text);
        font-size: 0.9rem;
    }
    
    .activity-type {
        color: var(--secondary-accent);
        font-weight: 600;
        font-size: 0.85rem;
        margin-right: 8px;
    }
    
    .quick-actions {
        background: var(--light-surface);
        color: var(--light-text);
        padding: 24px;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        animation: fadeIn .7s;
        grid-column: 1 / -1;
    }
    
    .quick-actions h3 {
        color: var(--primary-accent);
        margin-bottom: 20px;
        font-size: 1.3rem;
        border-bottom: 2px solid var(--primary-accent);
        padding-bottom: 10px;
    }
    
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    
    .quick-action-btn {
        background: var(--primary-accent);
        color: #fff;
        border: none;
        padding: 16px 20px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        transition: background .3s, transform .2s;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .quick-action-btn:hover {
        background: var(--secondary-accent);
        transform: translateY(-2px);
        color: #fff;
    }
    
    .quick-action-btn i {
        font-size: 1.2rem;
    }
    
    /* Responsive Design */
    
    /* Tablet and smaller desktop */
    @media (max-width: 1200px) {
        .dashboard-stats {
            gap: 20px;
        }
        
        .stat-card {
            padding: 20px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
        }
        
        .quick-actions-grid {
            gap: 16px;
        }
    }
    
    /* Mobile landscape and small tablets */
    @media (max-width: 900px) {
        .dashboard-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .stat-card {
            padding: 18px;
            min-height: 120px;
        }
        
        .stat-icon {
            font-size: 2.2rem;
        }
        
        .stat-number {
            font-size: 1.8rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
        }
        
        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        
        .quick-action-btn {
            padding: 16px 12px;
            font-size: 0.9rem;
        }
    }
    
    /* Mobile portrait */
    @media (max-width: 768px) {
        .dashboard-content {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .dashboard-stats {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .stat-card {
            padding: 16px;
            min-height: 100px;
            flex-direction: row;
            align-items: center;
            gap: 16px;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0;
        }
        
        .stat-number {
            font-size: 1.6rem;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 0.85rem;
        }
        
        .quick-actions-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .quick-action-btn {
            padding: 14px 16px;
            font-size: 0.9rem;
            justify-content: flex-start;
            gap: 12px;
        }
        
        .quick-action-btn span {
            font-size: 1.1rem;
        }
    }
    
    /* Small mobile devices */
    @media (max-width: 480px) {
        .dashboard-stats {
            gap: 10px;
        }
        
        .stat-card {
            padding: 14px;
            min-height: 90px;
            gap: 12px;
        }
        
        .stat-icon {
            font-size: 1.8rem;
        }
        
        .stat-number {
            font-size: 1.4rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
        }
        
        .quick-actions-grid {
            gap: 10px;
        }
        
        .quick-action-btn {
            padding: 12px 14px;
            font-size: 0.85rem;
            gap: 10px;
        }
        
        .quick-action-btn span {
            font-size: 1rem;
        }
    }
</style>

<div class="dashboard-stats">
    <div class="stat-card" onclick="window.location.href='admin_teachers.php'">
        <span class="stat-icon">👨‍🏫</span>
                <div class="stat-number"><?php echo $teacher_count; ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>
    
    <div class="stat-card" onclick="window.location.href='admin_students.php'">
        <span class="stat-icon">🎓</span>
                <div class="stat-number"><?php echo $student_count; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
    
    <div class="stat-card" onclick="window.location.href='admin_parents.php'">
        <span class="stat-icon">👨‍👩‍👧‍👦</span>
                <div class="stat-number"><?php echo $parent_count; ?></div>
                <div class="stat-label">Total Parents</div>
            </div>
    
    <div class="stat-card" onclick="window.location.href='admin_sections.php'">
        <span class="stat-icon">🏫</span>
                <div class="stat-number"><?php echo $section_count; ?></div>
                <div class="stat-label">Total Sections</div>
            </div>
        </div>

<div class="dashboard-content">
    <div class="activity-section">
        <h3>Recent Teachers</h3>
        <ul class="activity-list">
            <?php while($teacher = $recent_teachers->fetch_assoc()): ?>
            <li class="activity-item">
                <span class="activity-name"><?php echo h($teacher['name']); ?></span>
                <span class="activity-date"><?php echo date('M j, Y', strtotime($teacher['created_at'])); ?></span>
            </li>
                        <?php endwhile; ?>
        </ul>
                </div>

    <div class="activity-section">
        <h3>Recent Students</h3>
        <ul class="activity-list">
            <?php while($student = $recent_students->fetch_assoc()): ?>
            <li class="activity-item">
                <span class="activity-name"><?php echo h($student['name']); ?></span>
                <span class="activity-date"><?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
            </li>
                        <?php endwhile; ?>
        </ul>
                </div>

    <div class="activity-section">
        <h3>Recent Parents</h3>
        <ul class="activity-list">
            <?php while($parent = $recent_parents->fetch_assoc()): ?>
            <li class="activity-item">
                <span class="activity-name"><?php echo h($parent['name']); ?></span>
                <span class="activity-date"><?php echo date('M j, Y', strtotime($parent['created_at'])); ?></span>
            </li>
                        <?php endwhile; ?>
        </ul>
        </div>
    </div>

    <div class="activity-section">
        <h3>Recent Activity</h3>
        <ul class="activity-list">
            <?php while($activity = $recent_activity->fetch_assoc()): ?>
            <li class="activity-item">
                <span class="activity-name">
                    <span class="activity-type"><?php echo ucfirst($activity['type']); ?>:</span>
                    <?php echo h($activity['name']); ?>
                </span>
                <span class="activity-date"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></span>
            </li>
                        <?php endwhile; ?>
        </ul>
    </div>

    <div class="activity-section">
        <h3>Section Statistics</h3>
        <ul class="activity-list">
            <?php while($section = $sections_with_counts->fetch_assoc()): ?>
            <li class="activity-item">
                <span class="activity-name"><?php echo h($section['name']); ?></span>
                <span class="activity-date"><?php echo $section['student_count']; ?> students</span>
            </li>
                        <?php endwhile; ?>
        </ul>
    </div>

<div class="quick-actions">
    <h3>Quick Actions</h3>
    <div class="quick-actions-grid">
        <a href="admin_teachers.php" class="quick-action-btn">
            <span>👨‍🏫</span>
            Manage Teachers
        </a>
        <a href="admin_students.php" class="quick-action-btn">
            <span>🎓</span>
            Manage Students
        </a>
        <a href="admin_parents.php" class="quick-action-btn">
            <span>👨‍👩‍👧‍👦</span>
            Manage Parents
        </a>
        <a href="admin_sections.php" class="quick-action-btn">
            <span>🏫</span>
            Manage Sections
        </a>
        </div>
    </div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/includes/admin_layout.php';
?>