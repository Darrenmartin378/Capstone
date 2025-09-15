<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

// Analytics quick stats
$skillStats = [];
$types = ['multiple_choice' => 'Multiple Choice', 'matching' => 'Matching', 'essay' => 'Essay'];
foreach ($types as $tKey => $tLabel) {
    $q = $conn->prepare("SELECT COUNT(*) c FROM assessment_questions aq JOIN assessments a ON aq.assessment_id = a.id WHERE a.teacher_id = ? AND aq.question_type = ?");
    $q->bind_param('is', $teacherId, $tKey);
    $q->execute();
    $totalQ = ($q->get_result()->fetch_assoc()['c'] ?? 0);

    $q2 = $conn->prepare("SELECT COUNT(*) c FROM assessment_responses ar JOIN assessment_questions aq ON ar.question_id = aq.id JOIN assessments a ON aq.assessment_id = a.id WHERE a.teacher_id = ? AND aq.question_type = ?");
    $q2->bind_param('is', $teacherId, $tKey);
    $q2->execute();
    $totalR = ($q2->get_result()->fetch_assoc()['c'] ?? 0);

    $avg = null;
    if ($tKey === 'multiple_choice') {
        $q3 = $conn->prepare("SELECT COUNT(*) c FROM assessment_responses ar JOIN assessment_questions aq ON ar.question_id = aq.id JOIN assessments a ON aq.assessment_id = a.id WHERE a.teacher_id = ? AND aq.question_type = 'multiple_choice' AND ar.answer = aq.answer");
        $q3->bind_param('i', $teacherId);
        $q3->execute();
        $correct = ($q3->get_result()->fetch_assoc()['c'] ?? 0);
        $avg = $totalR > 0 ? round(($correct / $totalR) * 100, 1) : null;
    }
    $skillStats[] = ['label' => $tLabel, 'total_q' => $totalQ, 'total_r' => $totalR, 'avg' => $avg];
}

render_teacher_header('analytics', $teacherName, 'Analytics');
?>
	<style>
body { background: #f6f8fc; }
.container { max-width: 900px; margin: 32px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 32px; }
.card-header { background: #eef2ff; padding: 18px 24px; border-radius: 12px 12px 0 0; font-size: 1.15rem; font-weight: 600; color: #3730a3; border-bottom: 1px solid #e0e7ff; }
.card-body { padding: 24px; }
.btn-primary { background: #6366f1; color: #fff; border: none; border-radius: 6px; padding: 7px 18px; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #4338ca; }
label { font-weight: 500; color: #3730a3; margin-bottom: 2px; display: block; }
select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 1rem; margin-top: 4px; margin-bottom: 12px; width: 100%; box-sizing: border-box; background: #f9fafb; }
.grid { display: grid; gap: 18px; }
.grid-3 { grid-template-columns: repeat(3, 1fr); }
table { width: 100%; border-collapse: collapse; margin-top: 18px; background: #fff; border-radius: 8px; overflow: hidden; }
th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
th { background: #eef2ff; color: #3730a3; font-weight: 600; }
tr:last-child td { border-bottom: none; }
@media (max-width: 700px) { .container { max-width: 100%; } .card-body { padding: 12px; } .grid-3 { grid-template-columns: 1fr; } th, td { padding: 7px 6px; } }
	</style>
	<div class="container">
		<div class="card">
			<div class="card-header"><strong>Analyzing Skill Trends</strong></div>
			<div class="card-body">
				<table>
					<thead><tr><th>Type</th><th>Total Questions</th><th>Total Responses</th><th>Avg Correct (MC)</th></tr></thead>
					<tbody>
						<?php foreach ($skillStats as $s): ?>
							<tr>
								<td><?php echo h($s['label']); ?></td>
								<td><?php echo (int)$s['total_q']; ?></td>
								<td><?php echo (int)$s['total_r']; ?></td>
								<td><?php echo $s['avg'] !== null ? h($s['avg']) . '%' : 'N/A'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="card">
			<div class="card-header"><strong>Reporting Individual Progress</strong></div>
			<div class="card-body">
				<form class="grid grid-3" method="GET">
					<div>
						<label>Student</label>
						<select name="report_student_id">
							<option value="">Select...</option>
							<?php $rs = $conn->query("SELECT * FROM students ORDER BY name"); if ($rs): while ($st = $rs->fetch_assoc()): ?>
								<option value="<?php echo (int)$st['id']; ?>" <?php echo (isset($_GET['report_student_id']) && (int)$_GET['report_student_id']===(int)$st['id']) ? 'selected' : ''; ?>><?php echo h($st['name']); ?></option>
							<?php endwhile; endif; ?>
						</select>
					</div>
					<div style="align-self:end;">
						<button class="btn btn-primary" type="submit">Generate</button>
					</div>
				</form>
				<?php if (isset($_GET['report_student_id']) && (int)$_GET['report_student_id']>0): ?>
					<?php
					$sid = (int)$_GET['report_student_id'];
					$sql = "SELECT a.title,
							   SUM(CASE WHEN aq.question_type='multiple_choice' AND ar.answer = aq.answer THEN 1 ELSE 0 END) as correct_mc,
							   SUM(CASE WHEN aq.question_type='multiple_choice' THEN 1 ELSE 0 END) as total_mc
						FROM assessment_responses ar
						JOIN assessment_questions aq ON ar.question_id = aq.id
						JOIN assessments a ON ar.assessment_id = a.id
						WHERE a.teacher_id = $teacherId AND ar.student_id = $sid
						GROUP BY a.id ORDER BY a.created_at DESC";
					$rep = $conn->query($sql);
					?>
					<table style="margin-top:12px;">
						<thead><tr><th>Assessment</th><th>MC Score</th></tr></thead>
						<tbody>
							<?php if ($rep && $rep->num_rows>0): while ($row = $rep->fetch_assoc()): ?>
								<tr>
									<td><?php echo h($row['title']); ?></td>
									<td><?php echo (int)$row['correct_mc'] . ' / ' . (int)$row['total_mc']; ?></td>
								</tr>
							<?php endwhile; else: ?>
								<tr><td colspan="2">No data.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php render_teacher_footer(); ?>


