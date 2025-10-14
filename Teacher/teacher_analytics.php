<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

// Auto-save functionality removed
?>

<?php
// Get teacher's sections
$teacherSections = getTeacherSections($conn, $teacherId);

// Get selected section (default to first section)
$selectedSectionId = (int)($_GET['section_id'] ?? ($teacherSections[0]['id'] ?? 0));
$selectedSectionName = '';

// Find selected section name
foreach ($teacherSections as $section) {
    if ($section['id'] == $selectedSectionId) {
        $selectedSectionName = $section['section_name'];
        break;
    }
}

// Get students in selected section
$students = [];
if ($selectedSectionId > 0) {
    $stmt = $conn->prepare("
        SELECT s.id, s.name, s.student_number, s.email, s.gender
        FROM students s 
        WHERE s.section_id = ? 
        ORDER BY s.name
    ");
    if ($stmt) {
        $stmt->bind_param('i', $selectedSectionId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }
}

// Get question sets for this section
$questionSets = [];
if ($selectedSectionId > 0) {
    $stmt = $conn->prepare("
        SELECT id, set_title, created_at 
        FROM question_sets 
        WHERE section_id = ? 
        ORDER BY created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $selectedSectionId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questionSets[] = $row;
        }
        $stmt->close();
    }
}

// Calculate performance data for each student
$studentPerformance = [];
$overallStats = [
    'total_students' => count($students),
    'total_sets' => count($questionSets),
    'avg_performance' => 0,
    'completion_rate' => 0
];

$totalScore = 0;
$totalMax = 0;
$completedTests = 0;

foreach ($students as $student) {
    $studentId = (int)$student['id'];
    $studentData = [
        'id' => $studentId,
        'name' => $student['name'],
        'student_number' => $student['student_number'],
        'email' => $student['email'],
        'gender' => $student['gender'],
        'sets_performance' => [],
        'overall_score' => 0,
        'overall_max' => 0,
        'overall_percentage' => 0,
        'completed_sets' => 0
    ];
    
    $studentTotalScore = 0;
    $studentTotalMax = 0;
    $studentCompleted = 0;
    
    foreach ($questionSets as $set) {
        $setId = (int)$set['id'];
        
        // Get student's performance for this set
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT sr.question_id) as total_questions,
                SUM(sr.score) as total_score,
                COUNT(CASE WHEN sr.score > 0 THEN 1 END) as correct_answers
            FROM student_responses sr 
            WHERE sr.student_id = ? AND sr.question_set_id = ?
        ");
        
        $setPerformance = [
            'set_id' => $setId,
            'set_title' => $set['set_title'],
            'created_at' => $set['created_at'],
            'total_questions' => 0,
            'total_score' => 0,
            'correct_answers' => 0,
            'percentage' => 0,
            'completed' => false
        ];
        
        if ($stmt) {
            $stmt->bind_param('ii', $studentId, $setId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $setPerformance['total_questions'] = (int)$row['total_questions'];
                $setPerformance['total_score'] = (float)$row['total_score'];
                $setPerformance['correct_answers'] = (int)$row['correct_answers'];
                $setPerformance['completed'] = $setPerformance['total_questions'] > 0;
                
                if ($setPerformance['completed']) {
                    $studentCompleted++;
                    $studentTotalScore += $setPerformance['total_score'];
                }
            }
            $stmt->close();
        }
        
        // Get max possible score for this set
        $stmt = $conn->prepare("
            SELECT COUNT(*) as max_questions
            FROM questions 
            WHERE set_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $maxQuestions = (int)$row['max_questions'];
                $setPerformance['max_score'] = $maxQuestions; // Assuming 1 point per question
                $studentTotalMax += $maxQuestions;
                
                if ($setPerformance['completed']) {
                    $setPerformance['percentage'] = $maxQuestions > 0 ? 
                        round(($setPerformance['total_score'] / $maxQuestions) * 100, 1) : 0;
                }
            }
            $stmt->close();
        }
        
        $studentData['sets_performance'][] = $setPerformance;
    }
    
    $studentData['overall_score'] = $studentTotalScore;
    $studentData['overall_max'] = $studentTotalMax;
    $studentData['overall_percentage'] = $studentTotalMax > 0 ? 
        round(($studentTotalScore / $studentTotalMax) * 100, 1) : 0;
    $studentData['completed_sets'] = $studentCompleted;
    
    $studentPerformance[] = $studentData;
    
    // Update overall stats
    if ($studentCompleted > 0) {
        $totalScore += $studentTotalScore;
        $totalMax += $studentTotalMax;
        $completedTests++;
    }
}

