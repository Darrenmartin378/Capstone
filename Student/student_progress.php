<?php
require_once 'includes/student_init.php';

$pageTitle = 'My Progress';

// Get student's assessment results and progress data
$progressData = [];
$assessmentResults = $conn->query("
    SELECT a.title, ar.answer, aq.question_text, ar.submitted_at
    FROM assessment_responses ar
    JOIN assessment_questions aq ON ar.question_id = aq.id
    JOIN assessments a ON ar.assessment_id = a.id
    WHERE ar.student_id = $studentId
    ORDER BY ar.submitted_at DESC
    LIMIT 10
");

if ($assessmentResults && $assessmentResults->num_rows > 0) {
    while ($row = $assessmentResults->fetch_assoc()) {
        $progressData[] = $row;
    }
}

// Calculate overall statistics
$statsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT ar.assessment_id) as total_tests,
        COUNT(ar.id) as total_responses
    FROM assessment_responses ar
    WHERE ar.student_id = $studentId
");

$stats = $statsQuery ? $statsQuery->fetch_assoc() : [
    'total_tests' => 0,
    'total_responses' => 0
];

// Add default values for missing fields
$stats['average_score'] = 0;
$stats['highest_score'] = 0;
$stats['lowest_score'] = 0;

$content = '
<div class="progress-container">
    <div class="progress-header">
        <h2>📊 My Learning Progress</h2>
        <p>Track your academic journey and see how you\'re improving!</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-content">
                <h3>' . $stats['total_tests'] . '</h3>
                <p>Tests Completed</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⭐</div>
            <div class="stat-content">
                <h3>' . round($stats['average_score'], 1) . '%</h3>
                <p>Average Score</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-content">
                <h3>' . $stats['highest_score'] . '%</h3>
                <p>Best Score</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <h3>' . ($stats['total_tests'] > 0 ? 'Improving' : 'Getting Started') . '</h3>
                <p>Progress Status</p>
            </div>
        </div>
    </div>

    <div class="recent-tests">
        <h3>📋 Recent Test Results</h3>
        <div class="test-results-list">';

if (!empty($progressData)) {
    foreach ($progressData as $assessment) {
        $content .= '
        <div class="test-result-item good">
            <div class="test-info">
                <h4>' . h($assessment['title']) . '</h4>
                <p class="test-date">' . date('M d, Y', strtotime($assessment['submitted_at'])) . '</p>
                <p class="question-preview">' . h(substr($assessment['question_text'], 0, 100)) . '...</p>
            </div>
            <div class="test-score">
                <div class="score-circle">
                    <span class="score-percentage">✓</span>
                </div>
                <p class="score-detail">Answered</p>
            </div>
        </div>';
    }
} else {
    $content .= '
    <div class="no-data">
        <div class="no-data-icon">📊</div>
        <h4>No assessment results yet</h4>
        <p>Start taking assessments to see your progress here!</p>
        <a href="student_tests.php" class="btn-primary">Take an Assessment</a>
    </div>';
}

$content .= '
        </div>
    </div>
</div>

<style>
.progress-container {
    max-width: 1200px;
    margin: 0 auto;
}

.progress-header {
    text-align: center;
    margin-bottom: 2rem;
}

.progress-header h2 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin: 0;
}

.stat-content p {
    color: var(--text-secondary);
    margin: 0;
    font-weight: 500;
}

.recent-tests {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: var(--shadow-md);
}

.recent-tests h3 {
    color: var(--primary);
    margin-bottom: 1.5rem;
}

.test-results-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.test-result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid;
    transition: transform 0.2s ease;
}

.test-result-item:hover {
    transform: translateX(4px);
}

.test-result-item.excellent {
    background: #E8F5E8;
    border-left-color: var(--success);
}

.test-result-item.good {
    background: #FFF3E0;
    border-left-color: var(--warning);
}

.test-result-item.needs-improvement {
    background: #FFEBEE;
    border-left-color: var(--error);
}

.test-info h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.test-date {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.9rem;
}

.test-score {
    text-align: center;
}

.score-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.excellent .score-circle {
    background: var(--success);
    color: white;
}

.good .score-circle {
    background: var(--warning);
    color: white;
}

.needs-improvement .score-circle {
    background: var(--error);
    color: white;
}

.score-detail {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.no-data {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.no-data-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.btn-primary {
    display: inline-block;
    background: var(--primary);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 1rem;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .test-result-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
}
</style>';

include 'includes/student_layout.php';
?>
