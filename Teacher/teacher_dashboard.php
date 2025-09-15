<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('teacher_dashboard.php', $_SESSION['teacher_name'] ?? 'Teacher');

// Get teacher ID from session
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Materials
$result = $conn->query("SELECT COUNT(*) AS total FROM reading_materials WHERE teacher_id = $teacherId");
$row = $result->fetch_assoc();
$materialsCount = (int)$row['total'];

// Questions (count distinct set titles)
$qResult = $conn->query("SELECT COUNT(DISTINCT set_title) AS total FROM question_bank WHERE teacher_id = $teacherId");
$qRow = $qResult->fetch_assoc();
$questionsCount = (int)$qRow['total'];

// Assessments
$aResult = $conn->query("SELECT COUNT(*) AS total FROM assessments WHERE teacher_id = $teacherId");
$aRow = $aResult->fetch_assoc();
$assessmentsCount = (int)$aRow['total'];

// Assignments (count assigned assessments)
$asResult = $conn->query("SELECT COUNT(*) AS total FROM assessment_assignments WHERE assessment_id IN (SELECT id FROM assessments WHERE teacher_id = $teacherId)");
$asRow = $asResult->fetch_assoc();
$assignmentsCount = (int)$asRow['total'];

// Practice Sets (Warm-ups)
$ptResult = $conn->query("SELECT COUNT(*) AS total FROM warm_ups WHERE teacher_id = $teacherId");
$ptRow = $ptResult->fetch_assoc();
$practiceSetsCount = (int)$ptRow['total'];
?>

<style>
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}
.card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(79,70,229,0.08);
    padding: 32px 24px;
    flex: 1;
    min-width: 180px;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
}
.card:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 8px 32px rgba(79,70,229,0.12);
}
.card .icon {
    font-size: 2.5rem;
    color: #6366f1;
    margin-bottom: 12px;
}
.card .count {
    font-size: 2.2rem;
    font-weight: 700;
    color: #4f46e5;
}
.card .btn {
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 22px;
    font-weight: 600;
    margin-top: 14px;
    transition: background 0.2s;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.card .btn:hover {
    background: #4338ca;
}
.card-btn {
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 28px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 12px;
    transition: background 0.2s;
    box-shadow: 0 2px 8px rgba(99,102,241,0.08);
    outline: none;
    display: inline-block;
}
.card-btn:hover {
    background: #4338ca;
}
.quick-links {
    margin-top: 24px;
    text-align: center;
}
.quick-links .btn {
    background: #fde68a;
    color: #b45309;
    margin-right: 10px;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
    display: inline-block;
}
.quick-links .btn:hover {
    background: #fbbf24;
    color: #fff;
}
@media (max-width: 900px) {
    .dashboard-cards {
        flex-direction: column;
        gap: 18px;
    }
}
</style>

<div class="dashboard-cards">
    <div class="card">
        <div class="icon"><i class="fas fa-book"></i></div>
        <div>Materials</div>
        <div class="count"><?php echo $materialsCount; ?></div>
        <button class="card-btn" onclick="window.location.href='teacher_content.php'">Manage</button>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-question-circle"></i></div>
        <div>Questions</div>
        <div class="count"><?php echo $questionsCount; ?></div>
        <a href="teacher_questions.php" class="btn">Open</a>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-tasks"></i></div>
        <div>Assessments</div>
        <div class="count"><?php echo $assessmentsCount; ?></div>
        <a href="teacher_assessments.php" class="btn">Build</a>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-calendar-alt"></i></div>
        <div>Assignments</div>
        <div class="count"><?php echo $assignmentsCount; ?></div>
        <a href="teacher_schedule.php" class="btn">Assign</a>
    </div>
    <div class="card">
        <div class="icon"><i class="fas fa-fire"></i></div>
        <div>Practice Sets</div>
        <div class="count"><?php echo $practiceSetsCount; ?></div>
        <a href="teacher_practice_tests.php" class="btn">Create</a>
    </div>
</div>
<div class="quick-links">
    <a href="teacher_grading.php" class="btn">Grading & Responses</a>
    <a href="teacher_notifications.php" class="btn">Announcements</a>
    <a href="teacher_analytics.php" class="btn">Analytics</a>
    <a href="teacher_account.php" class="btn">Account</a>
</div>

<?php
render_teacher_footer();
?>


