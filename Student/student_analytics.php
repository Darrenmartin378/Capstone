<?php
require_once 'includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

$responseHandler = new NewResponseHandler($conn);

// Get student and section
$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? 0);

// Fetch sets for this section
$sets = [];
if ($sectionId > 0) {
    $stmt = $conn->prepare("SELECT id, set_title, created_at FROM question_sets WHERE section_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $sets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Build analytics
$rows = [];
$totalScore = 0; $totalMax = 0; $completed = 0;
foreach ($sets as $set) {
    $setId = (int)$set['id'];
    $scoreInfo = $responseHandler->calculateTotalScore($studentId, $setId);
    $maxPts = $responseHandler->getMaxPointsForSet($setId);
    $has = is_array($scoreInfo) && ($scoreInfo['total_questions'] ?? 0) > 0;
    $score = $has ? (float)$scoreInfo['total_score'] : 0;
    $pct = ($maxPts > 0) ? round(($score / $maxPts) * 100, 1) : 0;
    $rows[] = [
        'title' => $set['set_title'],
        'date' => $set['created_at'],
        'score' => $score,
        'max' => (float)$maxPts,
        'pct' => $pct,
        'completed' => $has
    ];
    if ($has) { $totalScore += $score; $totalMax += $maxPts; $completed++; }
}
$avgPct = ($totalMax > 0) ? round(($totalScore / $totalMax) * 100, 1) : 0;
// Performance label based on average percent
if ($avgPct >= 90) { $perfLabel = 'Excellent'; $perfTone = 'ok'; }
elseif ($avgPct >= 75) { $perfLabel = 'Very Good'; $perfTone = 'good'; }
elseif ($avgPct >= 50) { $perfLabel = 'Good'; $perfTone = 'mid'; }
else { $perfLabel = 'Needs Improvement'; $perfTone = 'warn'; }

// Include layout
// Page title for layout
$pageTitle = 'Performance Analytics';

// Prepare chart data
$chartLabels = array_map(function($r){ return $r['title']; }, $rows);
$chartScores = array_map(function($r){ return (float)$r['pct']; }, $rows);

ob_start();
?>

<style>
.analytics-shell { background: rgba(15, 23, 42, 0.85); padding:20px; border-radius:16px; box-shadow:0 0 40px rgba(139, 92, 246, 0.3); border:1px solid rgba(139, 92, 246, 0.3); backdrop-filter: blur(12px); }
.stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:16px; }
.stat { background: rgba(30, 41, 59, 0.8); border:1px solid rgba(139, 92, 246, 0.3); border-radius:16px; padding:16px; text-align:center; box-shadow:0 0 20px rgba(139, 92, 246, 0.2); backdrop-filter: blur(10px); }
.stat:nth-child(1){ background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1)); }
.stat:nth-child(2){ background: linear-gradient(135deg, rgba(34, 211, 238, 0.15), rgba(139, 92, 246, 0.1)); }
.stat:nth-child(3){ background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(168, 85, 247, 0.1)); }
.table { width:100%; border-collapse:collapse; }
.table thead th{ background: linear-gradient(90deg, rgba(139, 92, 246, 0.2), rgba(34, 211, 238, 0.15)); color: #f1f5f9; }
.table th, .table td { padding:12px 10px; border-bottom:1px solid rgba(139, 92, 246, 0.2); text-align:left; color: #e1e5f2; }
.badge-ok{ background:#dcfce7; color:#065f46; padding:4px 10px; border-radius:9999px; font-size:12px; font-weight:700; border:1px solid #86efac; }
.bar { height:12px; background:#eef2f7; border-radius:9999px; overflow:hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,.06); }
.bar > span{ display:block; height:100%; width:0; border-radius:9999px; }
.chart-card{ background: rgba(30, 41, 59, 0.8); border:1px solid rgba(139, 92, 246, 0.3); border-radius:16px; padding:16px; margin-bottom:16px; box-shadow:0 0 25px rgba(139, 92, 246, 0.2); backdrop-filter: blur(10px); }
.perf-banner{ display:flex; align-items:center; justify-content:space-between; border:1px solid rgba(139, 92, 246, 0.3); border-radius:16px; padding:14px 16px; margin-bottom:16px; backdrop-filter: blur(10px); }
.perf-banner.ok{ background: linear-gradient(90deg, rgba(139, 92, 246, 0.15), rgba(34, 197, 94, 0.1)); }
.perf-banner.good{ background: linear-gradient(90deg, rgba(34, 211, 238, 0.15), rgba(59, 130, 246, 0.1)); }
.perf-banner.mid{ background: linear-gradient(90deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.1)); }
.perf-banner.warn{ background: linear-gradient(90deg, rgba(239, 68, 68, 0.15), rgba(244, 63, 94, 0.1)); }
.perf-chip{ padding:8px 12px; border-radius:9999px; font-weight:800; font-size:12px; }
.perf-chip.ok{ background:#dcfce7; color:#065f46; border:1px solid #86efac; }
.perf-chip.good{ background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
.perf-chip.mid{ background:#fef9c3; color:#92400e; border:1px solid #fde68a; }
.perf-chip.warn{ background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
</style>

<div class="page-shell">
    <div class="content-header">
        <h1><i class="fas fa-chart-line"></i> Performance Analytics</h1>
        <p>Overview of your scores in question sets.</p>
    </div>
    <div class="analytics-shell">
        <div class="perf-banner <?php echo $perfTone; ?>">
            <div>
                <div style="font-weight:800;color:#f1f5f9;text-shadow: 0 0 10px rgba(139, 92, 246, 0.3);">Overall Performance</div>
                <small style="color:rgba(241, 245, 249, 0.7);">Based on average score across completed sets</small>
            </div>
            <div>
                <span class="perf-chip <?php echo $perfTone; ?>"><?php echo h($perfLabel); ?> — <?php echo number_format($avgPct,1); ?>%</span>
            </div>
        </div>
        <div class="chart-card">
            <canvas id="scoreChart" height="300"></canvas>
        </div>
        <div class="stat-grid">
            <div class="stat">
                <div style="font-size:28px; font-weight:800; color:#f1f5f9; text-shadow: 0 0 15px rgba(139, 92, 246, 0.5);"><?php echo number_format($avgPct,1); ?>%</div>
                <div style="color:rgba(241, 245, 249, 0.7);">Average Score</div>
            </div>
            <div class="stat">
                <div style="font-size:28px; font-weight:800; color:#f1f5f9; text-shadow: 0 0 15px rgba(139, 92, 246, 0.5);"><?php echo (int)$completed; ?></div>
                <div style="color:rgba(241, 245, 249, 0.7);">Sets Completed</div>
            </div>
            <div class="stat">
                <div style="font-size:28px; font-weight:800; color:#f1f5f9; text-shadow: 0 0 15px rgba(139, 92, 246, 0.5);"><?php echo (int)count($rows); ?></div>
                <div style="color:rgba(241, 245, 249, 0.7);">Total Sets</div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Question Set</th>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['title']); ?></td>
                    <td><?php echo date('M j, Y g:ia', strtotime($r['date'])); ?></td>
                    <td><?php echo $r['score'].' / '.$r['max']; ?> (<?php echo $r['pct']; ?>%)</td>
                    <td style="width:220px;">
                        <div class="bar"><span style="width: <?php echo min(100,$r['pct']); ?>%"></span></div>
                    </td>
                    <td><?php echo $r['completed'] ? '<span class="badge-ok">Completed</span>' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    (function(){
        const ctx = document.getElementById('scoreChart');
        if (!ctx) return;
        const labels = <?php echo json_encode($chartLabels); ?>;
        const data = <?php echo json_encode($chartScores); ?>;
        const gradient = ctx.getContext('2d').createLinearGradient(0,0,0,160);
        gradient.addColorStop(0,'rgba(99,102,241,0.35)');
        gradient.addColorStop(1,'rgba(34,197,94,0.10)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Score %',
                    data,
                    fill: false,
                    borderColor: '#6366f1',
                    backgroundColor: gradient,
                    tension: .3,
                    pointRadius: 4,
                    pointBackgroundColor: '#22c55e'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 10,
                            autoSkip: false,
                            maxTicksLimit: 11,
                            precision: 0,
                            callback: (value) => `${value}`
                        }
                    }
                }
            }
        });
    })();
    // Fill progress bars
    document.querySelectorAll('.bar > span').forEach(el=>{
        const pct = parseFloat((el.style.width||'0').replace('%',''))||0;
        let color = '#22c55e';
        if (pct < 50) color = '#ef4444';
        else if (pct < 80) color = '#f59e0b';
        el.style.background = `linear-gradient(90deg, ${color}, #34d399)`;
        requestAnimationFrame(()=>{ el.style.width = el.style.width; });
    });
</script>

<?php 
$content = ob_get_clean();
require_once 'Includes/student_layout.php';
?>
