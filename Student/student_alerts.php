<?php
require_once 'includes/student_init.php';

$pageTitle = 'Performance Alerts';

// Get performance alerts (simulated based on assessment responses)
$alerts = [];
$assessmentCount = $conn->query("SELECT COUNT(*) as count FROM assessment_responses WHERE student_id = $studentId");
$assessmentCount = $assessmentCount ? $assessmentCount->fetch_assoc()['count'] : 0;

if ($assessmentCount > 0) {
    $alerts[] = [
        'id' => 1,
        'title' => 'Great Progress!',
        'message' => 'You have completed ' . $assessmentCount . ' assessment responses. Keep up the good work!',
        'severity' => 'low',
        'created_at' => date('Y-m-d H:i:s')
    ];
} else {
    $alerts[] = [
        'id' => 2,
        'title' => 'Get Started!',
        'message' => 'Start taking assessments to track your performance and see your progress.',
        'severity' => 'medium',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Get student's recent performance data from assessment responses
$performanceData = [];
$performanceQuery = $conn->query("
    SELECT 
        a.title,
        ar.submitted_at,
        'medium' as performance_level
    FROM assessment_responses ar
    JOIN assessments a ON ar.assessment_id = a.id
    WHERE ar.student_id = $studentId
    ORDER BY ar.submitted_at DESC
    LIMIT 5
");

if ($performanceQuery && $performanceQuery->num_rows > 0) {
    while ($row = $performanceQuery->fetch_assoc()) {
        $performanceData[] = $row;
    }
}

$content = '
<div class="alerts-container">
    <div class="alerts-header">
        <h2>⚠️ Performance Alerts</h2>
        <p>Stay informed about your academic performance and areas that need attention.</p>
    </div>

    <div class="alerts-content">
        <div class="alerts-section">
            <h3>🚨 Current Alerts</h3>
            <div class="alerts-list">';

if (!empty($alerts)) {
    foreach ($alerts as $alert) {
        $alertClass = $alert['severity'] === 'high' ? 'alert-high' : ($alert['severity'] === 'medium' ? 'alert-medium' : 'alert-low');
        $alertIcon = $alert['severity'] === 'high' ? '🔴' : ($alert['severity'] === 'medium' ? '🟡' : '🟢');
        
        $content .= '
        <div class="alert-item ' . $alertClass . '">
            <div class="alert-icon">' . $alertIcon . '</div>
            <div class="alert-content">
                <h4>' . h($alert['title']) . '</h4>
                <p>' . h($alert['message']) . '</p>
                <div class="alert-meta">
                    <span class="alert-date">' . date('M d, Y', strtotime($alert['created_at'])) . '</span>
                    <span class="alert-severity">' . ucfirst($alert['severity']) . ' Priority</span>
                </div>
            </div>
            <div class="alert-actions">
                <button class="btn-dismiss" onclick="dismissAlert(' . $alert['id'] . ')">Dismiss</button>
            </div>
        </div>';
    }
} else {
    $content .= '
    <div class="no-alerts">
        <div class="no-alerts-icon">✅</div>
        <h4>No alerts at this time</h4>
        <p>Great job! Keep up the excellent work!</p>
    </div>';
}

$content .= '
            </div>
        </div>

        <div class="performance-section">
            <h3>📊 Recent Performance Overview</h3>
            <div class="performance-list">';

if (!empty($performanceData)) {
    foreach ($performanceData as $performance) {
        $performanceClass = $performance['performance_level'];
        
        $content .= '
        <div class="performance-item ' . $performanceClass . '">
            <div class="performance-info">
                <h4>' . h($performance['title']) . '</h4>
                <p class="performance-date">' . date('M d, Y', strtotime($performance['submitted_at'])) . '</p>
            </div>
            <div class="performance-score">
                <div class="score-badge ' . $performanceClass . '">
                    ✓
                </div>
                <p class="score-detail">Assessment completed</p>
            </div>
        </div>';
    }
} else {
    $content .= '
    <div class="no-performance">
        <div class="no-performance-icon">📊</div>
        <h4>No performance data yet</h4>
        <p>Take some assessments to see your performance overview here!</p>
    </div>';
}

$content .= '
            </div>
        </div>
    </div>
</div>

<style>
.alerts-container {
    max-width: 1200px;
    margin: 0 auto;
}

.alerts-header {
    text-align: center;
    margin-bottom: 2rem;
}

.alerts-header h2 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.alerts-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.alerts-section, .performance-section {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
}

.alerts-section h3, .performance-section h3 {
    color: var(--primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alerts-list, .performance-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.alert-item {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.alert-item:hover {
    transform: translateX(4px);
}

.alert-high {
    background: #FFEBEE;
    border-left-color: var(--error);
}

.alert-medium {
    background: #FFF3E0;
    border-left-color: var(--warning);
}

.alert-low {
    background: #E8F5E8;
    border-left-color: var(--success);
}

.alert-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
}

.alert-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.alert-content p {
    margin: 0 0 1rem 0;
    color: var(--text-secondary);
    line-height: 1.5;
}

.alert-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
}

.alert-date {
    color: var(--text-muted);
}

.alert-severity {
    font-weight: 600;
}

.alert-actions {
    display: flex;
    align-items: flex-start;
}

.btn-dismiss {
    background: var(--text-muted);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-dismiss:hover {
    background: var(--text-secondary);
}

.performance-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.performance-item:hover {
    transform: translateX(4px);
}

.performance-item.high {
    background: #E8F5E8;
    border-left-color: var(--success);
}

.performance-item.medium {
    background: #FFF3E0;
    border-left-color: var(--warning);
}

.performance-item.low {
    background: #FFEBEE;
    border-left-color: var(--error);
}

.performance-info h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.performance-date {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.performance-score {
    text-align: center;
}

.score-badge {
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

.score-badge.high {
    background: var(--success);
    color: white;
}

.score-badge.medium {
    background: var(--warning);
    color: white;
}

.score-badge.low {
    background: var(--error);
    color: white;
}

.score-detail {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.no-alerts, .no-performance {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.no-alerts-icon, .no-performance-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .alerts-content {
        grid-template-columns: 1fr;
    }
    
    .alert-item, .performance-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .alert-meta {
        justify-content: center;
    }
}
</style>

<script>
function dismissAlert(alertId) {
    if (confirm("Are you sure you want to dismiss this alert?")) {
        // Here you would typically make an AJAX call to dismiss the alert
        fetch("dismiss_alert.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({alert_id: alertId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Error dismissing alert. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error dismissing alert. Please try again.");
        });
    }
}
</script>';

include 'includes/student_layout.php';
?>
