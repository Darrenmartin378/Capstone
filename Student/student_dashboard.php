<?php
require_once 'includes/student_init.php';

$pageTitle = 'Dashboard';

// Handle success message from login
$success_message = '';
if (isset($_GET['login']) && $_GET['login'] == 'success') {
    $success_message = 'Welcome back! You have successfully logged in.';
}

// Get counts for dashboard stats
$materialsCount = 0;
$materialsRes = $conn->query("SELECT COUNT(*) as count FROM reading_materials");
if ($materialsRes && $row = $materialsRes->fetch_assoc()) {
    $materialsCount = (int)$row['count'];
}

$testsCount = 0;
$testsRes = $conn->query("SELECT COUNT(DISTINCT a.id) as count 
                         FROM assessments a
                         JOIN assessment_assignments aa ON aa.assessment_id = a.id
                         WHERE aa.section_id = $studentSectionId");
if ($testsRes && $row = $testsRes->fetch_assoc()) {
    $testsCount = (int)$row['count'];
}

$questionsCount = 0;
$questionsRes = $conn->query("
    SELECT COUNT(DISTINCT qs.id) as count 
    FROM question_sets qs
    WHERE qs.section_id = $studentSectionId
    AND qs.set_title IS NOT NULL 
    AND qs.set_title != ''
");
if ($questionsRes && $row = $questionsRes->fetch_assoc()) {
    $questionsCount = (int)$row['count'];
}

$notificationsCount = 0;
$notificationsRes = $conn->query("SELECT COUNT(*) as count FROM announcements");
if ($notificationsRes && $row = $notificationsRes->fetch_assoc()) {
    $notificationsCount = (int)$row['count'];
}
ob_start();
?>
<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    .dashboard-card {
        background: var(--card);
        border: 2px solid #d9f2ff;
        border-radius: 18px;
        box-shadow: 0 10px 20px rgba(43,144,217,.15);
        padding: 24px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(43,144,217,.25);
        border-color: var(--primary);
    }
    .dashboard-card .icon {
        font-size: 48px;
        margin-bottom: 16px;
        display: block;
    }
    .dashboard-card h3 {
        margin: 0 0 8px 0;
        color: #17415e;
        font-size: 18px;
    }
    .dashboard-card .count {
        font-size: 32px;
        font-weight: bold;
        color: var(--primary);
        margin: 8px 0;
    }
    .dashboard-card .description {
        color: var(--muted);
        font-size: 14px;
        margin: 0;
    }
    .welcome-section {
        background:blue;
        color: white;
        padding: 30px;
        border-radius: 18px;
        margin-bottom: 30px;
        text-align: center;
    }
    .welcome-section h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
    }
    .welcome-section p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 30px 0;
    }
    .quick-action-btn {
        background: linear-gradient(135deg, var(--teal), #74f2c2);
        color: white;
        border: none;
        padding: 15px 20px;
        border-radius: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
            align-items: center;
            justify-content: center;
        gap: 8px;
    }
    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(16,185,129,.3);
    }
    .recent-activity {
        background: var(--card);
        border: 2px solid #d9f2ff;
        border-radius: 18px;
            padding: 24px;
        margin: 20px 0;
    }
    .recent-activity h3 {
        margin: 0 0 20px 0;
        color: #17415e;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .activity-item {
            display: flex;
        align-items: center;
            gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #e3eef8;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-icon {
        font-size: 20px;
        width: 30px;
        text-align: center;
    }
    .activity-content {
        flex: 1;
    }
    .activity-title {
        font-weight: 600;
        color: #17415e;
        margin: 0 0 4px 0;
    }
    .activity-time {
        color: var(--muted);
        font-size: 12px;
        margin: 0;
    }
    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
    </style>

<?php if ($success_message): ?>
<div class="success-message" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 16px; border-radius: 8px; margin: 0 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); animation: fadeIn 0.7s;">
    <?php echo h($success_message); ?>
</div>
<?php endif; ?>

<div class="welcome-section">
    <h1>Welcome back, <?php echo h($studentName); ?>! 👋</h1>
    <p>Ready to continue your learning journey? Let's make today productive!</p>
    </div>

<div class="dashboard-grid">
    <a href="student_materials.php" class="dashboard-card">
        <span class="icon">📗</span>
        <h3>Materials</h3>
        <div class="count"><?php echo $materialsCount; ?></div>
        <p class="description">Reading materials and resources</p>
    </a>

    <a href="student_tests.php" class="dashboard-card">
        <span class="icon">📝</span>
        <h3>My Tests</h3>
        <div class="count"><?php echo $testsCount; ?></div>
        <p class="description">Assigned assessments and quizzes</p>
    </a>

    <a href="clean_question_viewer.php" class="dashboard-card">
        <span class="icon">❓</span>
        <h3>Questions</h3>
        <div class="count"><?php echo $questionsCount; ?></div>
        <p class="description">Teacher questions to answer</p>
    </a>

    <a href="student_notifications.php" class="dashboard-card">
        <span class="icon">🔔</span>
        <h3>Notifications</h3>
        <div class="count"><?php echo $notificationsCount; ?></div>
        <p class="description">Announcements and updates</p>
    </a>
        </div>

<div class="quick-actions">
    <a href="student_materials.php" class="quick-action-btn">
        <span>📚</span> Browse Materials
    </a>
    <a href="student_tests.php" class="quick-action-btn">
        <span>✏️</span> Take Tests
    </a>
    <a href="clean_question_viewer.php" class="quick-action-btn">
        <span>💭</span> Answer Questions
    </a>
    <a href="student_notifications.php" class="quick-action-btn">
        <span>📢</span> View Notifications
    </a>
            </div>

<div class="recent-activity">
    <h3><span>📊</span> Recent Activity</h3>
    
    <?php
    // Get recent announcements
    $recentAnnouncements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
    if ($recentAnnouncements && $recentAnnouncements->num_rows > 0):
        while ($announcement = $recentAnnouncements->fetch_assoc()):
    ?>
        <div class="activity-item">
            <div class="activity-icon">📢</div>
            <div class="activity-content">
                <div class="activity-title"><?php echo h($announcement['title']); ?></div>
                <div class="activity-time"><?php echo h(date('M j, Y g:ia', strtotime($announcement['created_at']))); ?></div>
                            </div>
                        </div>
    <?php 
        endwhile;
    else:
    ?>
        <div class="activity-item">
            <div class="activity-icon">💡</div>
            <div class="activity-content">
                <div class="activity-title">Welcome to CompreLearn!</div>
                <div class="activity-time">Start exploring your learning materials</div>
                </div>
            </div>
            <?php endif; ?>
    </div>

    <script>
// Add some interactive animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate dashboard cards on load
    const cards = document.querySelectorAll('.dashboard-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add click feedback
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});
    </script>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>
