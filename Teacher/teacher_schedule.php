<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_assessment') {
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $sectionId = $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
    $studentId = $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;
    if ($assessmentId > 0) {
        $stmt = $conn->prepare('INSERT INTO assessment_assignments (assessment_id, section_id, student_id) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $assessmentId, $sectionId, $studentId);
        $stmt->execute();
        $flash = 'Assignment created.';
    } else {
        $flash = 'Please select an assessment.';
    }
}

$assessments = $conn->query("SELECT * FROM assessments WHERE teacher_id = $teacherId ORDER BY created_at DESC");
$sections = $conn->query("SELECT s.* FROM sections s JOIN teacher_sections ts ON ts.section_id = s.id WHERE ts.teacher_id = $teacherId ORDER BY s.name");
$students = $conn->query("SELECT st.* FROM students st ORDER BY st.name");

render_teacher_header('schedule', $teacherName, 'Schedule / Assign');
?>
<style>
body {
    background: #f6f8fc;
}
.container {
    max-width: 700px;
    margin: 32px auto;
    padding: 0 16px;
}
.flash {
    background: #fde68a;
    color: #b45309;
    border-radius: 8px;
    padding: 12px 18px;
    margin-bottom: 18px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(251,191,36,0.08);
    position: relative;
}
.flash-close {
    position: absolute;
    right: 14px;
    top: 10px;
    background: none;
    border: none;
    font-size: 1.3rem;
    color: #b45309;
    cursor: pointer;
    line-height: 1;
}
.flash-close:hover {
    color: #a16207;
}
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 32px;
    padding: 0;
}
.card-header {
    background: #eef2ff;
    padding: 18px 24px;
    border-radius: 12px 12px 0 0;
    font-size: 1.15rem;
    font-weight: 600;
    color: #3730a3;
    border-bottom: 1px solid #e0e7ff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-header i {
    color: #6366f1;
}
.card-body {
    padding: 24px;
}
.btn {
    border: none;
    border-radius: 6px;
    padding: 7px 18px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-primary {
    background: #6366f1;
    color: #fff;
}
.btn-primary:hover {
    background: #4338ca;
}
label {
    font-weight: 500;
    color: #3730a3;
    margin-bottom: 2px;
    display: block;
}
select, input[type="text"] {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 1rem;
    margin-top: 4px;
    margin-bottom: 12px;
    width: 100%;
    box-sizing: border-box;
    background: #f9fafb;
}
.grid {
    display: grid;
    gap: 18px;
}
.grid-3 {
    grid-template-columns: repeat(3, 1fr);
}
@media (max-width: 700px) {
    .container { max-width: 100%; }
    .card-body { padding: 12px; }
    .grid-3 { grid-template-columns: 1fr; }
}
</style>
<div class="container">
    <?php if (!empty($flash)): ?>
        <div class="flash" id="flash-message">
            <?php echo h($flash); ?>
            <button type="button" class="flash-close" onclick="document.getElementById('flash-message').style.display='none';">&times;</button>
        </div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-calendar-alt"></i>
            <strong>Set Assessment Schedule / Assign</strong>
        </div>
        <div class="card-body">
            <form method="POST" class="grid grid-3">
                <input type="hidden" name="action" value="assign_assessment">
                <div>
                    <label>Assessment</label>
                    <select name="assessment_id" required>
                        <option value="">Select...</option>
                        <?php if ($assessments): while ($a = $assessments->fetch_assoc()): ?>
                            <option value="<?php echo (int)$a['id']; ?>"><?php echo h($a['title']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label>Section <span style="color:#6b7280;font-size:0.95em;">(optional)</span></label>
                    <select name="section_id">
                        <option value="">None</option>
                        <?php if ($sections): while ($s = $sections->fetch_assoc()): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo h($s['name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label>Student <span style="color:#6b7280;font-size:0.95em;">(optional)</span></label>
                    <select name="student_id">
                        <option value="">None</option>
                        <?php if ($students): while ($st = $students->fetch_assoc()): ?>
                            <option value="<?php echo (int)$st['id']; ?>"><?php echo h($st['name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1; text-align:right;">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<?php render_teacher_footer(); ?>


