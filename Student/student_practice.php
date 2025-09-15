<?php
require_once 'includes/student_init.php';

$pageTitle = 'Practice Materials';

// Get practice materials from question bank
$practiceMaterials = [];
$materialsQuery = $conn->query("
    SELECT qb.id, qb.question_type, qb.question_text, qb.created_at,
           t.name as teacher_name
    FROM question_bank qb
    JOIN teachers t ON qb.teacher_id = t.id
    WHERE qb.question_category = 'practice'
    ORDER BY qb.created_at DESC
    LIMIT 10
");

if ($materialsQuery && $materialsQuery->num_rows > 0) {
    while ($row = $materialsQuery->fetch_assoc()) {
        $practiceMaterials[] = $row;
    }
}

// Get student's practice progress from warmup responses
$practiceProgress = [];
$progressQuery = $conn->query("
    SELECT question_id, COUNT(*) as attempts
    FROM warmup_responses 
    WHERE student_id = $studentId
    GROUP BY question_id
");

if ($progressQuery && $progressQuery->num_rows > 0) {
    while ($row = $progressQuery->fetch_assoc()) {
        $practiceProgress[$row['question_id']] = $row;
    }
}

$content = '
<div class="practice-container">
    <div class="practice-header">
        <h2>🎯 Additional Practice Materials</h2>
        <p>Extra exercises to help you master the concepts and improve your skills!</p>
    </div>

    <div class="practice-grid">';

if (!empty($practiceMaterials)) {
    foreach ($practiceMaterials as $material) {
        $progress = isset($practiceProgress[$material['id']]) ? $practiceProgress[$material['id']] : null;
        $attempts = $progress ? $progress['attempts'] : 0;
        $isCompleted = $attempts > 0;
        
        $content .= '
        <div class="practice-card ' . ($isCompleted ? 'completed' : '') . '">
            <div class="practice-icon">
                ' . ($material['question_type'] === 'multiple_choice' ? '📝' : ($material['question_type'] === 'essay' ? '✏️' : '🔗')) . '
            </div>
            <div class="practice-content">
                <h3>Practice Question #' . $material['id'] . '</h3>
                <p class="practice-description">' . h(substr($material['question_text'], 0, 150)) . '...</p>
                <div class="practice-meta">
                    <span class="practice-type">' . ucfirst(str_replace('_', ' ', $material['question_type'])) . '</span>
                    <span class="practice-difficulty difficulty-medium">Practice</span>
                </div>
                <div class="practice-progress">';
        
        if ($progress) {
            $content .= '
                    <div class="progress-info">
                        <span class="progress-label">Attempts: ' . $attempts . '</span>
                        <span class="attempts">Last practiced: ' . date('M d', strtotime($material['created_at'])) . '</span>
                    </div>';
        }
        
        $content .= '
                </div>
                <div class="practice-actions">
                    <a href="student_questions.php?id=' . $material['id'] . '" class="btn-practice">
                        ' . ($isCompleted ? '🔄 Practice Again' : '▶️ Start Practice') . '
                    </a>';
        
        if ($isCompleted) {
            $content .= '
                    <span class="completion-badge">✅ Practiced</span>';
        }
        
        $content .= '
                </div>
            </div>
        </div>';
    }
} else {
    $content .= '
    <div class="no-practice">
        <div class="no-practice-icon">🎯</div>
        <h4>No practice materials available</h4>
        <p>Check back later for additional practice exercises!</p>
    </div>';
}

$content .= '
    </div>
</div>

<style>
.practice-container {
    max-width: 1200px;
    margin: 0 auto;
}

.practice-header {
    text-align: center;
    margin-bottom: 2rem;
}

.practice-header h2 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.practice-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.practice-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.practice-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.practice-card.completed {
    border-color: var(--success);
    background: linear-gradient(135deg, #E8F5E8 0%, #ffffff 100%);
}

.practice-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 1rem;
}

.practice-content h3 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 1.3rem;
}

.practice-description {
    color: var(--text-secondary);
    margin-bottom: 1rem;
    line-height: 1.5;
}

.practice-meta {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.practice-type {
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.practice-difficulty {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.difficulty-easy {
    background: var(--success);
    color: white;
}

.difficulty-medium {
    background: var(--warning);
    color: white;
}

.difficulty-hard {
    background: var(--error);
    color: white;
}

.practice-progress {
    margin-bottom: 1rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.practice-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-practice {
    background: var(--secondary);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    flex: 1;
    text-align: center;
    margin-right: 1rem;
}

.btn-practice:hover {
    background: #e68900;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.completion-badge {
    background: var(--success);
    color: white;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.no-practice {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.no-practice-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .practice-grid {
        grid-template-columns: 1fr;
    }
    
    .practice-actions {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn-practice {
        margin-right: 0;
    }
}
</style>';

include 'includes/student_layout.php';
?>
