<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';
// Response/score utilities live under the Student includes
require_once __DIR__ . '/../Student/Includes/NewResponseHandler.php';

$responseHandler = new NewResponseHandler($conn);

// Filters
$filterSectionId = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$filterGenderRaw = strtolower(trim($_GET['gender'] ?? ''));
$filterGender = in_array($filterGenderRaw, ['male','female']) ? $filterGenderRaw : '';

// Determine if students.gender exists
$hasGenderCol = false;
try { $chk = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'"); $hasGenderCol = ($chk && $chk->num_rows > 0); } catch (Exception $e) {}

// Build list of section ids the teacher handles
$teacherSectionIds = array_map(function($s){ return (int)$s['id']; }, $teacherSections ?? []);
if (empty($teacherSectionIds)) { $teacherSectionIds = [0]; }

// Fetch students for the selected filters
$students = [];
if ($filterSectionId > 0) {
    $sql = "SELECT * FROM students WHERE section_id = ?" . ($hasGenderCol && $filterGender ? " AND LOWER(gender) = ?" : "");
    $stmt = $conn->prepare($sql);
    if ($hasGenderCol && $filterGender) { $stmt->bind_param('is', $filterSectionId, $filterGender); } else { $stmt->bind_param('i', $filterSectionId); }
} else {
    // All sections the teacher handles
    $placeholders = implode(',', array_fill(0, count($teacherSectionIds), '?'));
    $sql = "SELECT * FROM students WHERE section_id IN ($placeholders)" . ($hasGenderCol && $filterGender ? " AND LOWER(gender) = ?" : "");
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($teacherSectionIds)) . (($hasGenderCol && $filterGender) ? 's' : '');
    $params = $teacherSectionIds; if ($hasGenderCol && $filterGender) { $params[] = $filterGender; }
    $stmt->bind_param($types, ...$params);
}
if ($stmt && $stmt->execute()) { $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }

