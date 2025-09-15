<?php
require_once 'includes/student_init.php';

$pageTitle = 'Notifications';

// Get student's section ID
$studentSectionId = $_SESSION['student_section_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;

// Fetch student notifications (both specific to student and for their section)
$notifications = null;
try {
    $notifications = $conn->query("
        SELECT 
            sn.*,
            t.name as teacher_name
        FROM student_notifications sn
        JOIN teachers t ON sn.teacher_id = t.id
        WHERE (sn.student_id = $studentId OR (sn.student_id IS NULL AND sn.section_id = $studentSectionId))
        ORDER BY sn.created_at DESC
        LIMIT 20
    ");
} catch (Exception $e) {
    // Table doesn't exist yet, notifications will be empty
    $notifications = false;
    error_log("Student notifications table not found: " . $e->getMessage());
}

// Fetch announcements (legacy)
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

ob_start();
?>
<style>
    .card {
        background: var(--card);
        border: 2px solid #d9f2ff;
        border-radius: 18px;
        box-shadow: 0 10px 20px rgba(43,144,217,.15);
        margin: 18px 0;
        overflow: hidden;
    }
    .card-header {
        padding: 14px 16px;
        background: linear-gradient(90deg,#e8f7ff,#f0fff6);
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        color: #17415e;
    }
    .card-body {
        padding: 16px;
    }
    .muted {
        color: var(--muted);
        font-size: 13px;
    }
    .notification-item {
        background: linear-gradient(135deg, #fff9e6, #f0fff4);
        border: 2px solid #d9f2ff;
        border-radius: 12px;
        margin: 12px 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .notification-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(32,201,151,.2);
    }
    .notification-header {
        padding: 12px 16px;
        background: linear-gradient(90deg, #e8f7ff, #f0fff6);
        border-bottom: 1px solid #d4ecff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notification-title {
        font-weight: 700;
        color: #17415e;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .notification-time {
        color: var(--muted);
        font-size: 12px;
    }
    .notification-content {
        padding: 16px;
        line-height: 1.6;
        color: #2d3748;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--muted);
    }
    .empty-state .icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }
    .empty-state h3 {
        margin: 0 0 8px 0;
        color: #4a5568;
    }
    .empty-state p {
        margin: 0;
        font-size: 14px;
    }
</style>

<!-- New Notifications Section -->
<div class="card">
    <div class="card-header">
        <strong>Recent Notifications</strong> 
        <span aria-hidden="true">🔔</span>
    </div>
    <div class="card-body">
        <?php if ($notifications && $notifications->num_rows > 0): ?>
            <?php while ($notification = $notifications->fetch_assoc()): ?>
                <div class="notification-item">
                    <div class="notification-header">
                        <div class="notification-title">
                            <?php 
                            $icon = match($notification['type']) {
                                'material' => '📚',
                                'comprehension' => '❓',
                                'assessment' => '📝',
                                'announcement' => '📢',
                                default => '🔔'
                            };
                            echo $icon;
                            ?>
                            <?php echo h($notification['title']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo h(date('M j, Y g:ia', strtotime($notification['created_at']))); ?>
                        </div>
                    </div>
                    <div class="notification-content">
                        <?php echo nl2br(h($notification['message'])); ?>
                        <div style="margin-top: 8px; font-size: 12px; color: #6b7280;">
                            From: <?php echo h($notification['teacher_name']); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🔔</div>
                <h3>No New Notifications</h3>
                <p>You're all caught up! Check back later for updates from your teachers.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Legacy Announcements Section -->
<div class="card">
    <div class="card-header">
        <strong>Encouragements & Announcements</strong> 
        <span aria-hidden="true">⭐</span>
    </div>
    <div class="card-body">
        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <?php while ($n = $announcements->fetch_assoc()): ?>
                <div class="notification-item">
                    <div class="notification-header">
                        <div class="notification-title">
                            <span>📢</span>
                            <?php echo h($n['title']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo h(date('M j, Y g:ia', strtotime($n['created_at']))); ?>
                        </div>
                    </div>
                    <div class="notification-content">
                        <?php echo nl2br(h($n['content'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📢</div>
                <h3>No Announcements Yet</h3>
                <p>Check back later for announcements and encouragements from your teachers!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional encouragement cards -->
<div class="card">
    <div class="card-header">
        <strong>Study Tips & Motivation</strong> 
        <span aria-hidden="true">💡</span>
    </div>
    <div class="card-body">
        <div class="notification-item">
            <div class="notification-header">
                <div class="notification-title">
                    <span>🌟</span>
                    Daily Study Tip
                </div>
            </div>
            <div class="notification-content">
                <strong>Take Regular Breaks!</strong><br>
                Studies show that taking a 5-10 minute break every 25-30 minutes can improve focus and retention. 
                Try the Pomodoro Technique: 25 minutes of focused study, then a 5-minute break.
            </div>
        </div>

        <div class="notification-item">
            <div class="notification-header">
                <div class="notification-title">
                    <span>🎯</span>
                    Goal Setting
                </div>
            </div>
            <div class="notification-content">
                <strong>Set SMART Goals!</strong><br>
                Make your study goals Specific, Measurable, Achievable, Relevant, and Time-bound. 
                Instead of "study more," try "complete 3 practice questions by 3 PM today."
            </div>
        </div>

        <div class="notification-item">
            <div class="notification-header">
                <div class="notification-title">
                    <span>🧠</span>
                Memory Technique
            </div>
        </div>
        <div class="notification-content">
            <strong>Use Active Recall!</strong><br>
            Instead of just re-reading notes, try to recall information from memory. 
            This strengthens neural pathways and improves long-term retention.
        </div>
    </div>
</div>
</div>

<script>
// Add some interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Add click animation to notification items
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });

    // Auto-refresh notifications every 30 seconds (optional)
    // setInterval(() => {
    //     location.reload();
    // }, 30000);
});
</script>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>