// Calculate overall statistics
if ($completedTests > 0) {
    $overallStats['avg_performance'] = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 1) : 0;
    $overallStats['completion_rate'] = count($students) > 0 ? 
        round(($completedTests / (count($students) * count($questionSets))) * 100, 1) : 0;
}

// Sort students by performance (highest first)
usort($studentPerformance, function($a, $b) {
    return $b['overall_percentage'] <=> $a['overall_percentage'];
});

// Page title for layout
$pageTitle = 'Student Performance Analytics';

// Show message if no data available
if (empty($teacherSections) || empty($students)) {
    ob_start();
    ?>
    <div class="page-shell">
        <div class="content-header">
            <h1><i class="fas fa-chart-line"></i> Student Performance Analytics</h1>
            <p>No data available for analytics. Please ensure you have students and question sets.</p>
        </div>
        <div class="analytics-container">
            <div class="no-data">
                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; color: rgba(139, 92, 246, 0.5);"></i>
                <div>No students or question sets found. Please add some data to view analytics.</div>
            </div>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    render_teacher_header('teacher_analytics.php', $teacherName, $pageTitle);
    echo $content;
    render_teacher_footer();
    exit();
}

ob_start();
?>

<style>
/* Page shell and content styling */
.page-shell {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 64px);
    padding: 24px;
}

.content-header {
    margin-bottom: 24px;
    text-align: center;
}

.content-header h1 {
    color: #f1f5f9;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.content-header p {
    color: rgba(241, 245, 249, 0.8);
    font-size: 1.1rem;
    margin: 0;
}

.analytics-container { 
    background: rgba(15, 23, 42, 0.85); 
    padding: 24px; 
    border-radius: 16px; 
    box-shadow: 0 0 40px rgba(139, 92, 246, 0.3); 
    border: 1px solid rgba(139, 92, 246, 0.3); 
    backdrop-filter: blur(12px); 
    margin-bottom: 24px;
}

.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 16px; 
    margin-bottom: 24px; 
}

.stat-card { 
    background: rgba(30, 41, 59, 0.8); 
    border: 1px solid rgba(139, 92, 246, 0.3); 
    border-radius: 12px; 
    padding: 20px; 
    text-align: center; 
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.2); 
    backdrop-filter: blur(10px); 
}

.stat-card:nth-child(1) { background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1)); }
.stat-card:nth-child(2) { background: linear-gradient(135deg, rgba(34, 211, 238, 0.15), rgba(139, 92, 246, 0.1)); }
.stat-card:nth-child(3) { background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(168, 85, 247, 0.1)); }
.stat-card:nth-child(4) { background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(168, 85, 247, 0.1)); }

.stat-value { 
    font-size: 32px; 
    font-weight: 800; 
    color: #f1f5f9; 
    text-shadow: 0 0 15px rgba(139, 92, 246, 0.5); 
    margin-bottom: 8px;
}

.stat-label { 
    color: rgba(241, 245, 249, 0.7); 
    font-size: 14px; 
    font-weight: 500;
}

.section-selector { 
    margin-bottom: 24px; 
}

.section-selector select { 
    background: rgba(30, 41, 59, 0.8); 
    border: 1px solid rgba(139, 92, 246, 0.3); 
    border-radius: 8px; 
    padding: 12px 16px; 
    color: #f1f5f9; 
    font-size: 16px; 
    min-width: 200px;
}

.student-table { 
    width: 100%; 
    border-collapse: collapse; 
    background: rgba(30, 41, 59, 0.6); 
    border-radius: 12px; 
    overflow: hidden;
}

.student-table thead th { 
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.2), rgba(34, 211, 238, 0.15)); 
    color: #f1f5f9; 
    padding: 16px 12px; 
    text-align: left; 
    font-weight: 600;
}

.student-table td { 
    padding: 12px; 
    border-bottom: 1px solid rgba(139, 92, 246, 0.2); 
    color: #e1e5f2;
}

.performance-bar { 
    height: 8px; 
    background: rgba(30, 41, 59, 0.8); 
    border-radius: 4px; 
    overflow: hidden; 
    position: relative;
}

.performance-fill { 
    height: 100%; 
    border-radius: 4px; 
    transition: width 0.3s ease;
}