// Helper: get sets for a section created by this teacher
function getSetsForSection(mysqli $conn, int $teacherId, int $sectionId): array {
    $sets = [];
    $st = $conn->prepare('SELECT id, set_title, created_at FROM question_sets WHERE teacher_id = ? AND section_id = ? ORDER BY created_at DESC');
    if ($st) { $st->bind_param('ii', $teacherId, $sectionId); $st->execute(); $sets = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    return $sets;
}

// Compute per-student performance
$rows = [];
$chartMap = [];
$globalCompleted = 0; $globalPctSum = 0.0;
foreach ($students as $stu) {
    $sid = (int)($stu['id'] ?? 0);
    $secId = (int)($stu['section_id'] ?? 0);
    $sets = getSetsForSection($conn, $teacherId, $secId);
    $completed = 0; $scoreSum = 0.0; $maxSum = 0.0;
    $perLabels = []; $perData = [];
    foreach ($sets as $set) {
        $setId = (int)$set['id'];
        try {
            $totalScore = $responseHandler->calculateTotalScore($sid, $setId);
            if (is_array($totalScore) && ($totalScore['total_questions'] ?? 0) > 0) {
                $completed++;
                $scoreSum += (float)($totalScore['total_score'] ?? 0);
                $maxPts = (float)$responseHandler->getMaxPointsForSet($setId);
                $maxSum += $maxPts;
                $pctSet = $maxPts > 0 ? round((($totalScore['total_score'] ?? 0) / $maxPts) * 100, 1) : 0;
                $perLabels[] = $set['set_title'];
                $perData[] = $pctSet;
            }
        } catch (Exception $e) {}
    }
    $avgPct = ($maxSum > 0) ? round(($scoreSum / $maxSum) * 100, 1) : 0.0;
    if ($completed > 0) { $globalCompleted++; $globalPctSum += $avgPct; }
    $rows[] = [
        'student_id' => $sid,
        'name' => $stu['name'] ?? ($stu['username'] ?? 'Student'),
        'gender' => $hasGenderCol ? (strtolower($stu['gender'] ?? '') ?: '') : '',
        'section_id' => $secId,
        'completed' => $completed,
        'avg_pct' => $avgPct,
    ];
    $chartMap[$sid] = [ 'name' => ($stu['name'] ?? ($stu['username'] ?? 'Student')), 'labels' => $perLabels, 'data' => $perData ];
}

// Map section id -> name for fast lookup
$sectionMap = [];
foreach (($teacherSections ?? []) as $s) { $sectionMap[(int)$s['id']] = $s['section_name'] ?: $s['name']; }

// Summary metrics
$overallAvg = ($globalCompleted > 0) ? round($globalPctSum / $globalCompleted, 1) : 0.0;

render_teacher_header('teacher_analytics.php', $teacherName, 'Student Performance');
?>
<style>
body { background:#f5f7fa; }
.container { max-width: 1100px; margin: 22px auto; padding: 0 16px; }
.filters { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,.05); margin-bottom:16px; }
.grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
@media (max-width: 900px){ .grid{ grid-template-columns:1fr; } }
label { font-weight:600; color:#374151; font-size:14px; }
select { width:100%; padding:9px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
.summary { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin: 14px 0; }
.stat { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.stat .num { font-size:28px; font-weight:800; color:#111827; }
.charts { display:grid; grid-template-columns: 1fr; gap:12px; margin:14px 0; }
.chart-card{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.chart-card h3{ margin:0 0 8px 0; color:#111827; font-size:16px; font-weight:700; }
.chart-wrap{ position:relative; height:260px; }
.table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.table thead { background:#eef2ff; color:#111827; }
.table th, .table td { padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
.bar { height:12px; background:#f1f5f9; border-radius:9999px; overflow:hidden; }
.bar > span{ display:block; height:100%; background:linear-gradient(90deg,#6366f1,#22c55e); width:0; }
.badge { display:inline-block; padding:4px 8px; border-radius:9999px; background:#e0f2fe; color:#075985; font-size:12px; font-weight:700; }
.btn { border:none; background:#6366f1; color:#fff; padding:9px 14px; border-radius:8px; cursor:pointer; font-weight:700; }
</style>
<div class="container">
    <form class="filters" method="get">
        <div class="grid">
            <div>
                <label>Section</label>
                <select name="section">
                    <option value="0">All Sections</option>
                    <?php foreach (($teacherSections ?? []) as $s): $sid=(int)$s['id']; $name=$s['section_name']?:$s['name']; ?>
                        <option value="<?php echo $sid; ?>" <?php echo $filterSectionId===$sid?'selected':''; ?>><?php echo h($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Gender</label>
                <?php if ($hasGenderCol): ?>
                    <select name="gender">
                        <option value="">All</option>
                        <option value="male" <?php echo $filterGender==='male'?'selected':''; ?>>Male</option>
                        <option value="female" <?php echo $filterGender==='female'?'selected':''; ?>>Female</option>
                    </select>
                <?php else: ?>
                    <div class="badge">No gender data</div>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button class="btn" type="submit"><i class="fas fa-filter"></i>&nbsp;Apply Filters</button>
            </div>
        </div>
    </form>

    <div class="summary" id="summaryDefault">
        <div class="stat"><div class="num"><?php echo count($rows); ?></div><div>Total Students</div></div>
        <div class="stat"><div class="num"><?php echo number_format($overallAvg,1); ?>%</div><div>Overall Avg (completed)</div></div>
        <div class="stat"><div class="num"><?php echo (int)$globalCompleted; ?></div><div>Students with Attempts</div></div>
    </div>
    <div class="summary" id="summaryStudent" style="display:none;">
        <div class="stat"><div class="num" id="sAvg">0%</div><div>Average Score</div></div>
        <div class="stat"><div class="num" id="sCompleted">0</div><div>Sets Completed</div></div>
        <div class="stat"><div class="num" id="sTotal">0</div><div>Total Sets</div></div>
    </div>

    <div class="charts">
        <div class="chart-card">
            <h3 id="chartTitle">Student Performance</h3>
            <div class="chart-wrap"><canvas id="studentsBar"></canvas></div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Section</th>
                <?php if ($hasGenderCol): ?><th>Gender</th><?php endif; ?>
                <th style="width:140px;">Completed</th>
                <th style="width:220px;">Average</th>
                <th style="width:90px;">View</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): $pct = (float)$r['avg_pct']; ?>
                <tr>
                    <td><?php echo h($r['name']); ?></td>
                    <td><?php echo h($sectionMap[$r['section_id']] ?? ''); ?></td>
                    <?php if ($hasGenderCol): ?><td><?php echo h(ucfirst($r['gender'] ?: '')); ?></td><?php endif; ?>
                    <td><?php echo (int)$r['completed']; ?></td>
                    <td>
                        <div class="bar"><span style="width: <?php echo min(100,$pct); ?>%"></span></div>
                        <div style="font-size:12px;color:#374151;margin-top:4px; font-weight:700;">&nbsp;<?php echo number_format($pct,1); ?>%</div>
                    </td>
                    <td><button class="btn" style="padding:6px 10px;" onclick="showStudent(<?php echo (int)$r['student_id']; ?>, <?php echo (int)$r['completed']; ?>, <?php echo count(getSetsForSection($conn,$teacherId,(int)$r['section_id'])); ?>, <?php echo json_encode(number_format($pct,1)); ?>)">View</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<?php render_teacher_footer(); ?>

<script>
// Build chart dataset per student from PHP
const studentCharts = <?php echo json_encode($chartMap); ?>;
let chart;
function renderChart(labels, data, title){
  const ctx = document.getElementById('studentsBar');
  if (!ctx) return;
  if (chart) chart.destroy();
  chart = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Score %', data, borderRadius:6, backgroundColor: data.map(v => v>=75?'#22c55e':(v>=50?'#f59e0b':'#ef4444')) }] },
    options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, max:100, ticks:{ stepSize:10 } } }, plugins:{ legend:{ display:false } } }
  });
  const titleEl = document.getElementById('chartTitle'); if (titleEl) titleEl.textContent = title || 'Student Performance';
}
function showStudent(studentId, completed=0, totalSets=0, avgStr='0'){
  const d = studentCharts[String(studentId)] || studentCharts[studentId];
  if (!d) return;
  renderChart(d.labels||[], d.data||[], 'Performance — '+(d.name||'Student'));
  // Swap summary cards
  const def = document.getElementById('summaryDefault');
  const stu = document.getElementById('summaryStudent');
  if (def && stu){ def.style.display='none'; stu.style.display='grid'; }
  // Fill numbers
  const sAvg = document.getElementById('sAvg');
  const sComp = document.getElementById('sCompleted');
  const sTotal = document.getElementById('sTotal');
  if (sAvg) sAvg.textContent = (avgStr || '0') + '%';
  if (sComp) sComp.textContent = String(completed||0);
  if (sTotal) sTotal.textContent = String(totalSets||0);
}
// Auto-select first with data or first row
(function(){
  const keys = Object.keys(studentCharts||{});
  let chosen = null;
  for (const k of keys){ const d = studentCharts[k]; if (d && (d.data||[]).length>0){ chosen = k; break; } }
  if (!chosen && keys.length) chosen = keys[0];
  if (chosen) showStudent(chosen);
})();
</script>


