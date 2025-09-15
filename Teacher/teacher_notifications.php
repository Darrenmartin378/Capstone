<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/notification_helper.php';

$flash = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_announcement') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '') {
        // Redirect with error
        header('Location: teacher_notifications.php?error=1');
        exit;
    } else {
        $stmt = $conn->prepare('INSERT INTO announcements (teacher_id, title, content) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $teacherId, $title, $content);
        $stmt->execute();
        $announcementId = $conn->insert_id;
        
        // Create notification for all students in teacher's sections
        createNotificationForAllStudents(
            $conn, 
            $teacherId, 
            'announcement', 
            'New Announcement', 
            "Your teacher has posted a new announcement: \"$title\". Check the Notifications section to read it.",
            $announcementId
        );
        
        // Redirect to prevent form resubmission
        header('Location: teacher_notifications.php?published=1');
        exit;
    }
}

// Clean up orphaned quiz scores for deleted quiz sets
$conn->query("
    DELETE qs FROM quiz_scores qs
    WHERE qs.teacher_id = $teacherId
    AND qs.set_title NOT IN (
        SELECT DISTINCT set_title 
        FROM question_bank 
        WHERE teacher_id = $teacherId 
        AND set_title IS NOT NULL 
        AND set_title != ''
        AND question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder')
        AND question_text != ''
        AND question_text IS NOT NULL
    )
");

// Get completed quiz scores for this teacher (comprehension questions) - only for existing quiz sets
$completedQuizzes = $conn->query("
    SELECT 
        qs.*,
        s.name as student_name,
        s.student_number,
        sec.name as section_name
    FROM quiz_scores qs
    JOIN students s ON qs.student_id = s.id
    JOIN sections sec ON qs.section_id = sec.id
    WHERE qs.teacher_id = $teacherId
    AND qs.set_title IN (
        SELECT DISTINCT set_title 
        FROM question_bank 
        WHERE teacher_id = $teacherId 
        AND set_title IS NOT NULL 
        AND set_title != ''
        AND question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder')
        AND question_text != ''
        AND question_text IS NOT NULL
    )
    ORDER BY qs.submitted_at DESC
    LIMIT 50
");

// Get completed assessments for this teacher
$completedAssessments = $conn->query("
    SELECT 
        aa.*,
        a.title as assessment_title,
        a.description as assessment_description,
        s.name as student_name,
        s.student_number,
        sec.name as section_name,
        pt.score as assessment_score,
        pt.total_questions,
        pt.correct_answers,
        pt.completed_at
    FROM assessment_assignments aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN students s ON aa.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN practice_test_attempts pt ON aa.assessment_id = pt.practice_test_id AND aa.student_id = pt.student_id
    WHERE a.teacher_id = $teacherId AND pt.status = 'completed'
    ORDER BY pt.completed_at DESC
    LIMIT 50
");

// Get teacher's announcements
$announcements = $conn->query("
    SELECT * FROM announcements 
    WHERE teacher_id = $teacherId 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Render header after all POST handling is complete
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('notifications', $teacherName, 'Notifications');
?>
<style>
body { background: #f6f8fc; }
.container { max-width: 1200px; margin: 32px auto; padding: 0 16px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
.card-header { background: #eef2ff; padding: 18px 24px; font-size: 1.15rem; font-weight: 600; color: #3730a3; border-bottom: 1px solid #e0e7ff; display: flex; align-items: center; gap: 8px; }
.card-body { padding: 24px; }
.btn { border: none; border-radius: 6px; padding: 8px 16px; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-primary:hover { background: #4338ca; transform: translateY(-1px); }
.btn-secondary { background: #e0e7ff; color: #3730a3; }
.btn-secondary:hover { background: #c7d2fe; }
.btn-success { background: #10b981; color: #fff; }
.btn-warning { background: #f59e0b; color: #fff; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-sm { padding: 6px 12px; font-size: 0.8rem; }
label { font-weight: 500; color: #3730a3; margin-bottom: 6px; display: block; }
input[type="text"], textarea { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; font-size: 1rem; margin-bottom: 16px; width: 100%; box-sizing: border-box; background: #f9fafb; transition: border-color 0.2s; }
input[type="text"]:focus, textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
textarea { min-height: 120px; resize: vertical; }
.flash { border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative; }
.flash-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
.flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
.flash-close { position: absolute; right: 14px; top: 10px; background: none; border: none; font-size: 1.3rem; color: inherit; cursor: pointer; line-height: 1; opacity: 0.7; transition: opacity 0.2s; }
.flash-close:hover { opacity: 1; }
.notification-item { padding: 16px; border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s; }
.notification-item:hover { background: #f8fafc; }
.notification-item:last-child { border-bottom: none; }
.notification-header { display: flex; justify-content: between; align-items: center; margin-bottom: 8px; }
.notification-title { font-weight: 600; color: #374151; margin: 0; }
.notification-meta { font-size: 0.8rem; color: #6b7280; }
.notification-content { color: #4b5563; line-height: 1.5; }
.student-response { background: #f0f9ff; border-left: 4px solid #0ea5e9; }
.quiz-score { background: #f0fdf4; border-left: 4px solid #10b981; }
.essay-response { background: #fefce8; border-left: 4px solid #eab308; }
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
.stat-number { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
.stat-label { color: #6b7280; font-size: 0.9rem; }
@media (max-width: 768px) { 
    .container { max-width: 100%; padding: 0 12px; } 
    .grid-2, .grid-3 { grid-template-columns: 1fr; } 
    .card-body { padding: 16px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="container">
    <!-- Flash Messages -->
    <?php if (isset($_GET['published']) && $_GET['published'] === '1'): ?>
        <div class="flash flash-success" id="published-message">
            ✅ Announcement published successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('published-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
        <div class="flash flash-error" id="error-message">
            ❌ Title is required.
            <button type="button" class="flash-close" onclick="closeFlashMessage('error-message')">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $completedQuizzes ? $completedQuizzes->num_rows : 0; ?></div>
            <div class="stat-label">Quiz Completions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $completedAssessments ? $completedAssessments->num_rows : 0; ?></div>
            <div class="stat-label">Assessment Completions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $announcements ? $announcements->num_rows : 0; ?></div>
            <div class="stat-label">Your Announcements</div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Send Announcement Form -->
        <div class="card">
            <div class="card-header">
                <span>📢</span>
                <strong>Send Announcement</strong>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="send_announcement">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="Schedule assessments / Encourage students..." required>
                    
                    <label>Message</label>
                    <textarea name="content" placeholder="Announcement details or reading list/practice info..."></textarea>
                    
                    <div style="text-align: right;">
                        <button class="btn btn-primary" type="submit">
                            <span>📤</span> Publish
                        </button>
                    </div>
                </form>
                <p style="margin-top: 12px; font-size: 0.9rem; color: #6b7280;">
                    Use this to: schedule assessments, encourage students, remind tests, announce updates, provide practice, share reading lists, review performance alerts.
                </p>
            </div>
        </div>

        <!-- Recent Announcements -->
        <div class="card">
            <div class="card-header">
                <span>📋</span>
                <strong>Your Recent Announcements</strong>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if ($announcements && $announcements->num_rows > 0): ?>
                    <?php while ($announcement = $announcements->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <h4 class="notification-title"><?php echo h($announcement['title']); ?></h4>
                                <span class="notification-meta"><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                            </div>
                            <div class="notification-content">
                                <?php echo h(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="padding: 24px; text-align: center; color: #6b7280;">
                        <p>No announcements yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quiz Completions -->
    <div class="card">
        <div class="card-header">
            <span>📝</span>
            <strong>Comprehension Quiz Set Completions</strong>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if ($completedQuizzes && $completedQuizzes->num_rows > 0): ?>
                <?php while ($quiz = $completedQuizzes->fetch_assoc()): ?>
                    <div class="notification-item quiz-score">
                        <div class="notification-header">
                            <div>
                                <h4 class="notification-title">
                                    <?php echo h($quiz['student_name']); ?> 
                                    <span class="badge badge-info"><?php echo h($quiz['section_name']); ?></span>
                                    <span class="badge badge-success"><?php echo number_format($quiz['score'], 1); ?>%</span>
                                </h4>
                                <div class="notification-meta">
                                    <strong><?php echo h($quiz['set_title']); ?></strong> • 
                                    Completed on <?php echo date('M j, Y g:i A', strtotime($quiz['submitted_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="notification-content">
                            <strong>Quiz Set Completed:</strong> <?php echo h($quiz['set_title']); ?><br>
                            <strong>Final Score:</strong> <?php echo $quiz['correct_answers']; ?>/<?php echo $quiz['total_points']; ?> points 
                            (<?php echo number_format($quiz['score'], 1); ?>%)
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 24px; text-align: center; color: #6b7280;">
                    <p>No quiz completions yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assessment Completions -->
    <div class="card">
        <div class="card-header">
            <span>📊</span>
            <strong>Assessment Completions</strong>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if ($completedAssessments && $completedAssessments->num_rows > 0): ?>
                <?php while ($assessment = $completedAssessments->fetch_assoc()): ?>
                    <div class="notification-item student-response">
                        <div class="notification-header">
                            <div>
                                <h4 class="notification-title">
                                    <?php echo h($assessment['student_name']); ?> 
                                    <span class="badge badge-info"><?php echo h($assessment['section_name']); ?></span>
                                    <?php if ($assessment['assessment_score'] !== null): ?>
                                        <span class="badge badge-success"><?php echo number_format($assessment['assessment_score'], 1); ?>%</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="notification-meta">
                                    <strong><?php echo h($assessment['assessment_title']); ?></strong> • 
                                    Completed on <?php echo date('M j, Y g:i A', strtotime($assessment['completed_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="notification-content">
                            <strong>Assessment Completed:</strong> <?php echo h($assessment['assessment_title']); ?><br>
                            <?php if ($assessment['assessment_score'] !== null): ?>
                                <strong>Score:</strong> <?php echo $assessment['correct_answers']; ?>/<?php echo $assessment['total_questions']; ?> questions 
                                (<?php echo number_format($assessment['assessment_score'], 1); ?>%)
                            <?php else: ?>
                                <strong>Status:</strong> Assessment completed (score pending)
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 24px; text-align: center; color: #6b7280;">
                    <p>No assessment completions yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Flash message functions
function closeFlashMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateX(100%)';
        setTimeout(() => {
            message.remove();
            // Clean up URL parameters
            const url = new URL(window.location);
            url.searchParams.delete('published');
            url.searchParams.delete('error');
            window.history.replaceState({}, '', url);
        }, 300);
    }
}

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(message => {
        setTimeout(() => {
            if (message && message.parentNode) {
                closeFlashMessage(message.id);
            }
        }, 5000);
    });
});
</script>

<?php render_teacher_footer(); ?>


