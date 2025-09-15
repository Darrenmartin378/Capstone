<?php
require_once 'includes/student_init.php';

$pageTitle = 'My Tests';

// Handle autosave endpoint
if (isset($_POST['action']) && $_POST['action'] === 'autosave') {
    header('Content-Type: application/json');
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer = $_POST['answer'] ?? '';

    if ($assessmentId <= 0 || $questionId <= 0 || $studentId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        exit();
    }

    // Upsert response
    $check = $conn->prepare('SELECT id FROM assessment_responses WHERE assessment_id = ? AND question_id = ? AND student_id = ?');
    $check->bind_param('iii', $assessmentId, $questionId, $studentId);
    $check->execute();
    $rs = $check->get_result();
    if ($rs && $row = $rs->fetch_assoc()) {
        $rid = (int)$row['id'];
        $upd = $conn->prepare('UPDATE assessment_responses SET answer = ?, submitted_at = NOW() WHERE id = ?');
        $upd->bind_param('si', $answer, $rid);
        $ok = $upd->execute();
        echo json_encode(['ok' => $ok, 'mode' => 'update']);
        exit();
    } else {
        $ins = $conn->prepare('INSERT INTO assessment_responses (assessment_id, question_id, student_id, answer) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiis', $assessmentId, $questionId, $studentId, $answer);
        $ok = $ins->execute();
        echo json_encode(['ok' => $ok, 'mode' => 'insert']);
        exit();
    }
}

// Assigned assessments (by student or section)
$sqlAssigned = "SELECT DISTINCT a.*
                FROM assessments a
                JOIN assessment_assignments aa ON aa.assessment_id = a.id
                WHERE (aa.student_id = $studentId) OR ($studentSectionId > 0 AND aa.section_id = $studentSectionId)
                ORDER BY a.created_at DESC";
$assigned = $conn->query($sqlAssigned);

$takeAssessmentId = isset($_GET['take_assessment']) ? (int)$_GET['take_assessment'] : 0;
$assessment = null; $questions = null; $guide = null;
if ($takeAssessmentId > 0) {
    $ass = $conn->query("SELECT * FROM assessments WHERE id = $takeAssessmentId");
    $assessment = $ass ? $ass->fetch_assoc() : null;
    if ($assessment) {
        $questions = $conn->query("SELECT * FROM assessment_questions WHERE assessment_id = $takeAssessmentId ORDER BY id ASC");
        if (!empty($assessment['related_material_id'])) {
            $mid = (int)$assessment['related_material_id'];
            $guideRes = $conn->query("SELECT * FROM reading_materials WHERE id = $mid");
            $guide = $guideRes ? $guideRes->fetch_assoc() : null;
        }
    }
}

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
    .btn {
        border: none;
        padding: 10px 14px;
        border-radius: 12px;
        cursor: pointer;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(30,144,255,.18);
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background: linear-gradient(180deg, var(--primary), #5fb4ff);
    }
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(30,144,255,.25);
    }
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    th, td {
        padding: 12px;
        border-bottom: 1px dashed #d4ecff;
        text-align: left;
    }
    thead th {
        background: #f0f8ff;
    }
    .notice {
        background: #fff7db;
        border: 2px dashed #ffe6a8;
        color: #6a4a00;
        padding: 10px 12px;
        border-radius: 12px;
        margin: 10px 0;
    }
    .muted {
        color: var(--muted);
        font-size: 13px;
    }
    input[type=text], textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #d7ecff;
        border-radius: 14px;
        background: #fff;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
    }
    textarea {
        min-height: 120px;
    }
    label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
</style>

<div class="card">
    <div class="card-header">
        <strong>Assigned Tests</strong> 
        <span aria-hidden="true">📝🌟</span>
    </div>
    <div class="card-body">
        <?php if ($assigned && $assigned->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = $assigned->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($a['title']); ?></td>
                            <td><?php echo h(date('M j, Y', strtotime($a['created_at']))); ?></td>
                            <td>
                                <a class="btn btn-primary" href="student_tests.php?take_assessment=<?php echo (int)$a['id']; ?>#runner">
                                    Take / Continue
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <p class="muted">No tests assigned yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($assessment): ?>
<a id="runner"></a>
<div class="card">
    <div class="card-header">
        <strong><?php echo h($assessment['title']); ?></strong>
        <span class="muted">&nbsp;&nbsp;Auto-saves as you answer. 💾</span>
    </div>
    <div class="card-body">
        <?php if ($guide): ?>
            <div class="notice">
                Guided Instructions (from related material): <strong><?php echo h($guide['title']); ?></strong> 📖
            </div>
            <div style="max-height:160px; overflow:auto; background:#ffffff; border:2px dashed #d7ecff; padding:12px; border-radius:12px; margin-bottom:12px;">
                <?php echo nl2br(h($guide['content'])); ?>
            </div>
        <?php endif; ?>

        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <div class="notice">Encouragements & Reminders are shown in Notifications tab. 🎉</div>
        <?php endif; ?>

        <?php if ($questions && $questions->num_rows > 0): $qnum = 0; while ($q = $questions->fetch_assoc()): $qnum++; ?>
            <div class="card" style="margin:10px 0; border-color:#d7ecff;">
                <div class="card-header">
                    <strong>Q<?php echo $qnum; ?>.</strong> <?php echo h($q['question_text']); ?>
                </div>
                <div class="card-body">
                    <?php if ($q['question_type'] === 'multiple_choice'): ?>
                        <?php $opts = $q['options'] ? json_decode($q['options'], true) : []; ?>
                        <?php foreach (['A','B','C','D'] as $letter): $label = $opts[$letter] ?? ''; ?>
                            <label>
                                <input type="radio" name="q_<?php echo (int)$q['id']; ?>" value="<?php echo $letter; ?>" onchange="autoSave(<?php echo (int)$assessment['id']; ?>, <?php echo (int)$q['id']; ?>, this.value)">
                                <span><strong><?php echo $letter; ?>.</strong> <?php echo h($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php elseif ($q['question_type'] === 'matching'): ?>
                        <div class="muted" style="margin-bottom:6px;">Type your pairs (left=right, one per line). ✍️</div>
                        <textarea oninput="autoSave(<?php echo (int)$assessment['id']; ?>, <?php echo (int)$q['id']; ?>, this.value)"></textarea>
                    <?php else: ?>
                        <textarea placeholder="Write your answer..." oninput="autoSave(<?php echo (int)$assessment['id']; ?>, <?php echo (int)$q['id']; ?>, this.value)"></textarea>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <p class="muted">No questions in this assessment yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function autoSave(assessmentId, questionId, answer) {
    fetch('student_tests.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=autosave&assessment_id=${assessmentId}&question_id=${questionId}&answer=${encodeURIComponent(answer)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            console.log('Auto-saved:', data.mode);
        } else {
            console.error('Auto-save failed:', data.error);
        }
    })
    .catch(error => {
        console.error('Auto-save error:', error);
    });
}
</script>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>