.performance-excellent { background: linear-gradient(90deg, #22c55e, #16a34a); }
.performance-good { background: linear-gradient(90deg, #3b82f6, #2563eb); }
.performance-average { background: linear-gradient(90deg, #f59e0b, #d97706); }
.performance-poor { background: linear-gradient(90deg, #ef4444, #dc2626); }

.badge { 
    padding: 4px 8px; 
    border-radius: 12px; 
    font-size: 12px; 
    font-weight: 600; 
    text-transform: uppercase;
}

.badge-excellent { background: #dcfce7; color: #065f46; }
.badge-good { background: #dbeafe; color: #1e40af; }
.badge-average { background: #fef3c7; color: #92400e; }
.badge-poor { background: #fee2e2; color: #991b1b; }

.chart-container { 
    background: rgba(30, 41, 59, 0.8); 
    border: 1px solid rgba(139, 92, 246, 0.3); 
    border-radius: 12px; 
    padding: 20px; 
    margin-bottom: 24px; 
    box-shadow: 0 0 25px rgba(139, 92, 246, 0.2); 
    backdrop-filter: blur(10px);
}

.no-data { 
    text-align: center; 
    padding: 40px; 
    color: rgba(241, 245, 249, 0.6); 
    font-style: italic;
}
</style>

<div class="page-shell">
    <div class="content-header">
        <h1><i class="fas fa-chart-line"></i> Student Performance Analytics</h1>
        <p>Track and analyze student performance across question sets.</p>
    </div>

    <div class="analytics-container">
        <div class="section-selector">
            <label for="section-select" style="color: #f1f5f9; margin-right: 12px; font-weight: 600;">Select Section:</label>
            <select id="section-select" onchange="changeSection()">
                <?php foreach ($teacherSections as $section): ?>
                    <option value="<?php echo $section['id']; ?>" <?php echo $section['id'] == $selectedSectionId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($section['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filters-container" style="margin-bottom: 24px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            <div class="search-box">
                <input type="text" id="student-search" placeholder="Search students..." 
                       style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(139, 92, 246, 0.3); 
                              border-radius: 8px; padding: 12px 16px; color: #f1f5f9; font-size: 16px; 
                              min-width: 250px;" onkeyup="filterStudents()">
            </div>
            <div class="performance-filter">
                <select id="performance-filter" onchange="filterStudents()" 
                        style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(139, 92, 246, 0.3); 
                               border-radius: 8px; padding: 12px 16px; color: #f1f5f9; font-size: 16px;">
                    <option value="all">All Performance Levels</option>
                    <option value="excellent">Excellent (90%+)</option>
                    <option value="good">Very Good (75-89%)</option>
                    <option value="average">Good (50-74%)</option>
                    <option value="poor">Needs Improvement (<50%)</option>
                </select>
            </div>
            <div class="completion-filter">
                <select id="completion-filter" onchange="filterStudents()" 
                        style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(139, 92, 246, 0.3); 
                               border-radius: 8px; padding: 12px 16px; color: #f1f5f9; font-size: 16px;">
                    <option value="all">All Students</option>
                    <option value="completed">Completed Sets Only</option>
                    <option value="incomplete">Incomplete Sets Only</option>
                </select>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $overallStats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $overallStats['total_sets']; ?></div>
                <div class="stat-label">Question Sets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $overallStats['avg_performance']; ?>%</div>
                <div class="stat-label">Average Performance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $overallStats['completion_rate']; ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
        </div>

        <?php if (count($students) > 0): ?>
            <div class="chart-container">
                <h3 style="color: #f1f5f9; margin-bottom: 16px;">Performance Overview</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <div id="student-table-container">
                <table class="student-table" id="student-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student Number</th>
                            <th>Overall Performance</th>
                            <th>Completed Sets</th>
                            <th>Performance Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentPerformance as $student): ?>
                            <?php
                            $performanceClass = 'performance-poor';
                            $badgeClass = 'badge-poor';
                            $performanceLabel = 'Needs Improvement';
                            
                            if ($student['overall_percentage'] >= 90) {
                                $performanceClass = 'performance-excellent';
                                $badgeClass = 'badge-excellent';
                                $performanceLabel = 'Excellent';
                            } elseif ($student['overall_percentage'] >= 75) {
                                $performanceClass = 'performance-good';
                                $badgeClass = 'badge-good';
                                $performanceLabel = 'Very Good';
                            } elseif ($student['overall_percentage'] >= 50) {
                                $performanceClass = 'performance-average';
                                $badgeClass = 'badge-average';
                                $performanceLabel = 'Good';
                            }
                            ?>
                            <tr class="student-row" 
                                data-name="<?php echo htmlspecialchars(strtolower($student['name'])); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($student['email'])); ?>"
                                data-student-number="<?php echo htmlspecialchars(strtolower($student['student_number'])); ?>"
                                data-performance="<?php echo $student['overall_percentage']; ?>"
                                data-completed="<?php echo $student['completed_sets']; ?>"
                                data-total-sets="<?php echo count($questionSets); ?>">
                                <td>
                                    <div style="font-weight: 600; color: #f1f5f9;"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <div style="font-size: 12px; color: rgba(241, 245, 249, 0.6);"><?php echo htmlspecialchars($student['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                <td>
                                    <div style="margin-bottom: 4px;">
                                        <span style="font-weight: 600; color: #f1f5f9;"><?php echo $student['overall_percentage']; ?>%</span>
                                        <span style="color: rgba(241, 245, 249, 0.6); font-size: 12px;">(<?php echo $student['overall_score']; ?>/<?php echo $student['overall_max']; ?>)</span>
                                    </div>
                                    <div class="performance-bar">
                                        <div class="performance-fill <?php echo $performanceClass; ?>" 
                                             style="width: <?php echo min(100, $student['overall_percentage']); ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #f1f5f9;"><?php echo $student['completed_sets']; ?></span>
                                    <span style="color: rgba(241, 245, 249, 0.6);">/ <?php echo count($questionSets); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $performanceLabel; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="no-results" class="no-data" style="display: none;">
                <i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; color: rgba(139, 92, 246, 0.5);"></i>
                <div>No students match the current filters.</div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; color: rgba(139, 92, 246, 0.5);"></i>
                <div>No students found in the selected section.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function changeSection() {
    const sectionId = document.getElementById('section-select').value;
    window.location.href = `?section_id=${sectionId}`;
}

// Performance Chart
<?php if (count($students) > 0): ?>
(function() {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    const studentNames = <?php echo json_encode(array_map(function($s) { return $s['name']; }, $studentPerformance)); ?>;
    const performanceData = <?php echo json_encode(array_map(function($s) { return $s['overall_percentage']; }, $studentPerformance)); ?>;
    
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.8)');
    gradient.addColorStop(1, 'rgba(34, 211, 238, 0.3)');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: studentNames,
            datasets: [{
                label: 'Performance %',
                data: performanceData,
                backgroundColor: gradient,
                borderColor: '#8b5cf6',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            aspectRatio: 2.5,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    min: 0,
                    max: 100,
                    ticks: {
                        color: '#e1e5f2',
                        stepSize: 20,
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    grid: {
                        color: 'rgba(139, 92, 246, 0.2)',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: '#e1e5f2',
                        maxRotation: 0,
                        minRotation: 0,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
})();
<?php endif; ?>

// Filtering functionality
function filterStudents() {
    const searchTerm = document.getElementById('student-search').value.toLowerCase();
    const performanceFilter = document.getElementById('performance-filter').value;
    const completionFilter = document.getElementById('completion-filter').value;
    
    const rows = document.querySelectorAll('.student-row');
    const noResults = document.getElementById('no-results');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const email = row.dataset.email;
        const studentNumber = row.dataset.studentNumber;
        const performance = parseFloat(row.dataset.performance);
        const completed = parseInt(row.dataset.completed);
        const totalSets = parseInt(row.dataset.totalSets);
        
        let showRow = true;
        
        // Search filter
        if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm) && !studentNumber.includes(searchTerm)) {
            showRow = false;
        }
        
        // Performance filter
        if (performanceFilter !== 'all') {
            switch (performanceFilter) {
                case 'excellent':
                    if (performance < 90) showRow = false;
                    break;
                case 'good':
                    if (performance < 75 || performance >= 90) showRow = false;
                    break;
                case 'average':
                    if (performance < 50 || performance >= 75) showRow = false;
                    break;
                case 'poor':
                    if (performance >= 50) showRow = false;
                    break;
            }
        }
        
        // Completion filter
        if (completionFilter !== 'all') {
            switch (completionFilter) {
                case 'completed':
                    if (completed === 0) showRow = false;
                    break;
                case 'incomplete':
                    if (completed === totalSets) showRow = false;
                    break;
            }
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    if (visibleCount === 0) {
        noResults.style.display = 'block';
        document.getElementById('student-table').style.display = 'none';
    } else {
        noResults.style.display = 'none';
        document.getElementById('student-table').style.display = 'table';
    }
}

// Animate performance bars
document.querySelectorAll('.performance-fill').forEach(el => {
    const width = el.style.width;
    el.style.width = '0%';
    setTimeout(() => {
        el.style.width = width;
    }, 100);
});
</script>

<?php 
$content = ob_get_clean();

// Render the page with proper layout
render_teacher_header('teacher_analytics.php', $teacherName, $pageTitle);
echo $content;
render_teacher_footer();
?>